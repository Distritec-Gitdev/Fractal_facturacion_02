<?php

namespace App\Services;

use App\Repositories\Interfaces\TercerosRepositoryInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TercerosApiService
{
    protected $repository;
    protected $tokenPath;
    protected $tokenIncludePath;

    public function __construct(TercerosRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->tokenPath = app_path('conexion_api_yeminus/respuesta.json');
        $this->tokenIncludePath = app_path('conexion_api_yeminus/token_1.php');
    }

    /**
     * Obtiene un nuevo token de la API de Yeminus.
     *
     * @return string El access token.
     * @throws Exception Si no se puede obtener el token.
     */
    protected function fetchTokenFromApi(): string
    {
        try {
            $response = Http::asForm()->post('https://distritec.yeminus.com/apidistritec/token', [
                'username' => 'API', // <<== Considerar usar variables de entorno
                'password' => 'Api2025*', // <<== Considerar usar variables de entorno
                'grant_type' => 'password',
            ]);

            // Loguear la respuesta del token para depuración
            Log::info('Respuesta API Yeminus - Obtener Token:', ['status' => $response->status(), 'body' => $response->json()]);

            $response->throw(); // Lanza una excepción si la respuesta tiene un estado de error (4xx o 5xx)

            $data = $response->json();

            if (isset($data['access_token'])) {
                return $data['access_token'];
            } else {
                Log::error('API Yeminus - Token no encontrado en la respuesta:', ['response' => $data]);
                throw new Exception('Access token no encontrado en la respuesta de la API.');
            }

        } catch (Exception $e) {
            Log::error('API Yeminus - Error al obtener token:', ['exception' => $e]);
            throw new Exception('Error al comunicarse con la API de Yeminus para obtener el token: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene el token de la API. Actualmente siempre obtiene uno nuevo.
     * Se podría añadir lógica de caché para reutilizar tokens válidos.
     *
     * @return string El token de la API.
     * @throws Exception Si no se puede obtener el token.
     */
    protected function getApiToken(): string
    {
        // Por ahora, siempre obtenemos un token nuevo. En producción, se debería añadir caché.
        return $this->fetchTokenFromApi();
    }

    public function crearTercero(array $datosTercero)
    {
        try {
            $token = $this->getApiToken(); // Usar el nuevo método para obtener el token

            // Construir el cuerpo exacto de la solicitud, asegurando permitirTerceroDuplicado es true
            $requestBody = [
                'nitModel' => array_merge($datosTercero, ['permitirTerceroDuplicado' => true]) // Combinar datos y asegurar flag true
            ];

            $response = Http::withHeaders([
                'id_empresa' => '02', // Según Postman
                'usuario' => 'ANALISTACIAL', // Según Postman
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->post('https://distritec.yeminus.com/apidistritec/api/general/terceros/agregar', $requestBody); // Usar el cuerpo construido

            // Loguear la respuesta completa para depuración, incluyendo el cuerpo de la solicitud EXACTO que se envió
            Log::info('Respuesta API Yeminus - Crear Tercero:', [
                'status' => $response->status(),
                'body' => $response->json(),
                'request_body_sent' => $requestBody // Loguear el cuerpo EXACTO enviado
            ]);

            // Devolver la respuesta como un array asociativo
            return $response->json();

        } catch (Exception $e) {
            Log::error('TercerosApiService - Error al crear tercero:', [
                'exception' => $e,
                'datos_enviados_antes_construir_body' => $datosTercero // Loguear el array original por si acaso
            ]);
            // Podrías re-lanzar la excepción o devolver un formato de error específico
            throw new Exception('Error al comunicarse con la API de Yeminus para crear el tercero: ' . $e->getMessage());
        }
    }

    public function buscarPorCedula($cedula, $tipoDocumento)
    {
        Log::info('TercerosApiService - buscarPorCedula recibidos:', ['cedula' => $cedula, 'tipoDocumento' => $tipoDocumento]);

        try {
            $resultadoRepository = $this->repository->buscarPorCedula($cedula, $tipoDocumento);
            Log::info('TercerosApiService - resultado repository:', ['resultado' => $resultadoRepository]);
            return $resultadoRepository;
        } catch (Exception $e) {
            Log::error('TercerosApiService - Error en repositorio:', ['exception' => $e, 'cedula' => $cedula, 'tipoDocumento' => $tipoDocumento]);
            throw $e;
        }
    }
}

