<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use App\Models\YMedioPago;
use App\Models\YCuentaBanco;

class ReciboFacturaController extends Controller
{
    /**
     * Ruta final:
     * /admin/facturacion/factura/{key}
     */
    public function show(string $key)
    {
        return $this->preview($key);
    }

    /**
     * Preview del recibo usando cache
     */
    public function preview(string $tokenOrKey)
    {
        // =========================================================
        // 1) RESOLVER ESTADO DESDE CACHE
        // =========================================================
        $tokenFinal = null;

        $keysToTry = [
            $tokenOrKey,                    // recibo_factura_...
            "recibo_preview:{$tokenOrKey}", // legacy si existiera
        ];

        if (str_contains($tokenOrKey, '_')) {
            $parts = explode('_', $tokenOrKey);
            $tokenFinal = (string) end($parts);

            $keysToTry[] = $tokenFinal;
            $keysToTry[] = "recibo_preview:{$tokenFinal}";
        }

        $state  = null;
        $hitKey = null;

        foreach ($keysToTry as $k) {
            $tmp = Cache::get($k);
            if (is_array($tmp)) {
                $state  = $tmp;
                $hitKey = $k;
                break;
            }
        }

        Log::info('ReciboFacturaController@preview cache lookup', [
            'param'        => $tokenOrKey,
            'token_final'  => $tokenFinal,
            'keys_tried'   => $keysToTry,
            'hit'          => (bool) $state,
            'hit_key'      => $hitKey,
            'cache_driver' => config('cache.default'),
        ]);

        if (!$state || !is_array($state)) {
            abort(404, 'Recibo expirado o no encontrado');
        }

        // =========================================================
        // 2) DATA BASE
        // =========================================================
        $prefijo = (string)($state['prefijo'] ?? 'PPAL');
        $numero  = (string)($state['numero'] ?? ($state['numero_documento'] ?? '0'));
        $fecha   = (string)($state['fecha'] ?? now()->format('Y-m-d H:i:s'));

        $clienteNombre = trim((string)($state['cliente_nombre'] ?? $state['nombre_cliente'] ?? ''));
        $nit           = trim((string)($state['nit'] ?? $state['cedula'] ?? '—'));
        if ($clienteNombre === '') $clienteNombre = 'Cliente ' . ($nit !== '' ? $nit : '—');

        $asesorNombre = trim((string)($state['asesor_nombre'] ?? $state['vendedor_nombre'] ?? $state['nombre_asesor'] ?? '—'));
        if ($asesorNombre === '') $asesorNombre = '—';

        $sedeNombre = trim((string)($state['sede_nombre'] ?? $state['nombre_sede'] ?? '—'));
        if ($sedeNombre === '') $sedeNombre = '—';

        $productosRaw = $state['productos'] ?? $state['items'] ?? [];
        $productos = is_array($productosRaw) ? $productosRaw : [];
        $productos = array_values(array_filter(array_map(fn($x) => (array)$x, $productos)));

        $totalProductos = (float)($state['total_productos'] ?? $state['monto_total_calculado'] ?? $state['total_factura'] ?? 0);
        if ($totalProductos <= 0 && !empty($productos)) {
            $tmp = 0;
            foreach ($productos as $p) {
                $tmp += (float)($p['subtotal'] ?? $p['total'] ?? 0);
            }
            $totalProductos = (float)$tmp;
        }

        $totalFactura = (float)($state['total'] ?? $state['valor_total'] ?? $state['total_factura'] ?? 0);
        if ($totalFactura <= 0) $totalFactura = $totalProductos;

        // =========================================================
        // 3) MEDIO + BANCO (COMPATIBLE CON TU BLADE Y CON DEFAULTS)
        // =========================================================

        // Principal: intenta varias llaves por si tu cache cambia
        $medioPrincipalId = (int)(
            $state['medio_pago']
            ?? $state['codigo_forma_pago']
            ?? $state['codigoFormaPago']
            ?? 0
        );

        $valorPrincipal = (float)(
            $state['plataforma_principal']
            ?? $state['valor_pagado']
            ?? $state['valorPagado']
            ?? $state['total_recibido']
            ?? $totalFactura
        );

        // Banco principal: varias llaves
        $bancoPrincipalId = (int)(
            $state['banco_transferencia_principal_id']
            ?? $state['banco_transferencia_id']
            ?? $state['banco_id']
            ?? 0
        );

        $bancoPrincipalText = trim((string)(
            $state['banco_transferencia_principal_text']
            ?? $state['banco_transferencia_text']
            ?? $state['banco_text']
            ?? ''
        ));

        // Métodos adicionales (Repeater)
        $metodosRaw = $state['metodos_pago'] ?? $state['medios_pago'] ?? $state['pagos'] ?? [];
        $metodosWizard = is_array($metodosRaw) ? $metodosRaw : [];
        $metodosWizard = array_values(array_filter(array_map(fn($x) => (array)$x, $metodosWizard)));

        // Si no viene el banco en root, intenta tomarlo del primer método (cuando el wizard sí lo guarda allí)
        if ($bancoPrincipalId <= 0 && $bancoPrincipalText === '' && !empty($metodosWizard)) {
            $first = (array)$metodosWizard[0];

            $tmpBid = (int)($first['banco_transferencia_id'] ?? $first['banco_id'] ?? 0);
            $tmpBtx = trim((string)($first['banco_transferencia_text'] ?? $first['banco_text'] ?? ''));

            if ($tmpBid > 0) $bancoPrincipalId = $tmpBid;
            if ($tmpBtx !== '') $bancoPrincipalText = $tmpBtx;
        }

        // ✅ DEFAULTS IMPORTANTES: si el medio es 3 o 10 y NO viene banco, lo inferimos como en tu wizard
        if ($bancoPrincipalId <= 0) {
            if ($medioPrincipalId === 3) $bancoPrincipalId = 1;
            if ($medioPrincipalId === 10) $bancoPrincipalId = 5;
        }

        // Resolver nombre del medio desde BD si no tienes texto guardado
        $medioPrincipalNombre = trim((string)(
            $state['medio_pago_nombre']
            ?? $state['plataforma_nombre']
            ?? $state['medio_pago_text']
            ?? ''
        ));

        if ($medioPrincipalNombre === '' && $medioPrincipalId > 0) {
            try {
                $medioPrincipalNombre = trim((string)YMedioPago::where('Id_Medio_Pago', $medioPrincipalId)->value('Nombre_Medio_Pago'));
            } catch (\Throwable $e) {
                $medioPrincipalNombre = '';
            }
        }

        // Resolver texto banco desde BD si hay ID pero no texto
        if ($bancoPrincipalId > 0 && $bancoPrincipalText === '') {
            try {
                $bancoPrincipalText = trim((string)YCuentaBanco::where('ID_Banco', $bancoPrincipalId)->value('Cuenta_Banco'));
            } catch (\Throwable $e) {
                $bancoPrincipalText = '';
            }
        }

        Log::info('ReciboFacturaController@preview pago normalizado', [
            'medio_principal_id'   => $medioPrincipalId,
            'medio_principal_name' => $medioPrincipalNombre,
            'banco_principal_id'   => $bancoPrincipalId,
            'banco_principal_text' => $bancoPrincipalText,
            'metodos_count'        => count($metodosWizard),
            'metodos_sample'       => array_slice($metodosWizard, 0, 2),
        ]);

        $mediosPago = [];

        // 1) Principal
        if ($medioPrincipalId > 0 || $medioPrincipalNombre !== '') {
            $nombre = trim($medioPrincipalNombre) ?: ($medioPrincipalId ? "FORMA PAGO {$medioPrincipalId}" : 'N/D');

            $mediosPago[] = [
                // ✅ Tu Blade usa "nombre" o "codigo"
                'nombre' => $nombre,
                'codigo' => (string)($medioPrincipalId ?: 'PAGO'),

                // (Extras por si los usas en otros blades)
                'medio_id' => $medioPrincipalId ?: null,
                'medio'    => mb_strtoupper($nombre),

                'valor' => (int)round($this->parseMonto($valorPrincipal)),

                'banco_transferencia_id'   => $bancoPrincipalId ?: null,
                'banco_transferencia_text' => ($bancoPrincipalText !== '' ? $bancoPrincipalText : 'No aplica'),
            ];
        }

        // 2) Adicionales (si existen)
        foreach ($metodosWizard as $w) {
            $mid = (int)(
                $w['medio_id']
                ?? $w['codigoFormaPago']
                ?? $w['codigo_forma_pago']
                ?? ($w['objFormaPago']['formaPago'] ?? 0)
                ?? 0
            );

            $monto = (float)$this->parseMonto(
                $w['monto']
                ?? $w['valor']
                ?? $w['valorPagado']
                ?? $w['valor_pagado']
                ?? 0
            );

            if ($mid <= 0 && $monto <= 0) continue;

            $nombre = trim((string)($w['plataforma_nombre'] ?? $w['medio'] ?? $w['nombre'] ?? ''));
            if ($nombre === '' && $mid > 0) {
                try {
                    $nombre = trim((string)YMedioPago::where('Id_Medio_Pago', $mid)->value('Nombre_Medio_Pago'));
                } catch (\Throwable $e) {
                    $nombre = '';
                }
            }
            $nombre = $nombre !== '' ? $nombre : ($mid ? "FORMA PAGO {$mid}" : 'N/D');

            $bid = (int)($w['banco_transferencia_id'] ?? $w['banco_id'] ?? 0);
            $btx = trim((string)($w['banco_transferencia_text'] ?? $w['banco_text'] ?? ''));

            // ✅ default si aplica (3->1, 10->5) cuando en adicionales también falte
            if ($bid <= 0) {
                if ($mid === 3) $bid = 1;
                if ($mid === 10) $bid = 5;
            }

            if ($bid > 0 && $btx === '') {
                try {
                    $btx = trim((string)YCuentaBanco::where('ID_Banco', $bid)->value('Cuenta_Banco'));
                } catch (\Throwable $e) {
                    $btx = '';
                }
            }
            if ($btx === '') $btx = 'No aplica';

            // Evitar duplicado exacto
            $dup = false;
            foreach ($mediosPago as $m) {
                if ((string)($m['codigo'] ?? '') === (string)$mid && (int)($m['valor'] ?? 0) === (int)round($monto)) {
                    $dup = true;
                    break;
                }
            }
            if ($dup) continue;

            $mediosPago[] = [
                'nombre' => $nombre,
                'codigo' => (string)($mid ?: 'PAGO'),

                'medio_id' => $mid ?: null,
                'medio'    => mb_strtoupper($nombre),

                'valor' => (int)round($monto),

                'banco_transferencia_id'   => $bid ?: null,
                'banco_transferencia_text' => $btx,
            ];
        }

        // 3) Fallback
        if (empty($mediosPago)) {
            $mediosPago[] = [
                'nombre' => 'PAGO REGISTRADO',
                'codigo' => 'PAGO',
                'valor'  => (int)round($totalFactura),
                'banco_transferencia_id'   => null,
                'banco_transferencia_text' => 'No aplica',
            ];
        }

        // =========================================================
        // 4) DATA FINAL PARA BLADE
        // =========================================================
        $data = [
            'prefijo' => $prefijo,
            'numero'  => $numero,
            'fecha'   => $fecha,

            'cliente_nombre' => $clienteNombre,
            'nit'            => $nit,
            'asesor_nombre'  => $asesorNombre,
            'sede_nombre'    => $sedeNombre,

            'productos'   => $productos,
            'medios_pago' => $mediosPago,

            'total_productos' => $totalProductos,
            'total'           => $totalFactura,

            'watermark_text' => $state['watermark_text'] ?? "NO ES FACTURA DE VENTA\nDOCUMENTO INFORMATIVO",
            'watermark_font' => $state['watermark_font'] ?? 'Calibri',

            'empresa_nombre'    => $state['empresa_nombre'] ?? 'Distribuciones Tecnológicas de Colombia S.A.S.',
            'empresa_nit'       => $state['empresa_nit'] ?? 'NIT: 901042503-1',
            'empresa_direccion' => $state['empresa_direccion'] ?? 'Dirección: Calle 22 # /8-22',
            'empresa_ciudad'    => $state['empresa_ciudad'] ?? 'Pereira, Colombia',
            'empresa_telefono'  => $state['empresa_telefono'] ?? 'Tel: 3136200202',
            'empresa_email'     => $state['empresa_email'] ?? 'Email: info@empresa.com',
            'empresa_logo_url'  => $state['empresa_logo_url'] ?? 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTF5_J9853KbJEH3Qm-KwmFyU_JghyYA_aHTg&s',

            'generado_en' => now()->format('Y-m-d H:i:s'),
        ];

        return view('filament.facturacion.recibo-preview', compact('data'));
    }

    /**
     * Acepta: 20000, "20.000", "20,000", "$ 20.000"
     */
    protected function parseMonto($value): float
    {
        if ($value === null || $value === '') return 0.0;
        if (is_int($value) || is_float($value)) return (float) $value;

        $str = trim((string) $value);
        $str = preg_replace('/[^0-9,\.\-]/', '', $str);

        if ($str === '' || $str === '-') return 0.0;

        if (str_contains($str, '.') && !str_contains($str, ',')) {
            return (float) str_replace('.', '', $str);
        }

        if (str_contains($str, '.') && str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
            return (float) $str;
        }

        if (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        }

        return (float) $str;
    }
}
