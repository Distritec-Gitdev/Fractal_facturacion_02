<?php

namespace App\Repositories;

use App\Repositories\Interfaces\TercerosRepositoryInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class TercerosApiRepository implements TercerosRepositoryInterface
{
    protected $config;
    protected $headers;

    // Constantes para el manejo del token en caché
    protected const TOKEN_CACHE_KEY = 'terceros_api_token';
    protected const TOKEN_TTL_SECONDS = 23 * 60 * 60; // 23 horas

    public function __construct()
    {
        $this->config = config('terceros.api') ?? [];
        
        if (empty($this->config)) {
            throw new Exception('La configuración de terceros.api no está definida');
        }

        // Encabezados fijos, el token se añadirá dinámicamente
        $this->headers = [
            'id_empresa' => $this->config['empresa'] ?? '02',
            'Usuario' => $this->config['usuario'] ?? 'API',
            'Content-Type' => 'application/json', // Content type para las llamadas a terceros
        ];
         Log::info('TercerosRepository - Constructor llamado. Headers base:', $this->headers);
    }

    /**
     * Obtiene el token de la API de terceros, desde caché o solicitando uno nuevo.
     *
     * @return string El token de acceso.
     * @throws Exception Si no se puede obtener el token.
     */
    public function getApiToken(): string
    {
        // Intentar obtener el token de la caché
        $token = Cache::get(self::TOKEN_CACHE_KEY);

        if (!$token) {
            Log::info('TercerosRepository - Token no encontrado en caché. Solicitando nuevo token...');
            // Si no está en caché, solicitar uno nuevo y almacenarlo
            $token = $this->fetchAndCacheToken();
            if (!$token) {
                throw new Exception('No se pudo obtener el token de la API de terceros.');
            }
             Log::info('TercerosRepository - Nuevo token obtenido y almacenado en caché.');
        }

        return $token;
    }

    /**
     * Solicita un nuevo token a la API de seguridad y lo almacena en caché.
     *
     * @return string|null El token de acceso, o null si falla.
     */
    protected function fetchAndCacheToken(): ?string
    {
        try {
            $tokenApiConfig = $this->config['token_api'] ?? [];

            if (empty($tokenApiConfig) || !isset($tokenApiConfig['url'], $tokenApiConfig['username'], $tokenApiConfig['password'])) {
                Log::error('TercerosRepository - Configuración de token_api incompleta o faltante.');
                throw new Exception('Configuración de token_api incompleta.');
            }

            $url = $tokenApiConfig['url'];
            $username = $tokenApiConfig['username'];
            $password = $tokenApiConfig['password'];

            $response = Http::asForm()->post($url, [
                'userName' => $username,
                'password' => $password,
                'grant_type' => 'password',
            ]);

            Log::info('TercerosRepository - Respuesta raw de API de token:', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['access_token'])) {
                    $token = $data['access_token'];
                    // Almacenar el token en caché con el TTL definido
                    Cache::put(self::TOKEN_CACHE_KEY, $token, self::TOKEN_TTL_SECONDS);
                    return $token;
                } else {
                    Log::error('TercerosRepository - Respuesta de token no contiene access_token.', ['body' => $data]);
                    return null;
                }
            } else {
                Log::error('TercerosRepository - Error al solicitar token de API:', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }
        } catch (Exception $e) {
            $this->logError('Error al obtener y cachear token', $e);
            return null;
        }
    }

    public function buscarPorCedula(string $cedula, string $tipoDocumento)
    {
        $cacheKey = config('terceros.cache.prefix') . "cedula_{$cedula}_{$tipoDocumento}";

        return Cache::remember($cacheKey, config('terceros.cache.ttl'), function () use ($cedula, $tipoDocumento) {
            return $this->realizarConsultaAPI($cedula, $tipoDocumento);
        });
    }

    // CAMBIO 1: firma y llamada desde realizarConsultaAPI()
