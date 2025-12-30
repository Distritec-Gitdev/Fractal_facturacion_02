<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\ConsignacionService;
use Illuminate\Support\Facades\Storage;

class CarteraController extends Controller
{
    /**
     * Aquí obtengo un token dinámico desde Yeminus.
     * Uso las variables de entorno:
     *  - DISTRITEC_TOKEN_URL
     *  - DISTRITEC_API_USERNAME
     *  - DISTRITEC_API_PASSWORD
     *
     * Optimización:
     *  - Cachea el token en memoria usando Cache::put
     *  - Respeta el expires_in de la respuesta (con margen de 60s)
     */
    private function getDynamicToken(bool $forceRefresh = false): ?string
    {
        $cacheKey = 'yeminus_dynamic_token';

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $url      = env('DISTRITEC_TOKEN_URL');
        $username = env('DISTRITEC_API_USERNAME');
        $password = env('DISTRITEC_API_PASSWORD');

        if (! $url || ! $username || ! $password) {
            Log::error('TOKEN DINÁMICO → Faltan variables de entorno para pedir el token', [
                'url_null'      => empty($url),
                'username_null' => empty($username),
                'password_null' => empty($password),
            ]);

            return null;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post($url, [
                    'username'   => $username,
                    'password'   => $password,
                    'grant_type' => 'password',
                ]);

            if (config('app.debug')) {
                Log::info('TOKEN DINÁMICO → RESPONSE', [
                    'status' => $response->status(),
                ]);
            }

            if (! $response->successful()) {
                Log::error('TOKEN DINÁMICO → No se pudo obtener el token (status no exitoso)', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return null;
            }

            $accessToken = $response->json('access_token');

            if (! $accessToken) {
                Log::error('TOKEN DINÁMICO → La respuesta no contiene access_token');
                return null;
            }

            // Tiempo de vida del token (segundos). Si no viene, uso 1 hora por defecto.
            $expiresIn = (int) ($response->json('expires_in') ?? 3600);
            // Le resto 60 segundos para evitar usarlo justo al expirar.
            $ttl = max($expiresIn - 60, 60);

            Cache::put($cacheKey, $accessToken, $ttl);

            return $accessToken;

        } catch (\Throwable $e) {
            Log::error('TOKEN DINÁMICO → EXCEPCIÓN', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Envío HTTP con reintentos controlados.
     *
     * - Reintenta cuando:
     *      - status == 503 y el body contiene "Máximo de peticiones superado"
     *      - o la excepción contiene ese mismo texto
     * - Timeout reducido a 15s.
     * - Optimización: límite de intentos ($maxAttempts) para no colgar el request.
     */
    private function sendWithRetries(
        string $method,
        string $url,
        array $headers,
        array $payload = [],
        int $maxAttempts = 5
    ) {
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                if (config('app.debug')) {
                    Log::info('sendWithRetries() → Llamando API', [
                        'method'  => $method,
                        'url'     => $url,
                        'attempt' => $attempt,
                    ]);
                }

                $response = Http::withHeaders($headers)
                    ->timeout(15)
                    ->{$method}($url, $payload);

                if (
                    $response->status() === 503 &&
                    str_contains($response->body(), 'Máximo de peticiones superado')
                ) {
                    if (config('app.debug')) {
                        Log::warning('sendWithRetries() → 503 Máximo de peticiones superado, reintento…', [
                            'status'  => $response->status(),
                            'attempt' => $attempt,
                        ]);
                    }

                    usleep(600 * 1000); // 600 ms
                    continue;
                }

                return $response;

            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'Máximo de peticiones superado')) {
                    if (config('app.debug')) {
                        Log::warning('sendWithRetries() → EXCEPCIÓN de máximo peticiones, reintento…', [
                            'error'   => $e->getMessage(),
                            'attempt' => $attempt,
                        ]);
                    }

                    usleep(600 * 1000); // 600 ms
                    continue;
                }

                Log::error('sendWithRetries() → EXCEPCIÓN inesperada', [
                    'error'   => $e->getMessage(),
                    'attempt' => $attempt,
                ]);

                throw $e;
            }
        }

        // Si llego aquí, me pasé del número máximo de intentos
        throw new \RuntimeException("sendWithRetries() → Máximo de intentos ({$maxAttempts}) alcanzado para {$url}");
    }

