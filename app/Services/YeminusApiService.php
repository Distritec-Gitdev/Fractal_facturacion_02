<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YeminusApiService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.api.url');
        $this->apiKey = config('services.api.key');
    }

    /**
     * Crear un nuevo tercero en Yeminus
     */
    public function crearTercero(array $datos)
    {
        try {
            Log::info('YeminusApiService: Intentando crear tercero', ['datos' => $datos]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl, $datos);

            $body = $response->json();

            Log::info('YeminusApiService: Respuesta recibida', [
                'status' => $response->status(),
                'body' => $body
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $body,
                    'message' => 'Tercero creado exitosamente'
                ];
            }

            return [
                'success' => false,
                'error' => $body,
                'message' => 'Error al crear tercero en Yeminus'
            ];

        } catch (\Exception $e) {
            Log::error('YeminusApiService: Error al crear tercero', [
                'error' => $e->getMessage(),
                'datos' => $datos
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error interno del servicio'
            ];
        }
    }

    /**
     * Buscar un tercero existente en Yeminus
     */
    public function buscarTercero(string $cedula)
    {
        try {
            Log::info('YeminusApiService: Buscando tercero', ['cedula' => $cedula]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/buscar', [
                'cedula' => $cedula
            ]);

            $body = $response->json();

            Log::info('YeminusApiService: Respuesta de búsqueda', [
                'status' => $response->status(),
                'body' => $body
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $body,
                    'message' => 'Tercero encontrado'
                ];
            }

            return [
                'success' => false,
                'error' => $body,
                'message' => 'Tercero no encontrado'
            ];

        } catch (\Exception $e) {
            Log::error('YeminusApiService: Error al buscar tercero', [
                'error' => $e->getMessage(),
                'cedula' => $cedula
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error interno del servicio'
            ];
        }
    }
}
