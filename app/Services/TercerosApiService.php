<?php

namespace App\Services;

use App\Repositories\Interfaces\TercerosRepositoryInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TercerosApiService
{
    protected $repository;

    public function __construct(TercerosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Obtiene un nuevo token desde la API de Yeminus usando variables de entorno.
     */
    protected function fetchTokenFromApi(): string
    {
        try {
            $response = Http::asForm()->post(
                env('DISTRITEC_TOKEN_URL', 'https://distritec.yeminus.com/apidistritec/token'),
                [
                    'username'   => env('DISTRITEC_API_USERNAME'),
                    'password'   => env('DISTRITEC_API_PASSWORD'),
                    'grant_type' => 'password',
                ]
            );

            Log::info('API Yeminus - Token response', [
                'status' => $response->status(),
                'body'   => $response->json()
            ]);

            $response->throw();

            $data = $response->json();

            if (!isset($data['access_token'])) {
                Log::error('API Yeminus - access_token no encontrado', ['response' => $data]);
                throw new Exception('Access token no encontrado en la respuesta de la API.');
            }

            return $data['access_token'];

        } catch (Exception $e) {
            Log::error('API Yeminus - Error al obtener token:', ['exception' => $e]);
            throw new Exception("Error obteniendo token: " . $e->getMessage());
        }
    }

    /**
     * Devuelve token (actualmente siempre nuevo — opcional: implementar caché).
     */
    public function getApiToken(): string
    {
        return $this->fetchTokenFromApi();
    }

    /**
     * Crear tercero en Yeminus
     */
    public function crearTercero(array $datosTercero)
    {
        try {
            $token = $this->getApiToken();

            $body = [
                'nitModel' => array_merge($datosTercero, [
                    'permitirTerceroDuplicado' => true
                ])
            ];

            $response = Http::withHeaders([
                'id_empresa'  => env('DISTRITEC_EMPRESA'),
                'usuario'     => env('DISTRITEC_USUARIO'),
                'Content-Type'=> 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->post(
                'https://distritec.yeminus.com/apidistritec/api/general/terceros/agregar',
                $body
            );

            Log::info('API Yeminus - Crear tercero', [
                'status' => $response->status(),
                'body'   => $response->json(),
                'sent'   => $body
            ]);

            return $response->json();

        } catch (Exception $e) {

            Log::error('TercerosApiService - Error al crear tercero:', [
                'exception' => $e,
                'datos_enviados' => $datosTercero
            ]);

            throw new Exception("Error creando tercero: " . $e->getMessage());
        }
    }


    public function buscarPorCedula($cedula, $tipoDocumento)
    {
        Log::info('TercerosApiService - buscarPorCedula:', [
            'cedula' => $cedula,
            'tipoDocumento' => $tipoDocumento
        ]);

        try {
            return $this->repository->buscarPorCedula($cedula, $tipoDocumento);

        } catch (Exception $e) {
            Log::error('TercerosApiService - Error repo Cedula:', [
                'exception' => $e
            ]);
            throw $e;
        }
    }

    public function buscarTercerosPorQuery(string $query): array
    {
        Log::info('TercerosApiService - buscarTercerosPorQuery:', ['query' => $query]);

        return $this->repository->buscarCoincidencias($query);
    }
}
