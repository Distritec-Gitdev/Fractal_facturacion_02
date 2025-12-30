<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Token;
use App\Models\Cliente;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Imagenes;
use Illuminate\Support\Facades\DB;
use App\Events\ClienteUpdated;
use App\Events\ClienteFirmado;



use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\DocumentosClienteMailable;
use ZipArchive;
use App\Models\SendEmailDocumentacion;
use Illuminate\Support\Facades\File;

use Illuminate\Support\Facades\View;









class ClienteController extends Controller
    /**
     * Obtiene el cliente con todas las relaciones y construye el array $data para PDFs
     */
  
{



      private function getClienteData($id_cliente)
    {
        $cliente = \App\Models\Cliente::with([
            'clientesNombreCompleto',
            'clientesContacto',
            'departamento',
            'municipio',
            'detallesCliente',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',
            'dispositivoscomprados',
            'dispositivosPago',
            'token'
        ])->findOrFail($id_cliente);

        // 1) Obtén el objeto trabajo (puede ser null si no existe relación)
        $trabajo = $cliente->trabajo;

        // 2) Valores originales (pueden venir null)
        $empresaNombre = $trabajo->empresa_labor ?? '';
        $empresaNum    = $trabajo->num_empresa   ?? '';

        // 3) Si ambos están vacíos y es_independiente == 1, forzar "INDEPENDIENTE"
        if (
            $empresaNombre === '' &&
            $empresaNum === '' &&
            ((int) ($trabajo->es_independiente ?? 0)) === 1
        ) {
            $empresaNombre = 'INDEPENDIENTE';
            // $empresaNum queda en '' por diseño
        }

        $data = [
            'id_cliente' => $cliente->id_cliente,
            'nombre' => trim(
                ($cliente->clientesNombreCompleto->Primer_nombre_cliente ?? '') . ' ' .
                ($cliente->clientesNombreCompleto->Segundo_nombre_cliente ?? '') . ' ' .
                ($cliente->clientesNombreCompleto->Primer_apellido_cliente ?? '') . ' ' .
                ($cliente->clientesNombreCompleto->Segundo_apellido_cliente ?? '')
            ),
            'primer_nombre' => $cliente->clientesNombreCompleto->primer_nombre ?? '',
            'segundo_nombre' => $cliente->clientesNombreCompleto->segundo_nombre ?? '',
            'primer_apellido' => $cliente->clientesNombreCompleto->primer_apellido ?? '',
            'segundo_apellido' => $cliente->clientesNombreCompleto->segundo_apellido ?? '',
            'cedula' => ($cliente->cedula ? number_format($cliente->cedula, 0, '', '.') : ''),
            'fecha_nac' => $cliente->clientesContacto->fecha_nac ?? '',
            'fecha_registro'           => $cliente->fecha_registro? \Illuminate\Support\Carbon::parse($cliente->fecha_registro)->format('d/m/Y')
            : null,
            'fechacompleta'            => $cliente->created_at ?? '',
            'departamento' => strtoupper($cliente->departamento->nombre ?? ''),
            'municipio' => strtoupper($cliente->municipio->nombre ?? ''),
            'correo' => $cliente->clientesContacto->correo ?? '',
            'tel' => $cliente->clientesContacto->tel ?? '',
            'tel_alternativo' => $cliente->clientesContacto->tel_alternativo ?? '',
            'direccion' => $cliente->clientesContacto->direccion ?? '',
            'municipio_residencia' => strtoupper($cliente->municipio->nombre ?? ''),
            'departamento_residencia' => strtoupper($cliente->departamento->nombre ?? ''),
            'empresa' => $empresaNombre,
            'empresa_contacto' => $empresaNum,
            'cod_asesor' => $cliente->cod_asesor ?? '',
            'plataforma' => $cliente->detallesCliente[0]->plataformaCredito->plataforma ?? '',
            'nombre_asesor' => $cliente->nombre_asesor ?? '',
            'sede' => $cliente->detallesCliente[0]->sede->Name_Sede ?? '',
            'marca' =>  $cliente->dispositivoscomprados[0]->marca->name_marca ?? '',
            'modelo' => $cliente->dispositivoscomprados[0]->modelo->name_modelo ?? '',
            'equipo' => $cliente->dispositivoscomprados[0]->marca->name_marca . ' ' . $cliente->dispositivoscomprados[0]->modelo->name_modelo ?? '',
            'imei' => $cliente->dispositivoscomprados[0]->imei ?? '',
            'producto_convenio' => $cliente->dispositivoscomprados[0]->producto_convenio ?? '',
            'garantia' => $cliente->dispositivoscomprados[0]->garantia->garantia?? '',
            'tiempo_garantia' => $cliente->dispositivoscomprados[0]->garantia->garantia?? '',
            'fecha_pc' => $cliente->detallesCliente[0]->fecha_pc ?? '',
            'd_pago' => $cliente->dispositivosPago[0]->pago->periodo_pago ?? '',
            'cuota_inicial' => (
                isset($cliente->dispositivosPago[0]->cuota_inicial)
                    ? number_format($cliente->dispositivosPago[0]->cuota_inicial, 0, '', '.')
                    : ''
            ),
            'numero_cuotas' => (
                isset($cliente->dispositivosPago[0]->num_cuotas)
                    ? number_format($cliente->dispositivosPago[0]->num_cuotas, 0, '', '.')
                    : ''
            ),
            'valor_cuotas' => (
                isset($cliente->dispositivosPago[0]->valor_cuotas)
                    ? number_format($cliente->dispositivosPago[0]->valor_cuotas, 0, '', '.')
                    : ''
            ),
            // Referencia 1
            'referencia1_nombre' =>
                trim(
                    ($cliente->referenciasPersonales1[0]->Primer_Nombre_rf1 ?? '') . ' ' .
                    ($cliente->referenciasPersonales1[0]->Segundo_Nombre_rf1 ?? '') . ' ' .
                    ($cliente->referenciasPersonales1[0]->Primer_Apellido_rf1 ?? '') . ' ' .
                    ($cliente->referenciasPersonales1[0]->Segundo_Apellido_rf1 ?? '')
                ),
            'referencia1_celular' => $cliente->referenciasPersonales1[0]->Celular_rf1 ?? '',
            'referencia1_parentesco' => $cliente->referenciasPersonales1[0]->zparentescoPersonal->parentesco ?? '',
            'referencia1_tiempo' => 
                trim(
                        ($cliente->referenciasPersonales1[0]->ztiempoconocerlonum->numeros?? '') . ' ' .
                        ($cliente->referenciasPersonales1[0]->ztiempoconocerlo->tiempo ?? '')
                    ),
            // Referencia 2
            'referencia2_nombre' =>
                trim(
                    ($cliente->referenciasPersonales2[0]->Primer_Nombre_rf2 ?? '') . ' ' .
                    ($cliente->referenciasPersonales2[0]->Segundo_Nombre_rf2 ?? '') . ' ' .
                    ($cliente->referenciasPersonales2[0]->Primer_Apellido_rf2 ?? '') . ' ' .
                    ($cliente->referenciasPersonales2[0]->Segundo_Apellido_rf2 ?? '')
                ),
            'referencia2_celular' => $cliente->referenciasPersonales2[0]->Celular_rf2 ?? '',
            'referencia2_parentesco' => $cliente->referenciasPersonales2[0]->zparentescoPersonal->parentesco ?? '',
            'referencia2_tiempo' => 
                trim(
                        ($cliente->referenciasPersonales2[0]->ztiempoconocerlonum->numeros?? '') . ' ' .
                        ($cliente->referenciasPersonales2[0]->ztiempoconocerlo->tiempo ?? '')
                    ),
            'firma' =>  trim(
                ($cliente->clientesNombreCompleto->Primer_nombre_cliente ?? '') . ' ' .
                ($cliente->clientesNombreCompleto->Segundo_nombre_cliente ?? '') . ' ' .
                ($cliente->clientesNombreCompleto->Primer_apellido_cliente ?? '') . ' ' .
                ($cliente->clientesNombreCompleto->Segundo_apellido_cliente ?? '')
            ),

            'token' => $cliente->token->token ?? '',
        ];
        return $data;
    }
    public function obtenerCliente(Request $request)
    {
        $cedula = $request->input('cedula');

        $response = Http::withHeaders([
            'Authorization' => 'eyJhbGciOiJSUzUxMiIsInR5cCI6IkpXVCJ9.eyJodHRwOi8vc2NoZW1hcy54bWxzb2FwLm9yZy93cy8yMDA1LzA1L2lkZW50aXR5L2NsYWltcy9uYW1laWRlbnRpZmllciI6InVzdWFyaW9ZZW1pbnVzIiwibmJmIjoxNzQ3MzQ3NTE5LCJleHAiOjE3NDc0MzM5MTksImlzcyI6IkFwaVllbWludXMiLCJhdWQiOiJQcm9kdWN0b3NZZW1pbnVzIn0.TjHkk_mEBlq4-9Q-i2PWhuIESdMjpzycufZn4D1LIy62UIJ8QkuLit6YeKSXaYHfA86b-iGvFhx7raQ45VB1rL7ayWbF3CkglToDwMrLiUIY3UpR2hHg7k77DpNGBkU2t0j6jAHfdjGHgLFCFIa2nuJYFw69I7aP61-_UK2ian7dwosxJFpY_ALqnL_vRk-m9mMCxW1XT4dtkx9gLWg9oJCPOvhZsC0QTXObIpgD_SrlAuK0tF5CtKa_aMzFohHqpMMNzEmMOOEkLULfjEXJY2_h0OBLcfJqHXDHHYwUe53imS2MhYTFbVQD7ShI1nOfRkOLGcaCW-L6fB3pzNqK1g', // Tu token aquí
        ])->post('https://distritec.yeminus.com/TablasSistema.Api/api/tablassistema/terceros/obtenerfiltradas', [
            "filtrar" => true,
            "buscarResponsables" => false,
            "activos" => true,
            "inactivos" => false,
            "relacion" => ["C", "P"],
            "busquedaRapida" => $cedula,
            "listaEstados" => ["A"]
        ]);

        if ($response->successful()) {
    $datos = $response->json();

    // Verificar si la estructura de datos es la esperada
    if (isset($datos['datos']['nits']) && is_array($datos['datos']['nits']) && count($datos['datos']['nits']) > 0) {
        $datos = $datos['datos']['nits'][0];

        // Transformar los datos según la estructura de tu formulario
        $clienteTransformado = [
            'cedula' => $datos['nit'] ?? null,
            'Primer_nombre_cliente' => $datos['nombre1'] ?? null,
            'Segundo_nombre_cliente' => $datos['nombre2'] ?? null,
            'Primer_apellido_cliente' => $datos['apellido1'] ?? null,
            'Segundo_apellido_cliente' => $datos['apellido2'] ?? null,
            'correo' => $datos['email'] ?? null,
            'tel' => $datos['telefono1'] ?? null,
            'tel_alternativo' => $datos['telefono2'] ?? null,
            'direccion' => $datos['direccion'] ?? null,
            'empresa' => $datos['nombreComercial'] ?? null,
            'telefono_empresa' => $datos['celular'] ?? null,
            // Agrega más campos según sea necesario
        ];

        return response()->json($clienteTransformado);
    } else {
        return response()->json(['error' => 'No se encontraron datos del cliente'], 404);
    }
    } else {
        return response()->json(['error' => 'No se pudo obtener la información del cliente'], 500);
    }
    }

    public function testConsulta($cedula)
    {
        try {
            $apiService = app(\App\Services\TercerosApiService::class);
            $resultado = $apiService->buscarPorCedula($cedula, '44');
            
            return response()->json([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

        /**
     * Genera un link temporal para un cliente
     */
    public function generarLinkTemporal(Request $request)
    {
        $request->validate([
            'id_cliente' => 'required|exists:clientes,id_cliente',
        ]);
        $id_cliente = $request->input('id_cliente');
        $token = Str::random(40);
        $expiracion = Carbon::now()->addMinutes(30);

        $nuevoToken = Token::create([
            'id_cliente' => $id_cliente,
            'token' => $token,
            'expires_at' => $expiracion,
        ]);

        $url = route('link.temporal', ['token' => $token]);
        return response()->json(['url' => $url, 'expira' => $expiracion]);
    }

    /**
     * Acceso mediante link temporal
     */
    public function accesoPorLink($token)
    {
        $tokenModel = Token::where('token', $token)->first();
        if (!$tokenModel) {
            return response('El enlace es inválido.', 403);
        }
        if (isset($tokenModel->expires_at) && $tokenModel->expires_at < now()) {
            return response('El enlace ha expirado.', 403);
        }
        if (isset($tokenModel->confirmacion_token) && $tokenModel->confirmacion_token == 1) {
            return response('El enlace ya fue utilizado.', 403);
        }
        // Puedes marcarlo como usado aquí si es de un solo uso:
        // $tokenModel->update(['used_at' => now()]);
        $cliente = Cliente::find($tokenModel->id_cliente);
        if (!$cliente) {
            return response('Cliente no encontrado.', 404);
        }
        // Devuelve los datos del cliente (ajusta según lo que necesites mostrar)
        return response()->json($cliente);
    }

    /**
     * Mostrar formulario de login por token
     */
   public function mostrarLoginToken($token)
    {
        $tokenModel = Token::where('token', $token)->first();
        if (
            !$tokenModel ||
            (isset($tokenModel->expires_at) && $tokenModel->expires_at < now()) ||
            (isset($tokenModel->confirmacion_token) && $tokenModel->confirmacion_token == 2)
        ) {
            return response('El enlace es inválido o ha expirado. Solicita uno nuevo para continuar.', 403);
        }
        return view('auth.token-login', ['token' => $token]);
    }

    /**
     * Procesar login por token y cédula
     */
    public function procesarLoginToken(Request $request, $token)
    {
        $request->validate([
            'cedula' => 'required',
            'token' => 'required',
        ]);

        $tokenModel = Token::where('token', $token)
            ->where('token', $request->token)
            ->first();

        if (!$tokenModel || (isset($tokenModel->expires_at) && $tokenModel->expires_at < now()) || (isset($tokenModel->confirmacion_token) && $tokenModel->confirmacion_token == 2)) {
            return redirect()->back()->with('error', 'El enlace o token es inválido o ha expirado.');
        }

        $cliente = \App\Models\Cliente::with([
            'clientesNombreCompleto',
            'clientesContacto',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',
            'detallesCliente',
            'token',
        ])->find($tokenModel->id_cliente);

        if (!$cliente || $cliente->cedula != $request->cedula) {
            return redirect()->back()->with('error', 'La cédula no corresponde al cliente.');
        }

       // Si el token aún no está en 1 ni en 2, lo ponemos en 1 al validar login
        if ($tokenModel->confirmacion_token !== 1 && $tokenModel->confirmacion_token !== 2) {
            $tokenModel->update([
                'confirmacion_token' => 1,
                'hora_confirmacion' => now(),
            ]);
        } else {
            $tokenModel->update([
                'hora_confirmacion' => now(),
            ]);
        }

        // Obtener la plataforma del cliente (si existe) usando la relación
        $plataforma = null;
        if (isset($cliente->detallesCliente[0]) && $cliente->detallesCliente[0]->plataformaCredito) {
            $plataforma = $cliente->detallesCliente[0]->plataformaCredito->plataforma;
        }

        // Redirige a la vista de dashboard del cliente para mostrar los PDFs según la plataforma
        $response = response()->view('cliente.dashboard', compact('cliente', 'plataforma', 'token'));
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
        return $response;
    
    }

    /**
     * Generar PDF de garantía para un cliente
     */
     private function buildClienteData(Cliente $cliente): array
    {
        // Relaciones ya cargadas en $cliente
        $trabajo = $cliente->trabajo;

        // Lógica para empresa independiente
        $empresaNombre = $trabajo->empresa_labor ?? '';
        $empresaNum    = $trabajo->num_empresa   ?? '';
        if (empty($empresaNombre) && empty($empresaNum) && (int) ($trabajo->es_independiente ?? 0) === 1) {
            $empresaNombre = 'INDEPENDIENTE';
        }

        // Concatena nombres y apellidos
        $fullName = trim(
            ($cliente->clientesNombreCompleto->Primer_nombre_cliente ?? '') . ' ' .
            ($cliente->clientesNombreCompleto->Segundo_nombre_cliente ?? '') . ' ' .
            ($cliente->clientesNombreCompleto->Primer_apellido_cliente ?? '') . ' ' .
            ($cliente->clientesNombreCompleto->Segundo_apellido_cliente ?? '')
        );

        return [
            'id_cliente'               => $cliente->id_cliente,
            'nombre'                   => $fullName,
            'primer_nombre'            => $cliente->clientesNombreCompleto->primer_nombre ?? '',
            'segundo_nombre'           => $cliente->clientesNombreCompleto->segundo_nombre ?? '',
            'primer_apellido'          => $cliente->clientesNombreCompleto->primer_apellido ?? '',
            'segundo_apellido'         => $cliente->clientesNombreCompleto->segundo_apellido ?? '',
            'cedula'                   => $cliente->cedula ? number_format($cliente->cedula, 0, '', '.') : '',
            'fecha_nac'                => $cliente->clientesContacto->fecha_nac ?? '',
            'fecha_registro'           => $cliente->fecha_registro? \Illuminate\Support\Carbon::parse($cliente->fecha_registro)->format('d/m/Y')
            : null,
            'fechacompleta'            => $cliente->created_at ?? '',
            'hora'                     => $cliente->hora ?? '',
            'departamento'             => strtoupper($cliente->departamento->name_departamento ?? ''),
            'municipio'                => strtoupper($cliente->municipio->name_municipio ?? ''),
            'correo'                   => $cliente->clientesContacto->correo ?? '',
            'tel'                      => $cliente->clientesContacto->tel ?? '',
            'tel_alternativo'          => $cliente->clientesContacto->tel_alternativo ?? '',
            'direccion'                => $cliente->clientesContacto->direccion ?? '',
            'municipio_residencia'     => strtoupper($cliente->clientesContacto->municipios->name_municipio ?? ''),
            'departamento_residencia'  => strtoupper($cliente->clientesContacto->departamento->name_departamento ?? ''),
            'empresa'                  => $empresaNombre,
            'empresa_contacto'         => $empresaNum,
            'cod_asesor'               => $cliente->cod_asesor ?? '',
            'plataforma'               => $cliente->detallesCliente[0]->plataformaCredito->plataforma ?? '',
            'nombre_asesor'            => $cliente->nombre_asesor ?? '',
            'sede'                     => $cliente->detallesCliente[0]->sede->Name_Sede ?? '',
            'marca'                    => $cliente->dispositivoscomprados[0]->marca->name_marca ?? '',
            'modelo'                   => $cliente->dispositivoscomprados[0]->modelo->name_modelo ?? '',
            'equipo'                   => ($cliente->dispositivoscomprados[0]->marca->name_marca ?? '') . ' ' .
                                          ($cliente->dispositivoscomprados[0]->modelo->name_modelo ?? ''),
            'imei'                     => $cliente->dispositivoscomprados[0]->imei ?? '',
            'producto_convenio'        => $cliente->dispositivoscomprados[0]->producto_convenio ?? '',
            'garantia'                 => $cliente->dispositivoscomprados[0]->garantia->garantia ?? '',
            'tiempo_garantia'          => $cliente->dispositivoscomprados[0]->garantia->garantia ?? '',

            'estado_bateria'           => $cliente->dispositivoscomprados[0]->estado_bateria ?? '',
            'estado_pantalla'          => $cliente->dispositivoscomprados[0]->estado_pantalla ?? '',
            'sistema_operativo'        => $cliente->dispositivoscomprados[0]->sistemaOperativo->name_sisOpet ?? '',
            'observacion_equipo'       => $cliente->dispositivoscomprados[0]->observacion_equipo ?? '',
            
            'fecha_pc'                 => $cliente->detallesCliente[0]->fecha_pc ?? '',
            'd_pago'                   => $cliente->dispositivosPago[0]->pago->periodo_pago ?? '',
            'cuota_inicial'            => isset($cliente->dispositivosPago[0]->cuota_inicial)
                                           ? number_format($cliente->dispositivosPago[0]->cuota_inicial, 0, '', '.')
                                           : '',
            'numero_cuotas'            => isset($cliente->dispositivosPago[0]->num_cuotas)
                                           ? number_format($cliente->dispositivosPago[0]->num_cuotas, 0, '', '.')
                                           : '',
            'valor_cuotas'             => isset($cliente->dispositivosPago[0]->valor_cuotas)
                                           ? number_format($cliente->dispositivosPago[0]->valor_cuotas, 0, '', '.')
                                           : '',
            'referencia1_nombre'       => trim(
                                           ($cliente->referenciasPersonales1[0]->Primer_Nombre_rf1 ?? '') . ' ' .
                                           ($cliente->referenciasPersonales1[0]->Segundo_Nombre_rf1 ?? '') . ' ' .
                                           ($cliente->referenciasPersonales1[0]->Primer_Apellido_rf1 ?? '') . ' ' .
                                           ($cliente->referenciasPersonales1[0]->Segundo_Apellido_rf1 ?? '')
                                       ),
            'referencia1_celular'      => $cliente->referenciasPersonales1[0]->Celular_rf1 ?? '',
            'referencia1_parentesco'   => $cliente->referenciasPersonales1[0]->zparentescoPersonal->parentesco ?? '',
            'referencia1_tiempo'       => trim(
                                           ($cliente->referenciasPersonales1[0]->ztiempoconocerlonum->numeros ?? '') . ' ' .
                                           ($cliente->referenciasPersonales1[0]->ztiempoconocerlo->tiempo ?? '')
                                       ),
             // Referencia 2
            'referencia2_nombre'       =>
                                        trim(
                                            ($cliente->referenciasPersonales2[0]->Primer_Nombre_rf2 ?? '') . ' ' .
                                            ($cliente->referenciasPersonales2[0]->Segundo_Nombre_rf2 ?? '') . ' ' .
                                            ($cliente->referenciasPersonales2[0]->Primer_Apellido_rf2 ?? '') . ' ' .
                                            ($cliente->referenciasPersonales2[0]->Segundo_Apellido_rf2 ?? '')
                                        ),
            'referencia2_celular'      => $cliente->referenciasPersonales2[0]->Celular_rf2 ?? '',
            'referencia2_parentesco'   => $cliente->referenciasPersonales2[0]->zparentescoPersonal->parentesco ?? '',
            'referencia2_tiempo'       => 
                                        trim(
                                                
                                                ($cliente->referenciasPersonales2[0]->ztiempoconocerlonum->numeros?? '') . ' ' .
                                                ($cliente->referenciasPersonales2[0]->ztiempoconocerlo->tiempo ?? '')
                                            ),
            'firma'                    =>  
                                        trim(
                                            ($cliente->clientesNombreCompleto->Primer_nombre_cliente ?? '') . ' ' .
                                            ($cliente->clientesNombreCompleto->Segundo_nombre_cliente ?? '') . ' ' .
                                            ($cliente->clientesNombreCompleto->Primer_apellido_cliente ?? '') . ' ' .
                                            ($cliente->clientesNombreCompleto->Segundo_apellido_cliente ?? '')
                                        ),

            'token'                    => $cliente->token->token ?? '',
        ];
    }

    /**
     * Generar PDF de entrega de producto para un cliente
     * Guarda el nombre del archivo en el campo 'Carta_de_Garantías'
     */
    public function generarEntregaProducto($id_cliente)
    {
        $cliente = \App\Models\Cliente::with([
            'clientesNombreCompleto',
            'clientesContacto',
            'departamento',
            'municipio',
            'detallesCliente',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',   
            'dispositivoscomprados',
            'dispositivosPago',
            'token'     
        ])->findOrFail($id_cliente);
 
        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.entrega_producto', $data);
        
        // Solo devolver el PDF para mostrar en el navegador, NO guardar
        return $pdf->stream('entrega_producto.pdf');
    }

    /**
     * Mostrar PDF de entrega de producto (solo visualización)
     */
    public function mostrarEntregaProducto($id_cliente)
    {
        $cliente = \App\Models\Cliente::with([
            'clientesNombreCompleto',
            'clientesContacto',
            'departamento',
            'municipio',
            'detallesCliente',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',   
            'dispositivoscomprados',
            'dispositivosPago',
             'token'        
        ])->findOrFail($id_cliente);
 
        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.entrega_producto', $data);
        
        // Solo devolver el PDF para mostrar en el navegador, NO guardar
        return $pdf->stream('entrega_producto.pdf');
    }

    /**
     * Generar PDF de carta antifraude PayJoy para un cliente
     * Guarda el nombre del archivo en el campo 'Carta_antifraude'
     */
    public function generarCartaAntifraude($id_cliente)
    {
        $cliente = \App\Models\Cliente::with([
            'clientesNombreCompleto',
            'clientesContacto',
            'departamento',
            'municipio',
            'detallesCliente',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',   
            'dispositivoscomprados',
            'dispositivosPago',
            'token'    
        ])->findOrFail($id_cliente);

        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.PayJoy-antifraude', $data);
        
        // Solo devolver el PDF para mostrar en el navegador, NO guardar
        return $pdf->stream('carta_antifraude.pdf');
    }

    /**
     * Mostrar PDF de carta antifraude PayJoy (solo visualización)
     */
    public function mostrarCartaAntifraude($id_cliente)
    {
        $cliente = \App\Models\Cliente::with([
            'clientesNombreCompleto',
            'clientesContacto',
            'departamento',
            'municipio',
            'detallesCliente',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',   
            'dispositivoscomprados',
            'dispositivosPago',
            'token'    
        ])->findOrFail($id_cliente);

        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.PayJoy-antifraude', $data);
        
        // Solo devolver el PDF para mostrar en el navegador, NO guardar
        return $pdf->stream('carta_antifraude.pdf');
    }

    /**
     * Generar PDF de carta antifraude Crediminuto para un cliente
     * Guarda el nombre del archivo en el campo 'Carta_de_Garantias2'
     */
    public function generarCrediminutoAntifraude($id_cliente)
    {
         $cliente = \App\Models\Cliente::with([
            'clientesNombreCompleto',
            'clientesContacto',
            'departamento',
            'municipio',
            'detallesCliente',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',   
            'dispositivoscomprados',
            'dispositivosPago',
            'token'   
        ])->findOrFail($id_cliente);

        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.Crediminuto-antifraude', $data);
        
        // Solo devolver el PDF para mostrar en el navegador, NO guardar
        return $pdf->stream('crediminuto_antifraude.pdf');
    }

    /**
     * Mostrar PDF de carta antifraude Crediminuto (solo visualización)
     */
    public function mostrarCrediminutoAntifraude($id_cliente)
    {
         $cliente = \App\Models\Cliente::with([
            'clientesNombreCompleto',
            'clientesContacto',
            'departamento',
            'municipio',
            'detallesCliente',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',   
            'dispositivoscomprados',
            'dispositivosPago',
            'token'    
        ])->findOrFail($id_cliente);

        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.Crediminuto-antifraude', $data);
        
        // Solo devolver el PDF para mostrar en el navegador, NO guardar
        return $pdf->stream('crediminuto_antifraude.pdf');
    }

    /**
     * Generar PDF de carta antifraude Krediya para un cliente
     * Guarda el nombre del archivo en el campo 'Carta_de_Garantias2'
     */
    public function generarKrediyaAntifraude($id_cliente)
    {
       $cliente = \App\Models\Cliente::with([
            'clientesNombreCompleto',
            'clientesContacto',
            'departamento',
            'municipio',
            'detallesCliente',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',   
            'dispositivoscomprados',
            'dispositivosPago',
            'token'    
        ])->findOrFail($id_cliente);

        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.Krediya-antifraude', $data);
        
        // Solo devolver el PDF para mostrar en el navegador, NO guardar
        return $pdf->stream('krediya_antifraude.pdf');
    }

    /**
     * Mostrar PDF de carta antifraude Krediya (solo visualización)
     */
    public function mostrarKrediyaAntifraude($id_cliente)
    {
       $cliente = \App\Models\Cliente::with([
            'clientesNombreCompleto',
            'clientesContacto',
            'departamento',
            'municipio',
            'detallesCliente',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',   
            'dispositivoscomprados',
            'dispositivosPago',
            'token'    
        ])->findOrFail($id_cliente);

        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.Krediya-antifraude', $data);
        
        // Solo devolver el PDF para mostrar en el navegador, NO guardar
        return $pdf->stream('krediya_antifraude.pdf');
    }

    /**
     * Generar PDF de compromiso antifraude para un cliente
     */
    public function generarCompromisoAntifraude($id_cliente)
    {
       $cliente = \App\Models\Cliente::with([
            'clientesNombreCompleto',
            'clientesContacto',
            'departamento',
            'municipio',
            'detallesCliente',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',   
            'dispositivoscomprados',
            'dispositivosPago',
            'token'    
        ])->findOrFail($id_cliente);

        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.compromiso-antifraude', $data);
        
        // Solo devolver el PDF para mostrar en el navegador, NO guardar
        return $pdf->stream('compromiso_antifraude.pdf');
    }
    /** Nombre amigable */
private function friendlyClienteName($cliente): string
{
    $n = $cliente->clientesNombreCompleto ?? null;
    $pn = trim(($n->Primer_nombre_cliente ?? $n->primer_nombre ?? '') . ' ' . ($n->Segundo_nombre_cliente ?? $n->segundo_nombre ?? ''));
    $pa = trim(($n->Primer_apellido_cliente ?? $n->primer_apellido ?? '') . ' ' . ($n->Segundo_apellido_cliente ?? $n->segundo_apellido ?? ''));
    $full = trim($pn . ' ' . $pa);
    return $full !== '' ? ucwords(mb_strtolower($full)) : 'Cliente';
}



public function finalizarProceso(Request $request) 
{
    \Log::info('▶️ finalizarProceso INICIO', ['payload' => $request->except(['_token'])]);

    $request->validate(['token' => 'required']);

    $tokenModel = \App\Models\Token::where('token', $request->token)->first();
    if (!$tokenModel || (isset($tokenModel->expires_at) && $tokenModel->expires_at < now())) {
        return response('El enlace ha expirado. Solicita uno nuevo para continuar.', 403);
    }

    $email_ok = null;
    $email_error = null;
    $estadoEnvio = 2; // 1 ok, 2 fallo

    if ((int)($tokenModel->confirmacion_token ?? 0) !== 2) {
        $tokenModel->update(['confirmacion_token' => 2]);

        $cliente = \App\Models\Cliente::with([
            'token',
            'detallesCliente.plataformaCredito',
            'detallesCliente.sede',
            'clientesNombreCompleto',
            'clientesContacto',
            'departamento',
            'municipio',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',
            'dispositivoscomprados.marca',
            'dispositivoscomprados.modelo',
            'dispositivoscomprados.garantia',
            'dispositivoscomprados.sistemaOperativo',
            'dispositivosPago.pago',
        ])->find($tokenModel->id_cliente);

        if ($cliente) {
            \Log::info('👤 Cliente cargado', ['cliente_id' => $cliente->id_cliente]);

            // 1) Carpeta de PDFs persistidos (tu flujo actual)
            $this->crearCarpetaPDFs();
            $clienteFolder = public_path('storage/pdfs/' . $cliente->id_cliente);
            if (!is_dir($clienteFolder)) {
                @mkdir($clienteFolder, 0755, true);
            }
            \Log::info('📂 Carpeta cliente lista', ['path' => $clienteFolder]);

            // 2) Generar PDFs base persistidos (tu lógica existente)
            $this->generarYGuardarPDFs($cliente, $clienteFolder);
            \Log::info('📄 PDFs base generados');

            // 3) Generar TyC persistido (tu lógica existente)
            $this->generarTerminosYCondicionesParaGuardar($cliente, $clienteFolder);
            \Log::info('✅ TyC generado/guardado');

            // 4) Reutilizar EXACTAMENTE el mismo array que ya funciona
            $data = $this->buildClienteData($cliente);

            // (INFO) Log de la plataforma y vista antifraude resuelta
            $vistaAntifraude = $this->resolveAntifraudeView($cliente); // '' si no coincide
            \Log::info('ℹ️ Plataforma/Vista antifraude', [
                'cliente_id' => $cliente->id_cliente,
                'vista'      => $vistaAntifraude !== '' ? $vistaAntifraude : '(omitida)'
            ]);

            // 5) Cifrar y guardar en disk('public') usando ese mismo $data.
            //    Esta llamada OMITIRÁ Antifraude si la vista está vacía o no existe.
            $attachmentsEncrypted = [];
            try {
                $attachmentsEncrypted = $this->encryptAndStorePdfsUsingSameData($cliente, $data);
            } catch (\Throwable $e) {
                \Log::error('❌ Error cifrando PDF (public)', ['err' => $e->getMessage()]);
            }

            // 6) Fallback (persistidos) si no hay cifrados
            $attachmentsPersistidos = [];
            try {
                foreach (glob($clienteFolder . '/*.pdf') as $abs) {
                    if (is_file($abs)) {
                        $attachmentsPersistidos[] = [
                            'path' => $abs,
                            'name' => basename($abs),
                            'mime' => 'application/pdf'
                        ];
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('⚠️ No se pudieron listar PDFs persistidos', ['msg' => $e->getMessage()]);
            }

            // 7) Resolver destinatario
            $to = method_exists($this, 'getClienteEmail')
                ? $this->getClienteEmail($cliente)
                : ($cliente->clientesContacto->correo ?? null);

            \Log::info('📧 Destinatario', ['email' => $to]);

            // 8) Envío de correo con reintentos
            $estadoEnvio = 2; // por defecto fallo
            if ($to) {
                $nombreCliente = $this->friendlyClienteName($cliente);

                $viewData = [
                    'subject'       => '📄 Documentación de tu compra - Distritec',
                    'clienteNombre' => $nombreCliente,
                    'logoUrl'       => asset('imagenes_cabecera_pdf/logo.png'),
                    'backgroundUrl' => asset('imagenes_cabecera_pdf/Izquierda2.png'),
                    'instagramLink' => 'https://instagram.com/distriteccolombia',
                    'whatsappLink'  => 'https://wa.me/573136200202',
                    'soporteLink'   => 'https://distritec.co',
                ];

                $maxAttempts = 3;
                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    try {
                        $mailable = new \App\Mail\DocumentosClienteMailable(
                            $viewData,
                            // cifrados primero (como strings o arrays {path,name,mime})
                            array_map(fn($p) => ['path' => $p, 'mime' => 'application/pdf'], $attachmentsEncrypted),
                            // fallback si no hay cifrados
                            $attachmentsPersistidos
                        );
                        \Mail::to($to)->send($mailable);
                        \Log::info('✅ Correo enviado', ['to' => $to, 'attempt' => $attempt]);
                        $estadoEnvio = 1;
                        break;
                    } catch (\Throwable $e) {
                        \Log::error('❌ Error envío correo', ['attempt' => $attempt, 'msg' => $e->getMessage()]);
                        usleep(300 * 1000);
                    }
                }

                $email_ok    = $estadoEnvio === 1 ? '¡Listo! Te hemos enviado los documentos a tu correo.' : null;
                $email_error = $estadoEnvio === 1 ? null : 'No pudimos enviar el correo con tus documentos. Por favor, verifica tu bandeja o contáctanos.';

                try {
                    \App\Models\SendEmailDocumentacion::create([
                        'id_cliente'         => $cliente->id_cliente,
                        'estado_envio_email' => $estadoEnvio, // 1 ok, 2 fallo
                    ]);
                } catch (\Throwable $e) {
                    \Log::warning('⚠️ No se pudo registrar estado_envio_email', ['msg' => $e->getMessage()]);
                }
            } else {
                \Log::warning('⚠️ Cliente sin email. NO se enviará correo.', ['cliente_id' => $cliente->id_cliente]);
                try {
                    \App\Models\SendEmailDocumentacion::create([
                        'id_cliente'         => $cliente->id_cliente,
                        'estado_envio_email' => 2,
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('❌ Error guardando estado_envio_email (sin email)', ['msg' => $e->getMessage()]);
                }
            }

            $tokenString = (string) ($cliente->token->token ?? $request->token ?? '');

            event(new \App\Events\ClienteFirmado(
                (int) $cliente->id_cliente,
                $tokenString,
                null // si luego quieres, aquí metes el userId del asesor
            ));
        }
    } else {
        $email_error = 'El proceso ya había sido confirmado anteriormente.';
    }

    \Log::info('🏁 finalizarProceso FIN (render gracias)');
    return view('cliente.gracias', compact('email_ok', 'email_error'));
}


/**
 * Cifra y guarda los PDFs reutilizando el MISMO $data que ya funciona.
 * Devuelve rutas absolutas listas para ->attach()
 */
private function encryptAndStorePdfsUsingSameData($cliente, array $data): array
{
    $paths = [];

    $disk   = Storage::disk('public'); // storage/app/public
    $relDir = "tmp_mail/{$cliente->id_cliente}";
    $disk->makeDirectory($relDir);

    // Documentos SIEMPRE
    $docs = [
        ['view' => 'pdf.entrega_producto',     'title' => 'Entrega de producto'],
        ['view' => 'pdf.terminosycondiciones', 'title' => 'Términos y Condiciones'],
    ];

    // Antifraude SOLO si plataforma coincide con alguna y la vista existe
    $antifraudeView = $this->resolveAntifraudeView($cliente);
    if ($antifraudeView !== '' && View::exists($antifraudeView)) {
        $docs[] = ['view' => $antifraudeView, 'title' => 'Carta Antifraude'];
    } else {
        \Log::info('ℹ️ Antifraude omitido: plataforma no soportada o vista inexistente', [
            'cliente_id' => $cliente->id_cliente,
            'view'       => $antifraudeView,
        ]);
    }

    // Password = cédula (solo dígitos)
    $rawPass  = (string)($cliente->cedula ?? $cliente->num_documento ?? '');
    $password = preg_replace('/\D+/', '', $rawPass) ?: (string)$rawPass;

    // Nombre para archivo (usa valor del array si viene)
    $nombreCliente = $data['clienteNombre'] ?? trim(sprintf(
        '%s %s %s %s',
        $cliente->clientesNombreCompleto->Primer_nombre_cliente  ?? '',
        $cliente->clientesNombreCompleto->Segundo_nombre_cliente ?? '',
        $cliente->clientesNombreCompleto->Primer_apellido_cliente ?? '',
        $cliente->clientesNombreCompleto->Segundo_apellido_cliente ?? ''
    ));
    $nombreCliente = Str::of($nombreCliente)->ascii()->replaceMatches('/\s+/', ' ')->trim()->toString();

    foreach ($docs as $d) {
        // Doble seguro: si la vista no existe, omite
        if (!View::exists($d['view'])) {
            \Log::warning('⚠️ Vista PDF no encontrada, se omite', ['view' => $d['view']]);
            continue;
        }

        $pdf = PDF::loadView($d['view'], $data);

        // Encriptar antes de output()
        if (method_exists($pdf, 'setEncryption')) {
            $pdf->setEncryption($password, $password);
        } else {
            $pdf->getDomPDF()->getCanvas()->get_cpdf()->setEncryption($password, $password, ['print']);
        }

        $filename     = "{$d['title']} - {$nombreCliente}.pdf";
        $relativePath = "{$relDir}/{$filename}";
        $disk->put($relativePath, $pdf->output());

        $paths[] = $disk->path($relativePath); // absoluto para ->attach()
        \Log::info('🔐 PDF cifrado (public)', [
            'cliente_id' => $cliente->id_cliente,
            'file'       => $relativePath
        ]);
    }

    return $paths;
}

/** Vista correcta para Antifraude según plataforma */
private function resolveAntifraudeView($cliente): string
{
    $detalle    = optional($cliente->detallesCliente)->first();
    $plataforma = strtoupper($detalle->plataformaCredito->plataforma
        ?? ($detalle->plataforma_credito->plataforma ?? ''));

    return match ($plataforma) {
        'CREDIMINUTO' => 'pdf.Crediminuto-antifraude',
        'KREDIYA'     => 'pdf.Krediya-antifraude',
        'PAYJOY'      => 'pdf.PayJoy-antifraude',
        'ALOCREDIT'   => 'pdf.alo-antifraude',
        default   => '',   // blade genérico (guion bajo)
    };
}



private function dataUriFromPublic(string $relativePath): ?string
{
    $abs = public_path(trim($relativePath, '/'));
    if (!is_file($abs)) {
        \Log::warning('dataUriFromPublic: archivo no existe', ['path' => $abs]);
        return null;
    }
    $bin = @file_get_contents($abs);
    if ($bin === false) return null;

    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'png'  => 'image/png',
        'jpg','jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        default => 'application/octet-stream',
    };

    return 'data:'.$mime.';base64,'.base64_encode($bin);
}

/** Paquete de assets embebidos para PDFs (logo + background) */
private function pdfAssets(): array
{
    return [
        // AJUSTA las rutas relativas si tus imágenes viven en otra carpeta
        'logoDataUri' => $this->dataUriFromPublic('imagenes_cabecera_pdf/logo.png'),
        'bgDataUri'   => $this->dataUriFromPublic('imagenes_cabecera_pdf/Izquierda2.png'),
    ];
}


/**
 * Crea adjuntos encriptados (password = cédula).
 * Fallback: si no se puede cifrar, adjunta el archivo persistido.
 */
private function buildEncryptedAttachments($cliente): array
{
    $clienteNombre = $this->friendlyClienteName($cliente);
    $cedula = $this->onlyDigits($cliente->cedula ?? $cliente->num_documento ?? '') ?: '0000';
    $clienteId = $cliente->id_cliente; // 7193 en tu log

    $tmpDir = storage_path("app/tmp_mail/{$clienteId}");
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0755, true);
    }

    // Crea la carpeta recursivamente si no existe
    if (!File::exists($tmpDir)) {
        File::makeDirectory($tmpDir, 0755, true);
    }

    $dataPDF = $this->buildClienteData($cliente);
    $plataforma = strtoupper(trim(optional(optional($cliente->detallesCliente)->first())->plataformaCredito->plataforma ?? ''));
    $vistaAntifraude = match ($plataforma) {
        'CREDIMINUTO' => 'pdf.Crediminuto-antifraude',
        'KREDIYA'     => 'pdf.Krediya-antifraude',
        'PAYJOY'      => 'pdf.PayJoy-antifraude',
        'ALOCREDIT'   => 'pdf.alo-antifraude',
        default       => 'pdf.carta-antifraude',
    };

    $adjuntos = [];

    // Entrega de producto
    $en1 = $this->renderEncryptedPdf('pdf.entrega_producto', $dataPDF, $cedula, $tmpDir . '/Entrega de producto - ' . $clienteNombre . '.pdf');
    if ($en1 && is_file($en1)) {
        $adjuntos[] = ['path' => $en1, 'name' => basename($en1), 'mime' => 'application/pdf'];
    } else {
        if ($fb = $this->fallbackPersistedFile($cliente, 'Carta_de_Garantías', 'Entrega de producto')) {
            $adjuntos[] = $fb;
            Log::warning('⚠️ Entrega de producto no pudo cifrarse. Enviando persistido.');
        }
    }

    // Carta Antifraude
    $en2 = $this->renderEncryptedPdf($vistaAntifraude, $dataPDF, $cedula, $tmpDir . '/Carta Antifraude - ' . $clienteNombre . '.pdf');
    if ($en2 && is_file($en2)) {
        $adjuntos[] = ['path' => $en2, 'name' => basename($en2), 'mime' => 'application/pdf'];
    } else {
        if ($fb = $this->fallbackPersistedFile($cliente, 'Carta_antifraude', 'Carta Antifraude')) {
            $adjuntos[] = $fb;
            Log::warning('⚠️ Antifraude no pudo cifrarse. Enviando persistido.');
        }
    }

    // TyC
    $en3 = $this->renderEncryptedPdf('pdf.terminosycondiciones', $dataPDF, $cedula, $tmpDir . '/Términos y Condiciones - ' . $clienteNombre . '.pdf');
    if ($en3 && is_file($en3)) {
        $adjuntos[] = ['path' => $en3, 'name' => basename($en3), 'mime' => 'application/pdf'];
    } else {
        if ($fb = $this->fallbackPersistedFile($cliente, 'terminosycondiciones', 'Términos y Condiciones')) {
            $adjuntos[] = $fb;
            Log::warning('⚠️ TyC no pudo cifrarse. Enviando persistido.');
        }
    }

    // deduplicar por nombre
    $unique = collect($adjuntos)->filter(fn($a) => is_file($a['path'] ?? ''))->unique('name')->values()->all();

    Log::info('🧾 PDFs para envío', [
        'count' => count($unique),
        'totalMB' => round(array_sum(array_map(fn($a) => (filesize($a['path']) ?: 0), $unique)) / (1024 * 1024), 2),
        'files' => array_map(fn($a) => $a['name'], $unique),
    ]);

    return $unique;
}

/**
 * Renderiza y cifra PDF (pass=cedula). Devuelve ruta si ok, null si falla.
 */
private function renderEncryptedPdf(string $viewName, array $data, int $clienteId, string $titulo, string $password): ?string
{
    try {
        $tmpDir = storage_path("app/tmp_mail/{$clienteId}");
        if (!\File::exists($tmpDir)) {
            \File::makeDirectory($tmpDir, 0755, true);
        }

        // Nombre cliente legible y sin acentos
        $c = $data['cliente'];
        $nombre = trim("{$c->clientes_nombre_completo->Primer_nombre_cliente} {$c->clientes_nombre_completo->Segundo_nombre_cliente} {$c->clientes_nombre_completo->Primer_apellido_cliente} {$c->clientes_nombre_completo->Segundo_apellido_cliente}");
        $nombre = \Str::of($nombre)->ascii()->replaceMatches('/\s+/', ' ')->trim();

        $dest = $tmpDir . DIRECTORY_SEPARATOR . "{$titulo} - {$nombre}.pdf";

        // ⚠️ corrige aquí tus vistas mal nombradas
        if ($viewName === 'pdf.carta-antifraude') {
            $viewName = 'pdf.carta_antifraude';
        }

        $pdf = \PDF::loadView($viewName, $data);

        if (method_exists($pdf, 'setEncryption')) {
            $pdf->setEncryption($password, $password);
        } else {
            $dompdf = $pdf->getDomPDF();
            $dompdf->getCanvas()->get_cpdf()->setEncryption($password, $password, ['print']);
        }

        file_put_contents($dest, $pdf->output());
        return $dest;
    } catch (\Throwable $e) {
        \Log::error('❌ Error cifrando PDF', ['view' => $viewName, 'err' => $e->getMessage()]);
        return null;
    }
}


/**
 * Fallback a archivo persistido en DB (campo = nombre EXACTO de la columna en `imagenes`)
 */
private function fallbackPersistedFile($cliente, string $campo, string $nombreCorto): ?array
{
    $imagenes = Imagenes::where('id_cliente', $cliente->id_cliente)->first();
    if (!$imagenes) return null;

    $filename = $imagenes->{$campo} ?? null;
    if (!$filename) return null;

    $path = public_path("storage/pdfs/{$cliente->id_cliente}/{$filename}");
    if (!is_file($path)) return null;

    $niceName = $nombreCorto . ' - ' . $this->friendlyClienteName($cliente) . '.pdf';
    return ['path' => $path, 'name' => $niceName, 'mime' => 'application/pdf'];
}



/** Solo dígitos (para clave de PDF) */
private function onlyDigits(?string $v): string
{
    return preg_replace('/\D+/', '', (string) $v ?? '') ?? '';
}

/** Resolver email si no tienes getClienteEmail() */
private function resolveClienteEmailFallback($cliente): ?string
{
    $email = $cliente->clientesContacto->correo ?? $cliente->correo ?? null;
    return $email ? mb_strtolower(trim($email)) : null;
}


    /**
     * Crear la carpeta base de PDFs si no existe
     */
    private function crearCarpetaPDFs()
    {
        $pdfsPath = public_path('storage/pdfs');
        if (!is_dir($pdfsPath)) {
            mkdir($pdfsPath, 0755, true);
            \Log::info('Carpeta de PDFs creada', ['ruta' => $pdfsPath]);
        }
    }

    /**
     * Generar y guardar todos los PDFs para un cliente
     */
    private function generarYGuardarPDFs($cliente, $clienteFolder)
    {
        try {
            // 1. Generar PDF de Entrega de Producto (siempre presente), al igaul que el de terminos y condiciones
            $this->generarEntregaProductoParaGuardar($cliente, $clienteFolder);
            $this->generarTerminosYCondicionesParaGuardar($cliente, $clienteFolder);

            
            // 2. Generar PDF de Carta Antifraude según la plataforma
            $plataforma = null;
            if (isset($cliente->detallesCliente[0]) && $cliente->detallesCliente[0]->plataformaCredito) {
                $plataforma = strtolower(trim($cliente->detallesCliente[0]->plataformaCredito->plataforma ?? ''));
            }
            
            switch ($plataforma) {
                case 'crediminuto':
                    $this->generarCrediminutoAntifraudeParaGuardar($cliente, $clienteFolder);
                    break;
                case 'krediya':
                    $this->generarKrediyaAntifraudeParaGuardar($cliente, $clienteFolder);
                    break;
                case 'payjoy':
                    $this->generarCartaAntifraudeParaGuardar($cliente, $clienteFolder);
                    break;
                case 'alocredit':
                    $this->generarAloAntifraudeParaGuardar($cliente, $clienteFolder);
                    break;
            }
            
            \Log::info('PDFs generados exitosamente para cliente', [
                'id_cliente' => $cliente->id_cliente,
                'plataforma' => $plataforma,
                'carpeta' => $clienteFolder
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error generando PDFs para cliente', [
                'id_cliente' => $cliente->id_cliente,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Listar PDFs guardados (solo para administradores)
     */
    public function listarPDFs()
    {
        $pdfsPath = public_path('storage/pdfs');
        $pdfs = [];
        
        if (is_dir($pdfsPath)) {
            $files = scandir($pdfsPath);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'pdf') {
                    $filePath = $pdfsPath . '/' . $file;
                    $pdfs[] = [
                        'nombre' => $file,
                        'tamaño' => filesize($filePath),
                        'fecha_creacion' => date('Y-m-d H:i:s', filemtime($filePath)),
                        'ruta' => $filePath
                    ];
                }
            }
        }
        
        // Ordenar por fecha de creación (más recientes primero)
        usort($pdfs, function($a, $b) {
            return strtotime($b['fecha_creacion']) - strtotime($a['fecha_creacion']);
        });
        
        return response()->json($pdfs);
    }

    /**
     * Descargar PDF específico (solo para administradores)
     */
    public function descargarPDF($filename)
    {
        $filePath = public_path('storage/pdfs/' . $filename);
        
        if (!file_exists($filePath)) {
            return response('Archivo no encontrado', 404);
        }
        
        return response()->download($filePath);
    }

    /**
     * Generar PDF de carta antifraude Aló Crédito para un cliente
     * Guarda el nombre del archivo en el campo 'Carta_antifraude_alo'
     */
    public function generarAloAntifraude($id_cliente)
    {
         $cliente = \App\Models\Cliente::with([
            'clientesNombreCompleto',
            'clientesContacto',
            'departamento',
            'municipio',
            'detallesCliente',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',   
            'dispositivoscomprados',
            'dispositivosPago',
            'token'  
        ])->findOrFail($id_cliente);

        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.alo-antifraude', $data);

        // Solo devolver el PDF para mostrar en el navegador, NO guardar
        return $pdf->stream('alo_antifraude.pdf');
    }

    /**
     * Mostrar PDF de carta antifraude Aló (solo visualización)
     */
    public function mostrarAloAntifraude($id_cliente)
    {
         $cliente = \App\Models\Cliente::with([
            'clientesNombreCompleto',
            'clientesContacto',
            'departamento',
            'municipio',
            'detallesCliente',
            'trabajo',
            'referenciasPersonales1',
            'referenciasPersonales2',   
            'dispositivoscomprados',
            'dispositivosPago',
            'token'  
        ])->findOrFail($id_cliente);

        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.alo-antifraude', $data);

        // Solo devolver el PDF para mostrar en el navegador, NO guardar
        return $pdf->stream('alo_antifraude.pdf');
    }

    public function guardarTermino(Request $request)
    {
        \Log::info('GuardarTermino', $request->all());
        $request->validate([
            'id_cliente' => 'required|integer',
            'tipo' => 'required|in:tyc,comercial',
            'acepta' => 'required|boolean',
        ]);

        $data = [
            'id_cliente' => $request->id_cliente,
        ];

        $update = [];
        if ($request->tipo === 'tyc') {
            $update['terminos_y_condiciones'] = $request->acepta ? 'SI' : 'NO';
            $update['confirmacion_TYC'] = $request->acepta ? now() : null;
        } else {
            $update['terminos_comerciales'] = $request->acepta ? 'SI' : 'NO';
            $update['confirmacion_comercial'] = $request->acepta ? now() : null;                                    
        }

        // Inserta o actualiza
        DB::table('terminos_cond_conf')->updateOrInsert($data, $update);

        return response()->json(['success' => true]);
    }

    /**
     * Generar PDF de Entrega de Producto y guardarlo en la carpeta del cliente
     */
    private function generarEntregaProductoParaGuardar($cliente, $clienteFolder)
    {
        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.entrega_producto', $data);
        
        $filename = $cliente->id_cliente . '_entrega_producto_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        $filepath = $clienteFolder . '/' . $filename;
        $pdf->save($filepath);
        
        // Insertar o actualizar en la tabla imagenes
        $registro = \App\Models\Imagenes::where('id_cliente', $cliente->id_cliente)->first();
        if ($registro) {
            $registro->update([
                'Carta_de_Garantías' => $filename,
            ]);
            \Log::info('Imagenes actualizado', [
                'id_cliente' => $cliente->id_cliente,
                'campo' => 'Carta_de_Garantías',
                'archivo' => $filename
            ]);
        } else {
            \App\Models\Imagenes::create([
                'id_cliente' => $cliente->id_cliente,
                'imagen_cedula_cara_delantera' => '',
                'imagen_cedula_cara_trasera' => '',
                'imagen_persona_con_cedula' => '',
                'Carta_de_Garantías' => $filename,
                'Carta_de_Garantias2' => null,
                'Carta_antifraude' => null,
                'estado_archivos' => null,
            ]);
            \Log::info('Imagenes creado', [
                'id_cliente' => $cliente->id_cliente,
                'campo' => 'Carta_de_Garantías',
                'archivo' => $filename
            ]);
        }
        
        \Log::info('PDF Entrega Producto guardado', [
            'id_cliente' => $cliente->id_cliente,
            'archivo' => $filename
        ]);
    }

    /**
     * Generar PDF de Carta Antifraude PayJoy y guardarlo en la carpeta del cliente
     */
    private function generarCartaAntifraudeParaGuardar($cliente, $clienteFolder)
    {
        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.PayJoy-antifraude', $data);
        
        $filename = $cliente->id_cliente . '_carta_antifraude_payjoy_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        $filepath = $clienteFolder . '/' . $filename;
        $pdf->save($filepath);
        
        // Insertar o actualizar en la tabla imagenes
        $registro = \App\Models\Imagenes::where('id_cliente', $cliente->id_cliente)->first();
        if ($registro) {
            $registro->update([
                'Carta_antifraude' => $filename,
            ]);
            \Log::info('Imagenes actualizado', [
                'id_cliente' => $cliente->id_cliente,
                'campo' => 'Carta_antifraude',
                'archivo' => $filename
            ]);
        } else {
            \App\Models\Imagenes::create([
                'id_cliente' => $cliente->id_cliente,
                'imagen_cedula_cara_delantera' => '',
                'imagen_cedula_cara_trasera' => '',
                'imagen_persona_con_cedula' => '',
                'Carta_de_Garantías' => null,
                'Carta_de_Garantias2' => null,
                'Carta_antifraude' => $filename,
                'estado_archivos' => null,
            ]);
            \Log::info('Imagenes creado', [
                'id_cliente' => $cliente->id_cliente,
                'campo' => 'Carta_antifraude',
                'archivo' => $filename
            ]);
        }
        
        \Log::info('PDF Carta Antifraude PayJoy guardado', [
            'id_cliente' => $cliente->id_cliente,
            'archivo' => $filename
        ]);
    }

    /**
     * Generar PDF de Carta Antifraude Crediminuto y guardarlo en la carpeta del cliente
     */
    private function generarCrediminutoAntifraudeParaGuardar($cliente, $clienteFolder)
    {
        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.Crediminuto-antifraude', $data);
        
        $filename = $cliente->id_cliente . '_carta_antifraude_crediminuto_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        $filepath = $clienteFolder . '/' . $filename;
        $pdf->save($filepath);
        
        // Insertar o actualizar en la tabla imagenes
        $registro = \App\Models\Imagenes::where('id_cliente', $cliente->id_cliente)->first();
        if ($registro) {
            $registro->update([
                'Carta_antifraude' => $filename,
            ]);
            \Log::info('Imagenes actualizado', [
                'id_cliente' => $cliente->id_cliente,
                'campo' => 'Carta_antifraude',
                'archivo' => $filename
            ]);
        } else {
            \App\Models\Imagenes::create([
                'id_cliente' => $cliente->id_cliente,
                'imagen_cedula_cara_delantera' => '',
                'imagen_cedula_cara_trasera' => '',
                'imagen_persona_con_cedula' => '',
                'Carta_de_Garantías' => null,
                'Carta_de_Garantias2' => null,
                'Carta_antifraude' => $filename,
                'estado_archivos' => null,
            ]);
            \Log::info('Imagenes creado', [
                'id_cliente' => $cliente->id_cliente,
                'campo' => 'Carta_antifraude',
                'archivo' => $filename
            ]);
        }
        
        \Log::info('PDF Carta Antifraude Crediminuto guardado', [
            'id_cliente' => $cliente->id_cliente,
            'archivo' => $filename
        ]);
    }

    /**
     * Generar PDF de Carta Antifraude Krediya y guardarlo en la carpeta del cliente
     */
    private function generarKrediyaAntifraudeParaGuardar($cliente, $clienteFolder)
    {
        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.Krediya-antifraude', $data);
        
        $filename = $cliente->id_cliente . '_carta_antifraude_krediya_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        $filepath = $clienteFolder . '/' . $filename;
        $pdf->save($filepath);
        
        // Insertar o actualizar en la tabla imagenes
        $registro = \App\Models\Imagenes::where('id_cliente', $cliente->id_cliente)->first();
        if ($registro) {
            $registro->update([
                'Carta_antifraude' => $filename,
            ]);
            \Log::info('Imagenes actualizado', [
                'id_cliente' => $cliente->id_cliente,
                'campo' => 'Carta_antifraude',
                'archivo' => $filename
            ]);
        } else {
            \App\Models\Imagenes::create([
                'id_cliente' => $cliente->id_cliente,
                'imagen_cedula_cara_delantera' => '',
                'imagen_cedula_cara_trasera' => '',
                'imagen_persona_con_cedula' => '',
                'Carta_de_Garantías' => null,
                'Carta_de_Garantias2' => null,
                'Carta_antifraude' => $filename,
                'estado_archivos' => null,
            ]);
            \Log::info('Imagenes creado', [
                'id_cliente' => $cliente->id_cliente,
                'campo' => 'Carta_antifraude',
                'archivo' => $filename
            ]);
        }
        
        \Log::info('PDF Carta Antifraude Krediya guardado', [
            'id_cliente' => $cliente->id_cliente,
            'archivo' => $filename
        ]);
    }

    /**
     * Generar PDF de Carta Antifraude Aló y guardarlo en la carpeta del cliente
     */
    private function generarAloAntifraudeParaGuardar($cliente, $clienteFolder)
    {
        $data = $this->buildClienteData($cliente);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.alo-antifraude', $data);
        
        $filename = $cliente->id_cliente . '_carta_antifraude_alo_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        $filepath = $clienteFolder . '/' . $filename;
        $pdf->save($filepath);
        
        // Insertar o actualizar en la tabla imagenes
        $registro = \App\Models\Imagenes::where('id_cliente', $cliente->id_cliente)->first();
        if ($registro) {
            $registro->update([
                'Carta_antifraude' => $filename,
            ]);
            \Log::info('Imagenes actualizado', [
                'id_cliente' => $cliente->id_cliente,
                'campo' => 'Carta_antifraude',
                'archivo' => $filename
            ]);
        } else {
            \App\Models\Imagenes::create([
                'id_cliente' => $cliente->id_cliente,
                'imagen_cedula_cara_delantera' => '',
                'imagen_cedula_cara_trasera' => '',
                'imagen_persona_con_cedula' => '',
                'Carta_de_Garantías' => null,
                'Carta_de_Garantias2' => null,
                'Carta_antifraude' => $filename,
                'estado_archivos' => null,
            ]);
            \Log::info('Imagenes creado', [
                'id_cliente' => $cliente->id_cliente,
                'campo' => 'Carta_antifraude',
                'archivo' => $filename
            ]);
        }
        
        \Log::info('PDF Carta Antifraude Aló guardado', [
            'id_cliente' => $cliente->id_cliente,
            'archivo' => $filename
        ]);
    }

    // use Barryvdh\DomPDF\Facade\Pdf;
// use App\Models\Imagenes;
// use Illuminate\Support\Facades\Log;

/**
 * Genera SIEMPRE el PDF de Términos y Condiciones y actualiza la columna
 * `terminosycondiciones` en la tabla `imagenes`.
 */
private function generarTerminosYCondicionesParaGuardar($cliente, string $clienteFolder): ?string
{
    try {
        $data = $this->buildClienteData($cliente);

        // AÑADIMOS assets embebidos y nombre del cliente
        $data = array_merge($data, $this->pdfAssets(), [
            'clienteNombre' => $this->friendlyClienteName($cliente),
            'cliente'       => $cliente, // por si la vista usa más datos
        ]);

        $pdf = Pdf::loadView('pdf.terminosycondiciones', $data)->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'chroot'               => public_path(),
            'dpi'                  => 110,
            'defaultMediaType'     => 'screen',
            'defaultFont'          => 'DejaVu Sans',
        ]);

        $filename = $cliente->id_cliente . '_terminosycondiciones_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        $filepath = rtrim($clienteFolder, '/').'/'.$filename;

        $pdf->save($filepath);

        $registro = Imagenes::firstOrNew(['id_cliente' => $cliente->id_cliente]);
        $registro->terminosycondiciones = $filename;
        $registro->save();

        Log::info('📄 TyC guardado', ['id_cliente' => $cliente->id_cliente, 'file' => $filename]);
        return $filepath;

    } catch (\Throwable $e) {
        Log::error('❌ Error guardando TyC', ['id_cliente' => $cliente->id_cliente, 'err' => $e->getMessage()]);
        return null;
    }
}


private function getClienteEmail(\App\Models\Cliente $cliente): ?string
{
    // Candidatos en el propio cliente
    $candidates = [
        $cliente->correo_electronico ?? null,
        $cliente->email ?? null,
        $cliente->correo ?? null,
        $cliente->Email ?? null,
    ];

    // Relaciones camelCase
    if (isset($cliente->clientesContacto)) {
        $candidates[] = $cliente->clientesContacto->correo ?? null;
        $candidates[] = $cliente->clientesContacto->email ?? null;
    }

    // Relaciones snake_case (muchas apps las exponen así en arrays/serialización)
    if (isset($cliente->clientes_contacto)) {
        $candidates[] = $cliente->clientes_contacto->correo ?? null;
        $candidates[] = $cliente->clientes_contacto->email ?? null;
    }

    // Limpia, normaliza y valida
    foreach ($candidates as $raw) {
        if (! $raw) continue;
        $email = strtolower(trim($raw));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            \Log::info('📧 getClienteEmail() elegido', ['email' => $email]);
            return $email;
        } else {
            \Log::warning('⚠️ getClienteEmail() candidato inválido', ['raw' => $raw]);
        }
    }

    \Log::warning('⚠️ getClienteEmail() no encontró email válido', [
        'disponibles' => $candidates,
    ]);

    return null;
}


private function listarPdfsPorCarpeta(string $folder): array
{
    $paths = [];
    $total = 0;
    if (!is_dir($folder)) return [[], 0];

    foreach (glob($folder . '/*.pdf') as $abs) {
        if (is_file($abs)) {
            $paths[] = $abs;
            $total += @filesize($abs) ?: 0;
        }
    }
    return [$paths, $total];
}

private function zipSiConviene(array $pdfsAbs, int $totalBytes, \App\Models\Cliente $cliente): array
{
    $MAX_TOTAL = 24 * 1024 * 1024; // ~24MB límite común

    if ($totalBytes > $MAX_TOTAL || count($pdfsAbs) > 8) {
        if (class_exists(ZipArchive::class)) {
            $tmpDir  = storage_path('app/tmp');
            if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

            $zipName = $cliente->id_cliente . '_documentos_' . now()->format('Y-m-d_H-i-s') . '.zip';
            $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $zipName;

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($pdfsAbs as $abs) {
                    $zip->addFile($abs, basename($abs));
                }
                $zip->close();
                return [[], $zipPath];
            }
            \Log::warning('No se pudo crear ZIP; se envían PDFs sueltos');
        } else {
            \Log::warning('ZipArchive no disponible; se envían PDFs sueltos');
        }
    }

    return [$pdfsAbs, null];
}




}