    /**
     * API 1 — Consulta básica del cliente en Yeminus.
     */
    public function diasAtraso(Request $request)
    {
        $nit = $request->input('nit');

        if (! $nit) {
            return response()->json([
                'success' => false,
                'error'   => 'Nit requerido',
            ], 400);
        }

        $service           = new ConsignacionService();
        $diasConsignacion  = $service->calcularDiasConsignacion($nit);
        $valorConsignacion = $service->calcularValorTotalConsignacionActiva($nit);

        $headers = [
            'Content-Type' => 'application/json',
            'id_empresa'   => env('DISTRITEC_EMPRESA'),
            'usuario'      => env('DISTRITEC_USUARIO'),
        ];

        $token = $this->getDynamicToken();

        if (! $token) {
            Log::error('diasAtraso() → No se pudo obtener token dinámico');

            return [
                'cupo_credito'                 => null,
                'control_cupo_credito'         => null,
                'dias_maximo_atraso_permitido' => null,
                'dias_consignacion'            => $diasConsignacion,
                'valor_total_consignacion'     => $valorConsignacion,
            ];
        }

        $headers['Authorization'] = "Bearer {$token}";

        try {
            $url = "https://distritec.yeminus.com/apidistritec/api/general/terceros/obtener";

            if (config('app.debug')) {
                Log::info('API DIAS ATRASO → REQUEST', [
                    'url' => $url,
                    'nit' => $nit,
                ]);
            }

            $response = $this->sendWithRetries('post', $url, $headers, [
                'nit' => (int) $nit,
            ]);

            if (config('app.debug')) {
                Log::debug('API DIAS ATRASO → RESPONSE', [
                    'status' => $response->status(),
                ]);
            }

            if (! $response->successful()) {
                Log::warning('API DIAS ATRASO → NO EXITOSA', [
                    'status' => $response->status(),
                ]);

                return [
                    'cupo_credito'                 => null,
                    'control_cupo_credito'         => null,
                    'dias_maximo_atraso_permitido' => null,
                    'dias_consignacion'            => $diasConsignacion,
                    'valor_total_consignacion'     => $valorConsignacion,
                ];
            }

            $json = $response->json();

            if (data_get($json, 'esExitoso') !== true || ! is_array(data_get($json, 'datos'))) {
                Log::warning('API DIAS ATRASO → esExitoso = false o datos inválidos');

                return [
                    'cupo_credito'                 => null,
                    'control_cupo_credito'         => null,
                    'dias_maximo_atraso_permitido' => null,
                    'dias_consignacion'            => $diasConsignacion,
                    'valor_total_consignacion'     => $valorConsignacion,
                ];
            }

            $datos = data_get($json, 'datos', []);

            $cupoCredito   = data_get($datos, 'cupoCredito');
            $controlCupo   = data_get($datos, 'controlCupoCredito');
            $diasMaxPermit = data_get($datos, 'diasMaximoAtrasoCartera');

            return [
                'cupo_credito'                 => $cupoCredito,
                'control_cupo_credito'         => $controlCupo,
                'dias_maximo_atraso_permitido' => $diasMaxPermit,
                'dias_consignacion'            => $diasConsignacion,
                'valor_total_consignacion'     => $valorConsignacion,
            ];

        } catch (\Throwable $e) {
            Log::error('API DIAS ATRASO ERROR', [
                'nit'   => $nit,
                'error' => $e->getMessage(),
            ]);

            return [
                'cupo_credito'                 => null,
                'control_cupo_credito'         => null,
                'dias_maximo_atraso_permitido' => null,
                'dias_consignacion'            => $diasConsignacion,
                'valor_total_consignacion'     => $valorConsignacion,
            ];
        }
    }




