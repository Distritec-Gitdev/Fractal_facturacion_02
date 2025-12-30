<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\ConsignacionService;
use Illuminate\Support\Facades\Storage;

class ProductosController extends Controller
{
    protected ConsignacionService $consignacionService;

    public function __construct(ConsignacionService $consignacionService)
    {
        $this->consignacionService = $consignacionService;
    }

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

            $expiresIn = (int) ($response->json('expires_in') ?? 3600);
            $ttl       = max($expiresIn - 60, 60);

            Cache::put($cacheKey, $accessToken, $ttl);

            return $accessToken;

        } catch (\Throwable $e) {
            Log::error('TOKEN DINÁMICO → EXCEPCIÓN', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

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

        throw new \RuntimeException("sendWithRetries() → Máximo de intentos ({$maxAttempts}) alcanzado para {$url}");
    }

    /**
     * Lista productos de una bodega desde la API externa.
     */
    public function productosBodegas(Request $request)
    {
        $codigoBodega = $request->input('codigo_bodega');

        if (! $codigoBodega) {
            return response()->json([
                'success' => false,
                'error'   => 'codigo_bodega requerido',
            ], 400);
        }

        $headers = [
            'Content-Type' => 'application/json',
            'id_empresa'   => env('DISTRITEC_EMPRESA'),
            'usuario'      => env('DISTRITEC_USUARIO'),
        ];

        $token = $this->getDynamicToken();

        if (! $token) {
            Log::error('productosBodegas() → No se pudo obtener token dinámico');

            return response()->json([
                'success' => false,
                'error'   => 'No se pudo obtener token de autenticación',
            ], 500);
        }

        $headers['Authorization'] = "Bearer {$token}";

        $datosVacios = [
            'contidadDisponibleEnConsignacion' => 0,
            'cantidad'                         => 0,
            'bodega'                           => null,
            'valorHistorico'                   => null,
            'costoUnit'                        => null,
            'cantAlterna'                      => null,
            'costoUnitarioAlt'                 => null,
            'costoPromedio'                    => null,
            'precioTotal'                      => null,
            'costoTotalCalculado'              => null,
            'precio1'                          => null,
            'precio2'                          => null,
            'precio3'                          => null,
            'precio4'                          => null,
            'precio5'                          => null,
            'precio6'                          => null,
            'precio7'                          => null,
            'precio8'                          => null,
            'precio9'                          => null,
            'precio10'                         => null,
            'precio1IncluidoIVA'               => null,
            'precio2IncluidoIVA'               => null,
            'precio3IncluidoIVA'               => null,
            'precio4IncluidoIVA'               => null,
            'precio5IncluidoIVA'               => null,
            'precio6IncluidoIVA'               => null,
            'precio7IncluidoIVA'               => null,
            'precio8IncluidoIVA'               => null,
            'precio9IncluidoIVA'               => null,
            'precio10IncluidoIVA'              => null,
            'producto'                         => null,
            'descripcion'                      => null,
            'referencia'                       => null,
            'unidadMedida'                     => null,
            'cantidadVariable'                 => null,
            'unidadAlterna'                    => null,
            'precioUltimo'                     => null,
            'costoUltimo'                      => null,
            'grupoProducto'                    => null,
            'grupoProductoDescripcion'         => null,
            'descripcionSubGrupo'              => null,
            'descripcionMarca'                 => null,
            'codigoInvima'                     => null,
            'codigoAtcSku'                     => null,
            'iumSismed'                        => null,
            'codigoAlterno'                    => null,
            'observaciones'                    => null,
            'productoSimilar'                  => null,
            'descripcionProductoSimilar'       => null,
            'descripcionDisenio'               => null,
            'descripcionUnidadNegocio'         => null,
            'descripcionCategoria'             => null,
            'descripcionSubCategoria'          => null,
            'fechaUltimaCompra'                => null,
            'fechaUltimaVenta'                 => null,
            'edad'                             => null,
            'diasSinVenta'                     => null,
            'proveedor'                        => null,
            'descripcionProveedor'             => null,
            'descripcionTipoServicio'          => null,
            'descripcionBodega'                => null,
            'localizacion'                     => null,
            'stockMinimo'                      => null,
            'stockMaximo'                      => null,
            'empaque'                          => null,
            'empaqueVenta'                     => null,
            'descripcionLocalizacion'          => null,
            'existencia'                       => null,
            'pedidos'                          => null,
            'ordenes'                          => null,
            'disponibleInmediato'              => null,
            'disponible'                       => null,
            'fechaEntregaMaxima'               => null,
            'fechaEntregaMinima'               => null,
            'observacionEstadoLog'             => null,
            'observacionEstadoLog1'            => null,
            'fechaLogEstado'                   => null,
            'usuarioLogEstado'                 => null,
            'descripcionEstado'                => null,
            'colorEstado'                      => null,
            'descripcionMotivo'                => null,
        ];

        try {
            $url  = env('PRODUCTOS_API_URL');
            $anio = date('Y');
            $mes  = date('m');

            $response = $this->sendWithRetries('post', $url, $headers, [
                'anio'          => $anio,
                'bodegas'       => [$codigoBodega],
                'ejecutarConSql'=> true,
                'listaProductos'=> [],
                'mes'           => $mes,
            ]);

            if (config('app.debug')) {
                Log::debug('API PRODUCTOS → RESPONSE', [
                    'status' => $response->status(),
                ]);
            }

            if (! $response->successful()) {
                Log::warning('API PRODUCTOS → NO EXITOSA', [
                    'status' => $response->status(),
                ]);

                return response()->json([$datosVacios]);
            }

            $json = $response->json();

            if (data_get($json, 'esExitoso') !== true || ! is_array(data_get($json, 'datos'))) {
                Log::warning('API PRODUCTOS → esExitoso = false o datos inválidos');
                return response()->json([$datosVacios]);
            }

            $datos = data_get($json, 'datos', []);

            return $datos;

        } catch (\Throwable $e) {
            Log::error('API PRODUCTOS ERROR', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([$datosVacios]);
        }
    }

    //{cantidadSeleccionada} {codigoProducto} {codigo_bodega} {codigoVariante} {existenciaDisponible}
    public function productoDisponibles(Request $request)//consultar api existencias por variante y comparar la cantidad con consignaciones
    {
        
        $codigoProducto = $request->input('codigoProducto');   
        $codigoBodega = $request->input('codigo_bodega');
 
        
        if (!$codigoProducto || !$codigoBodega) {
            return response()->json([
                'success' => false,
                'error'   => 'datos requeridos',
            ], 400);
        }

     
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
            ];
        }

        $headers['Authorization'] = "Bearer {$token}";
   
        
        $datosVacios = [
            'error' => "Producto no disponible",
        ];
        try {
            sleep(1); #esperar un segundo
            $url = env('EXISTENCIA_POR_PRODUCTO_API_URL');
            $year = date('Y');
            $mes = date('m');
         
            //esperar un segundo
            
            $response = $this->sendWithRetries('post', $url, $headers, [
                'filtroProducto' => [
                    "filtrar"=> false, 
                    "estadoActivo"=> false, 
                    "estadoInactivo"=> false 
                ],
                "filtroSaldos"=> [
                    "bodegaSeleccionadas"=> [ 
                        $codigoBodega
                    ],
                    "producto"=> $codigoProducto, 
                    "codigoClasificacion"=> null,
                    "year"=> $year,  
                    "mes"=> $mes, 
                    "aplicado"=> true 
                ],
                "numeroCaja"=> "1" 
            ]);

            

            if (config('app.debug')) {
                Log::debug('API DIAS ATRASO → RESPONSE', [
                    'status' => $response->status(),
                ]);
            }

            if (! $response->successful()) {
                Log::warning('API PRODUCTOS → NO EXITOSA', [
                    'status' => $response->status(),
                ]);

                return [$datosVacios];
            }

            $json = $response->json();

            if (data_get($json, 'esExitoso') !== true || ! is_array(data_get($json, 'datos'))) {
                Log::warning('API DIAS ATRASO → esExitoso = false o datos inválidos');

                return [$datosVacios];
            }

            $errorMensaje =['puedeFacturar' => false, "mensaje" => "Este producto se encuentra en consignacion o supera la cantidad disponible para facturar.", "accion" => "redireccionar", "url" => env('DISTRITEC_CONSIGNACION_URL'),];
            $datosFinales = [];
            $cantidadDisponibleConsignacion= [];

            //evaluar si tiene variante
            if ($json['datos'] != null || is_array($json['datos'] || count($json['datos']) > 0)) {
                $datos = data_get($json, 'datos', []);
                foreach ($datos as $producto) {
                    $datosFinales[] = [
                        //'puedeFacturar' => true,
                        //'bodega' => $producto['bodega'],
                        //'descripcionBodega' => $producto['descripcionBodega'],
                        //'producto' => $producto['producto'],
                        'descripcion' => $producto['descripcion'],
                        //'clasificacion' => $producto['clasificacion'],
                        //'codigoClasificacion' => $producto['codigoClasificacion'],
                        //'descripcionClasificacion' => $producto['descripcionClasificacion'],
                        //'fechaVencimiento' => $producto['fechaVencimiento'],
                        'cantidad' => $producto['cantidad'],
                        //'unidadDeMedida' => $producto['unidadDeMedida'],
                        //'costo' => $producto['costo'],
                        //'costoTotal' => $producto['costoTotal'],
                        //'talla' => $producto['talla'],
                        //'color' => $producto['color'],
                        //'valorHistorico' => $producto['valorHistorico'],
                        //'cantidadAlterna' => $producto['cantidadAlterna'],
                        //'fechaEntrada' => $producto['fechaEntrada'],
                        //'fecha3' => $producto['fecha3'],
                        //'medida1' => $producto['medida1'],
                        //'medida2' => $producto['medida2'],
                        //'bloqueado' => $producto['bloqueado'],
                        //'motivoBloqueo' => $producto['motivoBloqueo'],
                        //'ordenTalla' => $producto['ordenTalla'],
                        //'ordenColor' => $producto['ordenColor'],
                        //'precioClasificacion' => $producto['precioClasificacion'],
                        //'codBarrasClasificacion' => $producto['codBarrasClasificacion'],
                        //'costoKitClasificacion' => $producto['costoKitClasificacion'],
                        //'anio' => $producto['anio'],
                        //'mes' => $producto['mes'],
                        //'precio' => $producto['precio'],
                        //'costoUltimo' => $producto['costoUltimo'],
                        //'cantidadSaldoPeriodo' => $producto['cantidadSaldoPeriodo'],
                        //'cantidadDescuadre' => $producto['cantidadDescuadre'],
                        //'diasParaVencimiento' => $producto['diasParaVencimiento'],
                        //'fechaVencimientoGarantia' => $producto['fechaVencimientoGarantia'],
                        'codigo' => $producto['variante'],
                        //'motivo' => $producto['motivo'],
                        //'referencia' => $producto['referencia'],
                        //'grupo' => $producto['grupo'],
                        //'descripcionGrupo' => $producto['descripcionGrupo'],
                        //'observacionesGenerales' => $producto['observacionesGenerales'],
                        //'unidadAlterna' => $producto['unidadAlterna'],
                        //'codigoInvima' => $producto['codigoInvima'],
                        //'codigoAtcSku' => $producto['codigoAtcSku'],
                        //'iumSismed' => $producto['iumSismed'],
                        //'productoConDesc' => $producto['productoConDesc'],
                        //'bodegaConDesc' => $producto['bodegaConDesc'],
                        //'nombreRangoEscala' => $producto['nombreRangoEscala'],
                        //'colorRangoEscala' => $producto['colorRangoEscala'],
                        'marca' => $producto['marca'] ?? $producto['producto'] ,
                        //'descripcionMarca' => $producto['descripcionMarca'],
                        //'descripcionLocalizacion' => $producto['descripcionLocalizacion'],
                        //'codigoTercero' => $producto['codigoTercero'],
                        //'descripcionTercero' => $producto['descripcionTercero'],
                        //'codigoAlternoProducto' => $producto['codigoAlternoProducto'],
                        //'edad' => $producto['edad'],
                    ]; 
                }

                return array_values($datosFinales);

                
            }else{//de lo contrario si tiene variante                

                return [
                    'puedeFacturar' => false,
                    "mensaje" => "No se encontraron productos en esta bodega",
                ];
                
            }

        } catch (\Throwable $e) {
            Log::error('API PRODUCTOS ERROR', [
                'error' => $e->getMessage(),
            ]);

            return [
                $datosVacios
            ];
        }
    }


    public function validarEnConsignaciones(Request $request)//consultar api existencias por variante y comparar la cantidad con consignaciones
    {
        $cantidadSeleccionada = $request->input('cantidadSeleccionada');
        $codigoProducto = $request->input('codigoProducto');   
        $codigoBodega = $request->input('codigo_bodega');
        $codigoVariante = $request->input('codigoVariante');
        $existenciaDisponible = $request->input('existenciaDisponible');
       
        
        if (!$cantidadSeleccionada || !$codigoProducto || !$codigoBodega) {
            return response()->json([
                'success' => false,
                'error'   => 'datos requeridos',
            ], 400);
        }

     
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
            ];
        }

        $headers['Authorization'] = "Bearer {$token}";
   
        
        $datosVacios = [
            'error' => "Producto no disponible",
        ];
        try {
            Log::warning('iniciando validacione en consugnaciones');

            $url = env('EXISTENCIA_POR_PRODUCTO_API_URL');
            $year = date('Y');
            $mes = date('m');
         
            $response = $this->sendWithRetries('post', $url, $headers, [
                'filtroProducto' => [
                    "filtrar"=> false, 
                    "estadoActivo"=> false, 
                    "estadoInactivo"=> false 
                ],
                "filtroSaldos"=> [
                    "bodegaSeleccionadas"=> [ 
                        $codigoBodega
                    ],
                    "producto"=> $codigoProducto, 
                    "codigoClasificacion"=> null,
                    "year"=> $year,  
                    "mes"=> $mes, 
                    "aplicado"=> true 
                ],
                "numeroCaja"=> "1" 
            ]);

            

            if (config('app.debug')) {
                Log::debug('API DIAS ATRASO → RESPONSE', [
                    'status' => $response->status(),
                ]);
            }

            if (! $response->successful()) {
                Log::warning('API PRODUCTOS → NO EXITOSA', [
                    'status' => $response->status(),
                ]);

                return [$datosVacios];
            }

            $json = $response->json();

            if (data_get($json, 'esExitoso') !== true || ! is_array(data_get($json, 'datos'))) {
                Log::warning('API DIAS ATRASO → esExitoso = false o datos inválidos');
                return [$datosVacios];
            }

            $errorMensaje = [
                'puedeFacturar' => false,
                "mensaje" => "Este producto se encuentra en consignacion o supera la cantidad disponible para facturar.",
                "accion" => "redireccionar",
                "url" => env('DISTRITEC_CONSIGNACION_URL'),
            ];
            $datosFinales = [];
            $cantidadDisponibleConsignacion= [];

            //evaluar si tiene variante
            if ($json['datos'] != null || is_array($json['datos'] || count($json['datos']) > 0)) {
                $datos = data_get($json, 'datos', []);
            
                foreach ($datos as $producto) {
                    //comparar el codigo del producto, bodega, producto y codigo producto
                    if ($producto['cantidad'] != 0 && $producto['bodega'] != null && $producto['producto'] > 0 && $producto['producto'] == $codigoProducto && $producto['bodega'] == $codigoBodega) {
                        $codigoVarianteYeminus = $producto['variante'];
                        $codigoProductoYeminus = $producto['producto'];
                        $codigoBodegaYeminus   = $producto['bodega'];

                      
                        if ($codigoVariante == $codigoVarianteYeminus && $codigoBodega == $codigoBodegaYeminus && $codigoProducto == $codigoProductoYeminus) {//evaluar que la variante sea la misma
                            $service = new ConsignacionService();
                            $cantidadDisponibleConsignacion = $service->verificarProductoEnConsignacion(
                                $codigoProducto,
                                $codigoBodega,
                                $codigoVariante
                            );
                     
                            if($cantidadDisponibleConsignacion['cantidad_consignada_bodega'] == null || $cantidadDisponibleConsignacion['cantidad_consignada_bodega'] == '' || $cantidadDisponibleConsignacion['cantidad_consignada_bodega'] == 0){
                                //La cantidad total en Yeminus  - la cantidad que está en consignación  = cantidad disponible
                                $cantidadDisponible = $producto['cantidad'] - $cantidadDisponibleConsignacion['cantidad_consignada_bodega'];

                                $NuevaCantidadDisponible = 0;
                                if ($cantidadDisponible > 0) {
                                    $NuevaCantidadDisponible = $cantidadDisponible - $cantidadSeleccionada;
                                    if ($NuevaCantidadDisponible >= 0  && $cantidadDisponibleConsignacion['en_consignacion'] == false) {
                                        $datosFinales[] = [
                                            'puedeFacturar' => true,
                                            'NuevaCantidadDisponible' => $NuevaCantidadDisponible,
                                            'cantidadDisponible' => $cantidadDisponible,
                                            'cantidadSeleccionada' => $cantidadSeleccionada,
                                            '$cantidadDisponibleConsignacion[cantidad_consignada_bodega]' => $cantidadDisponibleConsignacion,
                                            'bodega' => $producto['bodega'],
                                            'descripcionBodega' => $producto['descripcionBodega'],
                                            'producto' => $producto['producto'],
                                            'descripcion' => $producto['descripcion'],
                                            'clasificacion' => $producto['clasificacion'],
                                            'codigoClasificacion' => $producto['codigoClasificacion'],
                                            'descripcionClasificacion' => $producto['descripcionClasificacion'],
                                            'fechaVencimiento' => $producto['fechaVencimiento'],
                                            'cantidad' => $producto['cantidad'],
                                            'unidadDeMedida' => $producto['unidadDeMedida'],
                                            'costo' => $producto['costo'],
                                            'costoTotal' => $producto['costoTotal'],
                                            'talla' => $producto['talla'],
                                            'color' => $producto['color'],
                                            'valorHistorico' => $producto['valorHistorico'],
                                            'cantidadAlterna' => $producto['cantidadAlterna'],
                                            'fechaEntrada' => $producto['fechaEntrada'],
                                            'fecha3' => $producto['fecha3'],
                                            'medida1' => $producto['medida1'],
                                            'medida2' => $producto['medida2'],
                                            'bloqueado' => $producto['bloqueado'],
                                            'motivoBloqueo' => $producto['motivoBloqueo'],
                                            'ordenTalla' => $producto['ordenTalla'],
                                            'ordenColor' => $producto['ordenColor'],
                                            'precioClasificacion' => $producto['precioClasificacion'],
                                            'codBarrasClasificacion' => $producto['codBarrasClasificacion'],
                                            'costoKitClasificacion' => $producto['costoKitClasificacion'],
                                            'anio' => $producto['anio'],
                                            'mes' => $producto['mes'],
                                            'precio' => $producto['precio'],
                                            'costoUltimo' => $producto['costoUltimo'],
                                            'cantidadSaldoPeriodo' => $producto['cantidadSaldoPeriodo'],
                                            'cantidadDescuadre' => $producto['cantidadDescuadre'],
                                            'diasParaVencimiento' => $producto['diasParaVencimiento'],
                                            'fechaVencimientoGarantia' => $producto['fechaVencimientoGarantia'],
                                            'variante' => $producto['variante'],
                                            'motivo' => $producto['motivo'],
                                            'referencia' => $producto['referencia'],
                                            'grupo' => $producto['grupo'],
                                            'descripcionGrupo' => $producto['descripcionGrupo'],
                                            'observacionesGenerales' => $producto['observacionesGenerales'],
                                            'unidadAlterna' => $producto['unidadAlterna'],
                                            'codigoInvima' => $producto['codigoInvima'],
                                            'codigoAtcSku' => $producto['codigoAtcSku'],
                                            'iumSismed' => $producto['iumSismed'],
                                            'productoConDesc' => $producto['productoConDesc'],
                                            'bodegaConDesc' => $producto['bodegaConDesc'],
                                            'nombreRangoEscala' => $producto['nombreRangoEscala'],
                                            'colorRangoEscala' => $producto['colorRangoEscala'],
                                            'marca' => $producto['marca'],
                                            'descripcionMarca' => $producto['descripcionMarca'],
                                            'descripcionLocalizacion' => $producto['descripcionLocalizacion'],
                                            'codigoTercero' => $producto['codigoTercero'],
                                            'descripcionTercero' => $producto['descripcionTercero'],
                                            'codigoAlternoProducto' => $producto['codigoAlternoProducto'],
                                            'edad' => $producto['edad'],
                                        ]; 
                                    }else{
                                        return $errorMensaje;
                                    }
                                }else {
                                    return $errorMensaje;
                                }    
                            }else{
                                return $errorMensaje;
                            }
                        }
                    }else {
                        continue;
                    }
                }

                if (count($datosFinales) > 0){
                    return $datosFinales;
                }else {
                    return ["puedeFacturar" => false, "error" => "Producto no encontrado o no disponible con los datos ingresados"];
                }

                
            }else{//de lo contrario si no tiene variante o la variante es 0           
                $service = new ConsignacionService();
                $cantidadDisponibleConsignacion = $service->verificarProductoEnConsignacion(
                    $codigoProducto,
                    $codigoBodega,
                    $codigoVariante
                );

                $cantidadDisponible = $existenciaDisponible - $cantidadDisponibleConsignacion['cantidad_consignada_bodega'];

                if ($cantidadDisponible > 0) {
                    $NuevaCantidadDisponible = $cantidadDisponible - $cantidadSeleccionada;
                    if ($NuevaCantidadDisponible >= 0 && $cantidadDisponibleConsignacion['en_consignacion']  == false) {
                        return [
                            'cantidadDisponibleConsignacion' => $cantidadDisponibleConsignacion,
                            'puedeFacturar' => true,
                            'CantidadDisponible' => $NuevaCantidadDisponible,
                            'NuevaCantidadDisponible' => $NuevaCantidadDisponible,
                            'cantidadDisponible' => $cantidadDisponible,
                            'cantidadSeleccionada' => $cantidadSeleccionada,
                            'bodega' => $codigoBodega,
                            'descripcionBodega' => null,
                            'producto' => $codigoProducto,
                            'descripcion' => null,
                            'clasificacion' => null,
                            'codigoClasificacion' => null,
                            'descripcionClasificacion' => null,
                            'fechaVencimiento' => null,
                            'cantidad' => null,
                            'unidadDeMedida' => null,
                            'costo' => null,
                            'costoTotal' => null,
                            'talla' => null,
                            'color' => null,
                            'valorHistorico' => null,
                            'cantidadAlterna' => null,
                            'fechaEntrada' => null,
                            'fecha3' => null,
                            'medida1' => null,
                            'medida2' => null,
                            'bloqueado' => null,
                            'motivoBloqueo' => null,
                            'ordenTalla' => null,
                            'ordenColor' => null,
                            'precioClasificacion' => null,
                            'codBarrasClasificacion' => null,
                            'costoKitClasificacion' => null,
                            'anio' => null,
                            'mes' => null,
                            'precio' => null,
                            'costoUltimo' => null,
                            'cantidadSaldoPeriodo' => null,
                            'cantidadDescuadre' => null,
                            'diasParaVencimiento' => null,
                            'fechaVencimientoGarantia' => null,
                            'variante' => 0,
                            'motivo' => null,
                            'referencia' => null,
                            'grupo' => null,
                            'descripcionGrupo' => null,
                            'observacionesGenerales' => null,
                            'unidadAlterna' => null,
                            'codigoInvima' => null,
                            'codigoAtcSku' => null,
                            'iumSismed' => null,
                            'productoConDesc' => null,
                            'bodegaConDesc' => null,
                            'nombreRangoEscala' => null,
                            'colorRangoEscala' => null,
                            'marca' => null,
                            'descripcionMarca' => null,
                            'descripcionLocalizacion' => null,
                            'codigoTercero' => null,
                            'descripcionTercero' => null,
                            'codigoAlternoProducto' => null,
                            'edad' => null,
                        ]; 
                      
                    }else{
                        return $errorMensaje;
                    }
                   
                }else{
                    return $errorMensaje;
                }
            }


        } catch (\Throwable $e) {
            Log::error('API PRODUCTOS ERROR', [
                'error' => $e->getMessage(),
            ]);

            return [
                $datosVacios
            ];
        }
    }
}
