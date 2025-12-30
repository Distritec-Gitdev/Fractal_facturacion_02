<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RecompraController extends Controller
{
    /**
     * Obtener token dinámico desde Yeminus utilizando
     * DISTRITEC_API_USERNAME y DISTRITEC_API_PASSWORD.
     *
     * OPTIMIZACIÓN:
     *  - Cachea el token con Cache::put usando expires_in de la respuesta.
     *  - Mientras el token esté vigente, no vuelve a pedirlo.
     */
    private function obtenerTokenDistritec(bool $forceRefresh = false): ?string
    {
        $cacheKey = 'distritec_recompra_token';

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(env('DISTRITEC_TOKEN_URL'), [
                    'username'   => env('DISTRITEC_API_USERNAME'),
                    'password'   => env('DISTRITEC_API_PASSWORD'),
                    'grant_type' => 'password',
                ]);

            if (config('app.debug')) {
                Log::debug('TOKEN DINÁMICO → RAW', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
            }

            if (!$response->successful()) {
                Log::warning('TOKEN DINÁMICO → respuesta no exitosa', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $accessToken = $response->json('access_token');

            if (! $accessToken) {
                Log::error('TOKEN DINÁMICO → sin access_token en la respuesta');
                return null;
            }

            // TTL desde expires_in, con margen de 60s
            $expiresIn = (int) ($response->json('expires_in') ?? 3600);
            $ttl       = max($expiresIn - 60, 60);

            Cache::put($cacheKey, $accessToken, $ttl);

            return $accessToken;

        } catch (\Throwable $e) {

            Log::error("TOKEN DINÁMICO ERROR: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Retry con límite de intentos SOLO para:
     * 503 + "Máximo de peticiones superado"
     *
     * OPTIMIZACIÓN:
     *  - Antes era ilimitado; ahora por defecto máximo 5 intentos.
     *  - Evita que el request se quede pegado eternamente.
     */
    private function retryIlimitado(callable $callback, int $maxAttempts = 5)
    {
        $i = 1;

        while (true) {
            $response = $callback();

            if ($response->successful()) {
                return $response;
            }

            $body = $response->body() ?? '';

            if (
                $response->status() !== 503 ||
                ! str_contains($body, 'Máximo de peticiones superado')
            ) {
                return $response;
            }

            if ($i >= $maxAttempts) {
                Log::warning("REINTENTO {$i} → Alcanzado máximo de intentos ({$maxAttempts}) para 503 Máximo de peticiones superado.");
                return $response;
            }

            Log::warning("REINTENTO {$i} → Error 503 Máximo de peticiones superado. Reintentando...");

            usleep(350000); // 350 ms
            $i++;
        }
    }

    /**
     * Valida si un documento aplica como recompra.
     */
    public function validar(Request $request)
    {
        $documento = $request->input('documento');

        if (!$documento) {
            return response()->json([
                'ok'      => false,
                'message' => 'El documento es requerido',
            ], 422);
        }

        // ========================
        // Leer config desde services.php
        // ========================
        $url     = config('services.distritec.recompra_url');
        $empresa = config('services.distritec.empresa');
        $usuario = config('services.distritec.usuario');

        // Obtener token dinámico (cacheado)
        $token = $this->obtenerTokenDistritec();

        if (!$token) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo obtener token dinámico.',
            ], 500);
        }

        $headers = [
            'id_empresa' => (string) $empresa,
            'usuario'    => (string) $usuario,
        ];

        $payload = [
            'filtroDocumento' => [
                'filtrar'               => true,
                'tiposDocumentos'       => ['FVP'],
                'fechaInicialCausacion' => null,
                'fechaFinalCausacion'   => null,
                'tercero1'              => (string) $documento,
            ],
        ];

        Log::info('Recompra API REQUEST', [
            'documento' => $documento,
            'payload'   => $payload,
        ]);

        try {
            // Ejecutar con retry (máx. 5 intentos para 503 "Máximo de peticiones superado")
            $response = $this->retryIlimitado(function () use ($headers, $token, $url, $payload) {
                return Http::withHeaders($headers)
                    ->withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout(15)
                    ->post($url, $payload);
            });

        } catch (\Throwable $e) {
            Log::error('Recompra API EXCEPTION', [
                'documento' => $documento,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo conectar con la API de Distritec',
                'error'   => $e->getMessage(),
            ], 500);
        }

        $status = $response->status();
        $body   = $response->json();

        Log::info('Recompra API RAW RESPONSE', [
            'documento' => $documento,
            'status'    => $status,
            'body'      => $body,
        ]);

        if (!$response->successful()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error llamando API de Distritec',
                'status'  => $status,
                'body'    => $response->body(),
            ], $status);
        }

        // ==========================
        // Procesar negocio
        // ==========================
        $esExitoso = data_get($body, 'resultado.esExitoso');

        if ($esExitoso === false) {
            $msg = data_get($body, 'resultado.mensaje')
                ?? data_get($body, 'mensaje')
                ?? 'Error de negocio';

            Log::warning('Recompra API NEGOCIO NO EXITOSO', [
                'documento' => $documento,
                'mensaje'   => $msg,
            ]);

            return response()->json([
                'ok'          => true,
                'documento'   => $documento,
                'monto_total' => 0,
                'es_recompra' => false,
                'detalle'     => [],
                'mensaje'     => $msg,
            ]);
        }

        $documentos = data_get($body, 'resultado.datos.documentos', [])
            ?: data_get($body, 'datos.documentos', []);

        $monto = collect($documentos)->sum(fn ($d) => (float) ($d['precioTotal'] ?? 0));

        $detalle = collect($documentos)->map(fn ($doc) => [
            'documento'     => $doc['documento']           ?? null,
            'fecha'         => $doc['fechaDocumento']      ?? null,
            'precioTotal'   => $doc['precioTotal']         ?? 0,
            'precioNeto'    => $doc['precioNeto']          ?? 0,
            'tipoDocumento' => $doc['tipoDocumento']       ?? null,
            'descripcion'   => $doc['descripcionProducto'] ?? null,
        ]);

        $respuesta = [
            'ok'          => true,
            'documento'   => $documento,
            'monto_total' => $monto,
            'es_recompra' => $monto >= 500000,
            'detalle'     => $detalle,
        ];

        Log::info('Recompra API PROCESADA', [
            'documento' => $documento,
            'body'      => $respuesta,
        ]);

        return response()->json($respuesta);
    }
}
