<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ValidacionCarteraService
{
    /**
     * Consulta la API de cartera de Distritec y analiza la situación del cliente
     * según los días de atraso y reglas del flujo de validación.
     */
    public function analizarCarteraPorDocumento(string $documento): array
    {
        try {
            // ================================
            // CONFIGURACIÓN GENERAL DE LA API
            // ================================
            $token = config('services.distritec.token'); // tomado del .env
            $url   = 'https://distritec.yeminus.com/apidistritec/api/ingresos/carteraclientes/seguimientocarterafiltros';
            $fecha = now()->toIso8601String();

            $payload = [
                "filtroCartera" => [
                    "codigoCliente" => (int) $documento,
                    "idEmpresa"     => "02",
                    "usuario"       => "API",
                ],
                "filtroTerceros" => null,
                "fecha"          => $fecha,
            ];

            Log::info('[ValidacionCartera] Consultando cartera de cliente', [
                'documento' => $documento,
            ]);

            // ================================
            // CONSULTA HTTP
            // ================================
            $response = Http::withToken($token)
                ->withHeaders([
                    'id_empresa' => '02',
                    'usuario'    => 'API',
                ])
                ->post($url, $payload);

            if (! $response->ok()) {
                Log::warning('[ValidacionCartera] Error en respuesta', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return [
                    'ok'      => false,
                    'bloquea' => false,
                    'mensaje' => 'No se pudo validar la cartera del cliente (error de conexión).',
                ];
            }

            $data = $response->json();

            // ================================
            // PROCESAMIENTO DE DATOS
            // ================================
            $documentos = $data['datos'] ?? $data['detalle'] ?? [];

            if (empty($documentos)) {
                return [
                    'ok'      => true,
                    'bloquea' => false,
                    'mensaje' => 'Cliente sin registros de cartera.',
                ];
            }

            $maxDiasAtraso = 0;
            $totalVencidas = 0;
            $saldoVencido  = 0;

            foreach ($documentos as $factura) {
                // algunos campos pueden variar según la respuesta del API
                $saldo = $factura['saldoPendiente'] ?? $factura['Saldo'] ?? 0;
                $fechaVenc = $factura['fechaVencimiento'] ?? $factura['FechaVencimiento'] ?? null;

                if (! $fechaVenc || $saldo <= 0) {
                    continue;
                }

                $fechaVencimiento = Carbon::parse($fechaVenc);
                $diasAtraso = now()->diffInDays($fechaVencimiento, false) * -1;

                if ($diasAtraso > 0) {
                    $totalVencidas++;
                    $saldoVencido += $saldo;
                    if ($diasAtraso > $maxDiasAtraso) {
                        $maxDiasAtraso = $diasAtraso;
                    }
                }
            }

            // ================================
            // APLICACIÓN DE REGLAS DEL FLUJO
            // ================================
            // Ajusta aquí según tu diagrama:
            // Ejemplo:
            // - Si tiene más de 60 días de atraso → BLOQUEO
            // - Si tiene entre 31 y 60 días → ALERTA ROJA (requiere autorización)
            // - Si tiene entre 1 y 30 días → ALERTA AMARILLA (pago parcial)
            // - Si no tiene atraso → OK

            if ($maxDiasAtraso > 60) {
                return [
                    'ok'       => true,
                    'bloquea'  => true,
                    'mensaje'  => "El cliente tiene {$maxDiasAtraso} días de atraso y saldo vencido de $" . number_format($saldoVencido, 0, ',', '.') . ". No se permite venta a crédito.",
                ];
            }

            if ($maxDiasAtraso > 30) {
                return [
                    'ok'       => true,
                    'bloquea'  => false,
                    'mensaje'  => "El cliente presenta {$maxDiasAtraso} días de atraso. Debe obtener autorización antes de realizar venta a crédito.",
                ];
            }

            if ($maxDiasAtraso > 0) {
                return [
                    'ok'       => true,
                    'bloquea'  => false,
                    'mensaje'  => "El cliente presenta {$maxDiasAtraso} días de atraso. Se recomienda pago parcial antes de continuar.",
                ];
            }

            // Sin atraso → permitido
            return [
                'ok'       => true,
                'bloquea'  => false,
                'mensaje'  => 'Cliente al día, autorizado para venta a crédito.',
            ];

        } catch (\Throwable $e) {
            Log::error('[ValidacionCartera] Excepción', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'ok'      => false,
                'bloquea' => false,
                'mensaje' => 'Error interno al procesar la validación de cartera.',
            ];
        }
    }
}