protected function realizarConsultaAPI(string $cedula, string $tipoDocumento)
{
    try {
        $token = $this->getApiToken();
        if (!$token) {
             throw new Exception('No se pudo obtener el token de autenticación para la API.');
        }

        Log::info('Token obtenido exitosamente:', ['token' => substr($token, 0, 20) . '...']);

        $url = $this->config['url'];
        $requestBody = [
            'filtrar' => true,
            'buscarResponsables' => false,
            'activos' => true,
            'inactivos' => false,
            'relacion' => ['C', 'P'],
            'busquedaRapida' => $cedula,
            'listaEstados' => ['A']
        ];

        $headersWithToken = $this->headers;
        $headersWithToken['Authorization'] = 'Bearer ' . $token;

        Log::info('TercerosRepository - Consulta API request:', [
            'url' => $url,
            'headers' => array_merge($headersWithToken, ['Authorization' => 'Bearer [TOKEN]']),
            'body' => $requestBody,
            'cedula' => $cedula,
            'tipoDocumento' => $tipoDocumento
        ]);

        $response = Http::timeout(60)
            ->retry(3, 2000)
            ->withHeaders($headersWithToken)
            ->post($url, $requestBody);

        Log::info('TercerosRepository - Respuesta raw de API:', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        // ⬇️ AQUÍ: pasar $cedula y $tipoDocumento
        return $this->procesarRespuesta($response, $cedula, $tipoDocumento);

    } catch (Exception $e) {
        $this->logError('Error en consulta API', $e, [
            'cedula' => $cedula,
            'tipoDocumento' => $tipoDocumento
        ]);
        throw $e;
    }
}


   // CAMBIO 2: firma + lógica de filtrado exacto en procesarRespuesta()
protected function procesarRespuesta($response, string $cedula, string $tipoDocumento = null)
{
    Log::info('TercerosRepository - Procesando respuesta:', ['status' => $response->status()]);

    if ($response->failed()) {
        Log::error('TercerosRepository - Respuesta fallida de API', ['status' => $response->status(), 'body' => $response->body()]);
        throw new Exception('Error en la respuesta: ' . $response->status());
    }

    $data = $response->json();
    Log::info('TercerosRepository - Respuesta JSON de API:', ['data' => $data]);

    if (!isset($data['datos']) || !isset($data['datos']['nits'])) {
        Log::warning('TercerosRepository - Estructura de respuesta inesperada', ['data' => $data]);
        return null;
    }

    $nits = $data['datos']['nits'];
    if (empty($nits)) {
        Log::info('TercerosRepository - No se encontraron terceros en la respuesta');
        return null;
    }

    // Normalizamos cédula y (opcional) tipo
    $needleDoc = $this->normalizarDocumento($cedula);
   $needleTipo = (isset($tipoDocumento) && trim((string)$tipoDocumento) !== '')
    ? mb_strtoupper(trim((string)$tipoDocumento))
    : null;

    // Filtrar coincidencia exacta por número de documento
    $exactMatches = array_values(array_filter($nits, function ($row) use ($needleDoc, $needleTipo) {
        $doc = $this->extraerNumeroDocumento($row);
        if ($doc === null) return false;

        $matchNumero = $this->normalizarDocumento($doc) === $needleDoc;

        // Si el usuario pasó tipoDocumento, tratar de matchear también (si el dato existe en el row)
        if ($needleTipo !== null) {
            $tipo = $this->extraerTipoDocumento($row);
            if ($tipo !== null) {
                return $matchNumero && (mb_strtoupper(trim($tipo)) === $needleTipo);
            }
            // Si no hay tipo en el row, dejamos que el match por número sea suficiente
        }

        return $matchNumero;
    }));

    if (!empty($exactMatches)) {
        $tercero = $exactMatches[0]; // debería ser único
        Log::info('TercerosRepository - Tercero con coincidencia exacta encontrado:', ['tercero' => $tercero]);
        return $tercero;
    }

    // Si no hay coincidencia exacta, devolvemos null (ya NO devolvemos el primero)
    Log::info('TercerosRepository - Sin coincidencia exacta para la cédula solicitada', [
        'cedula_buscada' => $cedula,
        'tipoDocumento_buscado' => $tipoDocumento
    ]);
    return null;
}


// CAMBIO 3: helpers pequeños (mismo class)
protected function normalizarDocumento($valor): string
{
    // quita todo lo que no sea dígito (soporta cédulas con separadores)
    return preg_replace('/\D+/', '', (string) $valor) ?? '';
}

protected function extraerNumeroDocumento(array $row): ?string
{
    // posibles nombres de campo según APIs comunes
    foreach (['numeroDocumento', 'documento', 'identificacion', 'nit', 'nroDocumento'] as $k) {
        if (isset($row[$k]) && $row[$k] !== '') {
            return (string) $row[$k];
        }
    }
    return null;
}

protected function extraerTipoDocumento(array $row): ?string
{
    // Prioriza campos que realmente representan el tipo de identificación del documento
    foreach ([
        'tipoIdentificacionTributaria', // ← este es el que trae "13" en tu payload
        'tipoDocumento',
        'tipoId',
        'idTipoDocumento',
        // 'tipoNit',  // ← NO usar: no es el tipo de documento, rompe el match
    ] as $k) {
        if (isset($row[$k]) && $row[$k] !== '') {
            return (string) $row[$k];
        }
    }
    return null;
}




    protected function logError(string $mensaje, Exception $e, array $contexto = [])
    {
        Log::error($mensaje, array_merge($contexto, [
            'exception_message' => $e->getMessage(),
            'exception_trace' => $e->getTraceAsString()
        ]));
    }

    public function crearTercero(array $datos)
    {
        try {
            $token = $this->getApiToken();
            if (!$token) {
                throw new Exception('No se pudo obtener el token de autenticación para la API.');
            }

            $url = $this->config['crear_url']; // Asumiendo que hay una URL específica para crear
            $requestBody = [
                'nitModel' => array_merge($datos, ['permitirTerceroDuplicado' => true])
            ];

            $headersWithToken = $this->headers;
            $headersWithToken['Authorization'] = 'Bearer ' . $token;

            Log::info('TercerosRepository - Crear Tercero request:', [
                'url' => $url,
                'headers' => $headersWithToken,
                'body' => $requestBody
            ]);

            $response = Http::withHeaders($headersWithToken)
                ->post($url, $requestBody);

            Log::info('TercerosRepository - Crear Tercero raw response:', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new Exception('Error al crear tercero: ' . ($response->json()['mensaje'] ?? 'Error desconocido'));
            }
        } catch (Exception $e) {
            $this->logError('Error al crear tercero en API', $e, [
                'datos' => $datos
            ]);
            throw $e;
        }
    }
}