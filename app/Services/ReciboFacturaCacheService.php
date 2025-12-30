<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Modelos para resolver nombres
use App\Models\YMedioPago;
use App\Models\YCuentaBanco;

class ReciboFacturaCacheService
{
    /**
     * Cachea el estado del recibo/factura con medio + banco correctamente.
     * Retorna: ['cache_key' => ..., 'url' => ...]
     */
    public function cachear(array $payload, array $extra = [], int $minutes = 30): array
    {
        // ---------------------------
        // Helpers (data_get sin drama)
        // ---------------------------
        $get = function (string $key, $default = null) use ($payload, $extra) {
            // prioridad: extra > payload
            return data_get($extra, $key, data_get($payload, $key, $default));
        };

        // ===========================
        // 1) Datos base
        // ===========================
        $nit     = (string)($get('nit') ?? $get('cedula') ?? '0');
        $prefijo = (string)($get('prefijo') ?? 'PPAL');
        $numero  = (string)($get('numero') ?? $get('numero_documento') ?? '0');
        $fecha   = (string)($get('fecha') ?? now()->format('Y-m-d H:i:s'));

        $clienteNombre = trim((string)($get('cliente_nombre') ?? $get('nombre_cliente') ?? ''));
        $asesorNombre  = trim((string)($get('asesor_nombre') ?? $get('vendedor_nombre') ?? ''));
        $sedeNombre    = trim((string)($get('sede_nombre') ?? $get('nombre_sede') ?? ''));

        // Productos
        $productos = $get('productos') ?? $get('items') ?? [];
        if (!is_array($productos)) $productos = [];
        $productos = array_values(array_filter(array_map(fn($x) => (array)$x, $productos)));

        // Total
        $totalFactura = (float)($get('total') ?? $get('total_factura') ?? $get('valor_total') ?? 0);
        if ($totalFactura <= 0 && !empty($productos)) {
            $tmp = 0;
            foreach ($productos as $p) {
                $tmp += (float)($p['subtotal'] ?? $p['total'] ?? 0);
            }
            $totalFactura = (float)$tmp;
        }

        // ===========================
        // 2) Medio principal (Filament)
        // ===========================
        $medioPagoId = (int)(
            $get('medio_pago')
            ?? $get('codigo_medio_pago')
            ?? $get('codigoFormaPago')
            ?? $get('codigo_forma_pago')
            ?? 0
        );

        // Si te llega el nombre explícito, bien. Si no, lo resolvemos desde BD.
        $medioPagoNombre = trim((string)(
            $get('medio_pago_nombre')
            ?? $get('plataforma_nombre')
            ?? $get('medio_pago_text')
            ?? $get('medioPagoNombre')
            ?? ''
        ));

        if ($medioPagoNombre === '' && $medioPagoId > 0) {
            try {
                $medioPagoNombre = (string)YMedioPago::where('Id_Medio_Pago', $medioPagoId)->value('Nombre_Medio_Pago');
                $medioPagoNombre = trim((string)$medioPagoNombre);
            } catch (\Throwable $e) {
                // silencioso
            }
        }

        // Valor principal (lo que calculó el wizard)
        $valorPrincipal = (float)(
            $get('plataforma_principal')
            ?? $get('valor_principal')
            ?? $get('valor_pagado')
            ?? $get('valorPagado')
            ?? $get('total_recibido')
            ?? $totalFactura
        );

        // ===========================
        // 3) Banco principal (CLAVE)
        // ===========================
        $bancoId = (int)(
            $get('banco_transferencia_principal_id')
            ?? $get('banco_transferencia_id') // por si lo mandaste mal alguna vez
            ?? $get('banco_id')
            ?? 0
        );

        $bancoText = trim((string)(
            $get('banco_transferencia_principal_text')
            ?? $get('banco_transferencia_text')
            ?? $get('banco_text')
            ?? ''
        ));

        // ✅ Defaults como tu wizard:
        // medio 3 => banco 1, medio 10 => banco 5
        if ($bancoId <= 0) {
            if ($medioPagoId === 3) $bancoId = 1;
            if ($medioPagoId === 10) $bancoId = 5;
        }

        // Resolver texto si hay id pero no texto
        if ($bancoId > 0 && $bancoText === '') {
            try {
                $bancoText = trim((string)YCuentaBanco::where('ID_Banco', $bancoId)->value('Cuenta_Banco'));
            } catch (\Throwable $e) {
                // silencioso
            }
        }

        // ===========================
        // 4) Métodos adicionales (Repeater)
        // ===========================
        $metodos = $get('metodos_pago') ?? [];
        if (!is_array($metodos)) $metodos = [];
        $metodos = array_values(array_filter(array_map(fn($x) => (array)$x, $metodos)));

        // Normaliza banco por item (por si viene vacío pero aplica)
        foreach ($metodos as &$m) {
            $mid = (int)($m['medio_id'] ?? 0);

            $bid = (int)($m['banco_transferencia_id'] ?? $m['banco_id'] ?? 0);
            $btx = trim((string)($m['banco_transferencia_text'] ?? $m['banco_text'] ?? ''));

            if ($bid <= 0) {
                if ($mid === 3) $bid = 1;
                if ($mid === 10) $bid = 5;
            }

            if ($bid > 0 && $btx === '') {
                try {
                    $btx = trim((string)YCuentaBanco::where('ID_Banco', $bid)->value('Cuenta_Banco'));
                } catch (\Throwable $e) {}
            }

            $m['banco_transferencia_id'] = $bid ?: null;
            $m['banco_transferencia_text'] = $btx !== '' ? $btx : null;
        }
        unset($m);

        // ===========================
        // 5) Guardar en cache
        // ===========================
        $token = Str::random(10);
        $cacheKey = "recibo_factura_{$nit}_{$prefijo}_{$numero}_{$token}";

        $state = [
            'prefijo' => $prefijo,
            'numero'  => $numero,
            'fecha'   => $fecha,

            'cliente_nombre' => $clienteNombre,
            'nit'            => $nit,
            'asesor_nombre'  => $asesorNombre,
            'sede_nombre'    => $sedeNombre,

            'productos'       => $productos,
            'total_productos' => $totalFactura,
            'total'           => $totalFactura,

            // ✅ Recibo consume esto
            'medio_pago'                 => $medioPagoId,
            'medio_pago_nombre'          => $medioPagoNombre,
            'plataforma_principal'       => $valorPrincipal,

            'banco_transferencia_principal_id'   => $bancoId ?: null,
            'banco_transferencia_principal_text' => ($bancoText !== '' ? $bancoText : null),

            'metodos_pago' => $metodos,

            'watermark_text' => "NO ES FACTURA DE VENTA\nDOCUMENTO INFORMATIVO",
            'watermark_font' => 'Calibri',

            'generado_en' => now()->format('Y-m-d H:i:s'),
        ];

        Cache::put($cacheKey, $state, now()->addMinutes($minutes));

        Log::info('✅ RECIBO CACHEADO (medio + banco OK)', [
            'cache_key'   => $cacheKey,
            'medio_id'    => $medioPagoId,
            'medio_text'  => $medioPagoNombre,
            'banco_id'    => $bancoId,
            'banco_text'  => $bancoText,
            'metodos'     => count($metodos),
            'cache_driver'=> config('cache.default'),
        ]);

        return [
            'cache_key' => $cacheKey,
            'url' => url("/admin/facturacion/factura/{$cacheKey}"),
        ];
    }
}