    /**
     * API 2 — Seguimiento de cartera (flujo completo).
     *
     * ⚠️ Optimización:
     *  - Primero valida CUPO y DÍAS DE ATRASO.
     *  - Solo si pasa esas validaciones, calcula CONSIGNACIÓN.
     *  - Usa token cacheado gracias a getDynamicToken().
     */
    public function seguimiento(Request $request)
    {
        $nit = $request->input('nit');

        if (! $nit) {
            return response()->json([
                'success' => false,
                'error'   => 'Nit requerido',
            ], 400);
        }

        // Por defecto NO calculamos consignación.
        // Solo se llenan cuando el cliente pasa cupo + días de atraso.
        $diasConsignacion  = 0;
        $valorConsignacion = 0.0;

        // --- Paso 1: obtener info básica (cupo / control de cupo / días permitidos) --- //
        $infoTerceroResp = $this->diasAtraso(new Request(['nit' => $nit]));

        if ($infoTerceroResp instanceof \Illuminate\Http\JsonResponse) {
            $infoTercero = $infoTerceroResp->getData(true);
        } else {
            $infoTercero = $infoTerceroResp;
        }

        $cupoCredito   = data_get($infoTercero, 'cupo_credito');
        $controlCupo   = data_get($infoTercero, 'control_cupo_credito');
        $diasMaxPermit = data_get($infoTercero, 'dias_maximo_atraso_permitido');

        $cupoCreditoNum   = (float) ($cupoCredito ?? 0);
        $controlCupoNum   = is_null($controlCupo) ? null : (float) $controlCupo;
        $diasMaxPermitNum = (float) ($diasMaxPermit ?? 0);

        $maxDiasAtraso         = 0;
        $saldoTotal            = 0.0;
        $saldoDisponible       = $cupoCreditoNum - $saldoTotal;
        $resultadoConsignacion = $saldoDisponible - $valorConsignacion;

        // --- Paso 2: sin control de cupo → NO seguimos y NO calculamos consignación --- //
        if (is_null($controlCupo)) {
            $resumen =
                "Control de cupo: (sin datos / null)\n" .
                "Cupo: {$cupoCredito}\n" .
                "Saldo total cartera: {$saldoTotal}\n" .
                "Disponible (cupo - cartera): {$saldoDisponible}\n" .
                "Max días atraso: {$maxDiasAtraso}\n" .
                "Días de atraso permitidos: {$diasMaxPermitNum}\n" .
                "Observación: el cliente NO tiene cupo configurado (control_cupo_credito es null).";

            if (config('app.debug')) {
                Log::info('API SEGUIMIENTO → RESUMEN (sin control de cupo, flujo detenido)', [
                    'nit' => (string) $nit,
                ]);
            }

            return [
                'success'                      => true,
                'nit'                          => (string) $nit,
                'cupo_credito'                 => $cupoCredito,
                'control_cupo_credito'         => $controlCupo,
                'dias_maximo_atraso_permitido' => $diasMaxPermitNum,
                'max_dias_atraso_factura'      => $maxDiasAtraso,
                'saldo_total_cartera'          => $saldoTotal,
                'saldo_disponible'             => $saldoDisponible,
                'dias_consignacion'            => $diasConsignacion,
                'valor_total_consignacion'     => $valorConsignacion,
                'resultado_consignacion'       => $resultadoConsignacion,
                'alerta' => [
                    'codigo'  => 'SIN_CONTROL_CUPO',
                    'mensaje' => 'El cliente no tiene cupo configurado (control_cupo_credito es null).',
                ],
                'resumen'  => $resumen,
                'facturas' => [],
            ];
        }

        // --- Paso 3: control de cupo distinto de 1 → NO seguimos y NO calculamos consignación --- //
        if ($controlCupoNum !== 1.0) {
            $saldoDisponible       = $cupoCreditoNum - $saldoTotal;
            $resultadoConsignacion = $saldoDisponible - $valorConsignacion;

            $resumen =
                "Control de cupo: {$controlCupo}\n" .
                "Cupo: {$cupoCredito}\n" .
                "Saldo total cartera: {$saldoTotal}\n" .
                "Disponible (cupo - cartera): {$saldoDisponible}\n" .
                "Max días atraso: {$maxDiasAtraso}\n" .
                "Días de atraso permitidos: {$diasMaxPermitNum}\n" .
                "Observación: el control de cupo no es igual a 1, por lo tanto no debo consultar seguimiento de cartera.";

            if (config('app.debug')) {
                Log::info('API SEGUIMIENTO → RESUMEN (control de cupo != 1, flujo detenido)', [
                    'nit' => (string) $nit,
                ]);
            }

            return [
                'success'                      => true,
                'nit'                          => (string) $nit,
                'cupo_credito'                 => $cupoCredito,
                'control_cupo_credito'         => $controlCupo,
                'dias_maximo_atraso_permitido' => $diasMaxPermitNum,
                'max_dias_atraso_factura'      => $maxDiasAtraso,
                'saldo_total_cartera'          => $saldoTotal,
                'saldo_disponible'             => $saldoDisponible,
                'dias_consignacion'            => $diasConsignacion,
                'valor_total_consignacion'     => $valorConsignacion,
                'resultado_consignacion'       => $resultadoConsignacion,
                'alerta' => [
                    'codigo'  => 'SIN_CUPO',
                    'mensaje' => 'El cliente no tiene control de cupo activo (control_cupo_credito != 1).',
                ],
                'resumen'  => $resumen,
                'facturas' => [],
            ];
        }

        // --- Si llego aquí, el control de cupo es 1 → seguimos con seguimiento de cartera --- //

        $headers = [
            'Content-Type' => 'application/json',
            'id_empresa'   => env('DISTRITEC_EMPRESA'),
            'usuario'      => env('DISTRITEC_USUARIO'),
        ];

        $token = $this->getDynamicToken();

        if (! $token) {
            $saldoDisponible       = $cupoCreditoNum - $saldoTotal;
            $resultadoConsignacion = $saldoDisponible - $valorConsignacion;

            $resumen  = "No se pudo obtener token dinámico.\n";
            $resumen .= "Cupo: {$cupoCredito}\n";
            $resumen .= "Control de cupo: {$controlCupo}\n";
            $resumen .= "Saldo total cartera: {$saldoTotal}\n";
            $resumen .= "Disponible (cupo - cartera): {$saldoDisponible}\n";
            $resumen .= "Max días atraso: {$maxDiasAtraso}\n";
            $resumen .= "Días de atraso permitidos: {$diasMaxPermitNum}";

            return [
                'success'                      => false,
                'error'                        => 'No se pudo obtener token dinámico',
                'nit'                          => (string) $nit,
                'cupo_credito'                 => $cupoCredito,
                'control_cupo_credito'         => $controlCupo,
                'dias_maximo_atraso_permitido' => $diasMaxPermitNum,
                'max_dias_atraso_factura'      => $maxDiasAtraso,
                'saldo_total_cartera'          => $saldoTotal,
                'saldo_disponible'             => $saldoDisponible,
                'dias_consignacion'            => $diasConsignacion,
                'valor_total_consignacion'     => $valorConsignacion,
                'resultado_consignacion'       => $resultadoConsignacion,
                'resumen'                      => $resumen,
                'facturas'                     => [],
            ];
        }

        $headers['Authorization'] = "Bearer {$token}";

        try {
            $urlSeguimiento = "https://distritec.yeminus.com/apidistritec/api/ingresos/carteraclientes/seguimientocarterafiltros";

            $payload = [
                "filtroCXC" => [
                    "fechaDeCorte"                     => now()->toISOString(),
                    "diasAtrasoInicio"                 => null,
                    "diasAtrasoFin"                    => null,
                    "ciudad"                           => null,
                    "zona"                             => null,
                    "codigoCliente"                    => (int) $nit,
                    "nit"                              => null,
                    "vendedor"                         => null,
                    "formaDePago"                      => null,
                    "cuentaPorCobrar"                  => null,
                    "prefijo"                          => null,
                    "nitCouta"                         => null,
                    "cliente2"                         => null,
                    "cliente3"                         => null,
                    "centrosDeCosto"                   => null,
                    "gruposCC"                         => null,
                    "tiposDocumentos"                  => null,
                    "caja"                             => null,
                    "estados"                          => null,
                    "numeroRadicado"                   => 0,
                    "consultarInformacionGlosas"       => null,
                    "destino"                          => null,
                    "fechaFacturaIni"                  => null,
                    "fechaFacturaFin"                  => null,
                    "fechaInicialNotificacionCartera"  => null,
                    "fechaFinalNotificacionCartera"    => null,
                    "fechaInicialUltimoPago"           => null,
                    "fechaFinalUltimoPago"             => null,
                    "fechaInicialCompromisoDePago"     => null,
                    "fechaFinalCompromisoDePago"       => null,
                ],
                "filtroTerceros" => null,
                "fecha"          => now()->toISOString(),
            ];

            if (config('app.debug')) {
                Log::info('API SEGUIMIENTO → REQUEST', [
                    'url'     => $urlSeguimiento,
                    'payload' => ['nit' => (int) $nit],
                ]);
            }

            $respSeguimiento = $this->sendWithRetries('post', $urlSeguimiento, $headers, $payload);

            if (config('app.debug')) {
                Log::debug('API SEGUIMIENTO → RESPONSE', [
                    'status' => $respSeguimiento->status(),
                ]);
            }

            if (! $respSeguimiento->successful()) {
                $saldoDisponible       = $cupoCreditoNum - $saldoTotal;
                $resultadoConsignacion = $saldoDisponible - $valorConsignacion;

                $resumen  = "No se pudo obtener la información de cartera desde el servidor externo.\n";
                $resumen .= "Cupo: {$cupoCredito}\n";
                $resumen .= "Control de cupo: {$controlCupo}\n";
                $resumen .= "Saldo total cartera: {$saldoTotal}\n";
                $resumen .= "Disponible (cupo - cartera): {$saldoDisponible}\n";
                $resumen .= "Max días atraso: {$maxDiasAtraso}\n";
                $resumen .= "Días de atraso permitidos: {$diasMaxPermitNum}";

                return [
                    'success'                      => true,
                    'nit'                          => (string) $nit,
                    'cupo_credito'                 => $cupoCredito,
                    'control_cupo_credito'         => $controlCupo,
                    'dias_maximo_atraso_permitido' => $diasMaxPermitNum,
                    'max_dias_atraso_factura'      => $maxDiasAtraso,
                    'saldo_total_cartera'          => $saldoTotal,
                    'saldo_disponible'             => $saldoDisponible,
                    'dias_consignacion'            => $diasConsignacion,
                    'valor_total_consignacion'     => $valorConsignacion,
                    'resultado_consignacion'       => $resultadoConsignacion,
                    'alerta' => [
                        'codigo'  => 'ERROR_SEGUIMIENTO',
                        'mensaje' => 'No se pudo obtener información de cartera (error de conexión o autorización).',
                    ],
                    'resumen'  => $resumen,
                    'facturas' => [],
                ];
            }

            $jsonSeguimiento = $respSeguimiento->json();

            if (data_get($jsonSeguimiento, 'esExitoso') !== true || ! is_array(data_get($jsonSeguimiento, 'datos'))) {
                $saldoDisponible       = $cupoCreditoNum - $saldoTotal;
                $resultadoConsignacion = $saldoDisponible - $valorConsignacion;

                $resumen  = "No se pudo obtener la información de seguimiento de cartera.\n";
                $resumen .= "Cupo: {$cupoCredito}\n";
                $resumen .= "Control de cupo: {$controlCupo}\n";
                $resumen .= "Saldo total cartera: {$saldoTotal}\n";
                $resumen .= "Disponible (cupo - cartera): {$saldoDisponible}\n";
                $resumen .= "Max días atraso: {$maxDiasAtraso}\n";
                $resumen .= "Días de atraso permitidos: {$diasMaxPermitNum}";

                return [
                    'success'                      => true,
                    'nit'                          => (string) $nit,
                    'cupo_credito'                 => $cupoCredito,
                    'control_cupo_credito'         => $controlCupo,
                    'dias_maximo_atraso_permitido' => $diasMaxPermitNum,
                    'max_dias_atraso_factura'      => $maxDiasAtraso,
                    'saldo_total_cartera'          => $saldoTotal,
                    'saldo_disponible'             => $saldoDisponible,
                    'dias_consignacion'            => $diasConsignacion,
                    'valor_total_consignacion'     => $valorConsignacion,
                    'resultado_consignacion'       => $resultadoConsignacion,
                    'alerta' => [
                        'codigo'  => 'ERROR_SEGUIMIENTO',
                        'mensaje' => 'No se pudo obtener información de seguimiento de cartera.',
                    ],
                    'resumen'  => $resumen,
                    'facturas' => [],
                ];
            }

            $facturas = data_get($jsonSeguimiento, 'datos', []);
            if (! is_array($facturas)) {
                $facturas = [];
            }

            $maxDiasAtraso = 0;
            $saldoTotal    = 0.0;

            foreach ($facturas as $factura) {
                $diasFactura  = data_get($factura, 'diasAtraso', data_get($factura, 'diasVencidos', 0));
                $saldoFactura = data_get($factura, 'saldo', data_get($factura, 'saldoTotal', 0));

                $diasFacturaInt  = (int) ($diasFactura ?? 0);
                $saldoFacturaNum = (float) ($saldoFactura ?? 0);

                if ($diasFacturaInt > $maxDiasAtraso) {
                    $maxDiasAtraso = $diasFacturaInt;
                }

                $saldoTotal += $saldoFacturaNum;
            }

            $saldoDisponible       = $cupoCreditoNum - $saldoTotal;
            $resultadoConsignacion = $saldoDisponible - $valorConsignacion;

            // --- Paso 6: VALIDACIÓN DE DÍAS DE ATRASO ---
            if ($diasMaxPermitNum > 0 && $maxDiasAtraso >= $diasMaxPermitNum) {
                $resumen =
                    "Control de cupo: {$controlCupo}\n" .
                    "Cupo: {$cupoCredito}\n" .
                    "Saldo total cartera: {$saldoTotal}\n" .
                    "Disponible (cupo - cartera): {$saldoDisponible}\n" .
                    "Max días atraso: {$maxDiasAtraso}\n" .
                    "Días de atraso permitidos: {$diasMaxPermitNum}\n" .
                    "Observación: los días de atraso superan o igualan los días máximos de atraso permitidos.";

                return [
                    'success'                      => true,
                    'nit'                          => (string) $nit,
                    'cupo_credito'                 => $cupoCredito,
                    'control_cupo_credito'         => $controlCupo,
                    'dias_maximo_atraso_permitido' => $diasMaxPermitNum,
                    'max_dias_atraso_factura'      => $maxDiasAtraso,
                    'saldo_total_cartera'          => $saldoTotal,
                    'saldo_disponible'             => $saldoDisponible,
                    'dias_consignacion'            => $diasConsignacion,
                    'valor_total_consignacion'     => $valorConsignacion,
                    'resultado_consignacion'       => $resultadoConsignacion,
                    'alerta' => [
                        'codigo'  => 'SUPERA_DIAS_ATRASO',
                        'mensaje' => "El cliente supera o iguala los días máximos de atraso permitidos ({$diasMaxPermitNum}).",
                    ],
                    'resumen'  => $resumen,
                    'facturas' => $facturas,
                ];
            }

            // --- Paso 7: saldo disponible negativo ---
            if ($saldoDisponible < 0) {
                $resumen =
                    "Control de cupo: {$controlCupo}\n" .
                    "Cupo: {$cupoCredito}\n" .
                    "Saldo total cartera: {$saldoTotal}\n" .
                    "Disponible (cupo - cartera): {$saldoDisponible}\n" .
                    "Max días atraso: {$maxDiasAtraso}\n" .
                    "Días de atraso permitidos: {$diasMaxPermitNum}\n" .
                    "Observación: el saldo disponible es negativo, el cliente supera el cupo de crédito.";

                return [
                    'success'                      => true,
                    'nit'                          => (string) $nit,
                    'cupo_credito'                 => $cupoCredito,
                    'control_cupo_credito'         => $controlCupo,
                    'dias_maximo_atraso_permitido' => $diasMaxPermitNum,
                    'max_dias_atraso_factura'      => $maxDiasAtraso,
                    'saldo_total_cartera'          => $saldoTotal,
                    'saldo_disponible'             => $saldoDisponible,
                    'dias_consignacion'            => $diasConsignacion,
                    'valor_total_consignacion'     => $valorConsignacion,
                    'resultado_consignacion'       => $resultadoConsignacion,
                    'alerta' => [
                        'codigo'  => 'SIN_SALDO_DISPONIBLE',
                        'mensaje' => 'El cliente no tiene saldo disponible en el cupo de crédito (sobregiro).',
                    ],
                    'resumen'  => $resumen,
                    'facturas' => $facturas,
                ];
            }

            // --- Paso 8: aquí recién calculamos CONSIGNACIÓN ---
            $service           = new ConsignacionService();
            $diasConsignacion  = $service->calcularDiasConsignacion($nit);
            $valorConsignacion = $service->calcularValorTotalConsignacionActiva($nit);

            $resultadoConsignacion = $saldoDisponible - $valorConsignacion;

            if ($diasMaxPermitNum > 0 && $diasConsignacion > $diasMaxPermitNum) {
                $resumen =
                    "Control de cupo: {$controlCupo}\n" .
                    "Cupo: {$cupoCredito}\n" .
                    "Saldo total cartera: {$saldoTotal}\n" .
                    "Disponible (cupo - cartera): {$saldoDisponible}\n" .
                    "Max días atraso: {$maxDiasAtraso}\n" .
                    "Días de atraso permitidos: {$diasMaxPermitNum}\n" .
                    "Días en consignación: {$diasConsignacion}\n" .
                    "Valor total en consignación activa: {$valorConsignacion}\n" .
                    "Cálculo resultado consignación (disponible - valor consignación): {$saldoDisponible} - {$valorConsignacion} = {$resultadoConsignacion}\n" .
                    "Observación: los días en consignación superan los días de atraso permitidos, por lo tanto detengo el flujo.";

                return [
                    'success'                      => true,
                    'nit'                          => (string) $nit,
                    'cupo_credito'                 => $cupoCredito,
                    'control_cupo_credito'         => $controlCupo,
                    'dias_maximo_atraso_permitido' => $diasMaxPermitNum,
                    'max_dias_atraso_factura'      => $maxDiasAtraso,
                    'saldo_total_cartera'          => $saldoTotal,
                    'saldo_disponible'             => $saldoDisponible,
                    'dias_consignacion'            => $diasConsignacion,
                    'valor_total_consignacion'     => $valorConsignacion,
                    'resultado_consignacion'       => $resultadoConsignacion,
                    'alerta' => [
                        'codigo'  => 'SUPERA_DIAS_CONSIGNACION',
                        'mensaje' => 'Los días en consignación superan los días de atraso permitidos.',
                    ],
                    'resumen'  => $resumen,
                    'facturas' => $facturas,
                ];
            }

            if ($resultadoConsignacion < 0) {
                $resumen =
                    "Control de cupo: {$controlCupo}\n" .
                    "Cupo: {$cupoCredito}\n" .
                    "Saldo total cartera: {$saldoTotal}\n" .
                    "Disponible (cupo - cartera): {$saldoDisponible}\n" .
                    "Max días atraso: {$maxDiasAtraso}\n" .
                    "Días de atraso permitidos: {$diasMaxPermitNum}\n" .
                    "Días en consignación: {$diasConsignacion}\n" .
                    "Valor total en consignación activa: {$valorConsignacion}\n" .
                    "Cálculo resultado consignación (disponible - valor consignación): {$saldoDisponible} - {$valorConsignacion} = {$resultadoConsignacion}\n" .
                    "Observación: el resultado de la consignación es negativo, el valor consignado supera el cupo disponible.";

                return [
                    'success'                      => true,
                    'nit'                          => (string) $nit,
                    'cupo_credito'                 => $cupoCredito,
                    'control_cupo_credito'         => $controlCupo,
                    'dias_maximo_atraso_permitido' => $diasMaxPermitNum,
                    'max_dias_atraso_factura'      => $maxDiasAtraso,
                    'saldo_total_cartera'          => $saldoTotal,
                    'saldo_disponible'             => $saldoDisponible,
                    'dias_consignacion'            => $diasConsignacion,
                    'valor_total_consignacion'     => $valorConsignacion,
                    'resultado_consignacion'       => $resultadoConsignacion,
                    'alerta' => [
                        'codigo'  => 'SUPERA_VALOR_CONSIGNACION',
                        'mensaje' => 'El valor de consignación supera el cupo disponible.',
                    ],
                    'resumen'  => $resumen,
                    'facturas' => $facturas,
                ];
            }

            // --- Paso 10: todo OK ---
            $resumen =
                "Control de cupo: {$controlCupo}\n" .
                "Cupo: {$cupoCredito}\n" .
                "Saldo total cartera: {$saldoTotal}\n" .
                "Disponible (cupo - cartera): {$saldoDisponible}\n" .
                "Max días atraso: {$maxDiasAtraso}\n" .
                "Días de atraso permitidos: {$diasMaxPermitNum}\n" .
                "Días en consignación: {$diasConsignacion}\n" .
                "Valor total en consignación activa: {$valorConsignacion}\n" .
                "Cálculo resultado consignación (disponible - valor consignación): {$saldoDisponible} - {$valorConsignacion} = {$resultadoConsignacion}\n" .
                "Observación: todas las validaciones se cumplen, no hay alertas generadas.";

            return [
                'success'                      => true,
                'nit'                          => (string) $nit,
                'cupo_credito'                 => $cupoCredito,
                'control_cupo_credito'         => $controlCupo,
                'dias_maximo_atraso_permitido' => $diasMaxPermitNum,
                'max_dias_atraso_factura'      => $maxDiasAtraso,
                'saldo_total_cartera'          => $saldoTotal,
                'saldo_disponible'             => $saldoDisponible,
                'dias_consignacion'            => $diasConsignacion,
                'valor_total_consignacion'     => $valorConsignacion,
                'resultado_consignacion'       => $resultadoConsignacion,
                'alerta' => [
                    'codigo'  => null,
                    'mensaje' => null,
                ],
                'resumen'  => $resumen,
                'facturas' => $facturas,
            ];

        } catch (\Throwable $e) {
            Log::error('API SEGUIMIENTO CARTERA ERROR', [
                'nit'   => $nit,
                'error' => $e->getMessage(),
            ]);

            $maxDiasAtraso         = 0;
            $saldoTotal            = 0.0;
            $saldoDisponible       = $cupoCreditoNum - $saldoTotal;
            $resultadoConsignacion = $saldoDisponible - $valorConsignacion;

            $resumen  = 'Error al consultar seguimiento de cartera. Reviso los logs para más detalle.';
            $resumen .= "\nCupo: {$cupoCredito}";
            $resumen .= "\nControl de cupo: {$controlCupo}";
            $resumen .= "\nSaldo total cartera: {$saldoTotal}";
            $resumen .= "\nDisponible (cupo - cartera): {$saldoDisponible}";
            $resumen .= "\nMax días atraso: {$maxDiasAtraso}";
            $resumen .= "\nDías de atraso permitidos: {$diasMaxPermitNum}";
            $resumen .= "\nDías en consignación: {$diasConsignacion}";
            $resumen .= "\nValor total en consignación activa: {$valorConsignacion}";
            $resumen .= "\nCálculo resultado consignación (disponible - valor consignación): {$saldoDisponible} - {$valorConsignacion} = {$resultadoConsignacion}";

            return [
                'success'                      => false,
                'error'                        => 'Error al consultar seguimiento de cartera',
                'nit'                          => (string) $nit,
                'cupo_credito'                 => $cupoCredito,
                'control_cupo_credito'         => $controlCupo,
                'dias_maximo_atraso_permitido' => $diasMaxPermitNum,
                'max_dias_atraso_factura'      => $maxDiasAtraso,
                'saldo_total_cartera'          => $saldoTotal,
                'saldo_disponible'             => $saldoDisponible,
                'dias_consignacion'            => $diasConsignacion,
                'valor_total_consignacion'     => $valorConsignacion,
                'resultado_consignacion'       => $resultadoConsignacion,
                'resumen'                      => $resumen,
                'resultadoConsignacion'        => $resultadoConsignacion,
                'facturas'                     => [],
            ];
        }
    }
}
