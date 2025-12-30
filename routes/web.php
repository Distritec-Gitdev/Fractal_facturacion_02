<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

use App\Http\Controllers\ChatController;
use App\Http\Controllers\Api\ClienteController as ApiClienteController;
use App\Http\Controllers\ClienteDocumentacionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Livewire\ClienteDocumentacionStandalone;
use App\Http\Controllers\Api\SecureFileController;
use App\Http\Controllers\ReciboFacturaController;
use App\Http\Livewire\Consignaciones;

use Illuminate\Http\Request;
use App\Filament\Resources\FacturacionResource\Forms\ModalProductosVariante;


// ---------------------------------------------------------
// Rutas públicas básicas
// ---------------------------------------------------------
Route::get('/', fn () => view('welcome'));
Route::get('/ping', fn () => 'pong');

// 🔧 Debug: dispara un broadcast de prueba (proteger si lo dejas)
Route::get('/test-event', function () {
    $cliente = \App\Models\Cliente::first();
    \Log::debug('🔔 [TEST] Disparando ClienteUpdated manualmente');
    event(new \App\Events\ClienteUpdated($cliente));
    \Log::debug('✅ [TEST] Evento ClienteUpdated disparado');
    return 'Evento disparado';
})->middleware(['auth', 'role:admin']);

// ---------------------------------------------------------
// Chat flotante (sólo autenticados)
// ---------------------------------------------------------
Route::middleware(['web', 'auth'])->group(function () {

    // Persistir cliente seleccionado para el widget (Spatie roles)
    Route::post('/filament/chat/set-client/{clientId}', function (int $clientId) {
        session()->put('chatClientId', $clientId);
        return response()->noContent();
    })
        ->name('filament.chat.set-client')
        ->middleware('role:admin|socio|gestor cartera|asesor_agente|super_admin')
        ->whereNumber('clientId');

    // Endpoints del chat (API interna del widget)
    Route::prefix('admin/chat')->name('admin.chat.')->group(function () {

        Route::get('/messages/{clientId}', [ChatController::class, 'getMessages'])
            ->name('messages')
            ->whereNumber('clientId');

        Route::post('/send', [ChatController::class, 'sendMessage'])
            ->name('send');
    });

    // Contador de no leídos
    Route::get('/chats/{clientId}/unread-count', [ChatController::class, 'unreadCount'])
        ->name('chat.unread-count')
        ->whereNumber('clientId');
});

// ---------------------------------------------------------
// Flujos por token / PDFs (endpoints públicos del cliente)
// ---------------------------------------------------------
Route::get('/acceso/{token}', [ApiClienteController::class, 'mostrarLoginToken'])
    ->name('link.temporal');

Route::post('/acceso/{token}', [ApiClienteController::class, 'procesarLoginToken'])
    ->name('token.login.submit');

Route::get('/pdf/entrega-producto/{id_cliente}', [ApiClienteController::class, 'generarEntregaProducto'])
    ->name('pdf.entrega-producto');

Route::get('/pdf/carta-antifraude/{id_cliente}', [ApiClienteController::class, 'generarCartaAntifraude'])
    ->name('pdf.carta-antifraude');

Route::get('/pdf/crediminuto-antifraude/{id_cliente}', [ApiClienteController::class, 'generarCrediminutoAntifraude'])
    ->name('pdf.crediminuto-antifraude');

Route::get('/pdf/krediya-antifraude/{id_cliente}', [ApiClienteController::class, 'generarKrediyaAntifraude'])
    ->name('pdf.krediya-antifraude');

Route::get('/pdf/alo-antifraude/{id_cliente}', [ApiClienteController::class, 'generarAloAntifraude'])
    ->name('pdf.alo-antifraude');

// Finalización proceso cliente (público por token)
Route::post('/cliente/finalizar', [ApiClienteController::class, 'finalizarProceso'])
    ->name('cliente.finalizar');

// Guardar “acepto términos” (público)
Route::post('/guardar-termino', [ApiClienteController::class, 'guardarTermino'])
    ->name('guardar.termino');

// ---------------------------------------------------------
// Admin: gestión de PDFs (protegido)
// ---------------------------------------------------------
Route::middleware(['auth', 'role:admin|socio|gestor cartera|asesor_agente|super_admin'])->group(function () {

    Route::get('/admin/pdfs', [ApiClienteController::class, 'listarPDFs'])
        ->name('admin.pdfs.list');

    Route::get('/admin/pdfs/descargar/{filename}', [ApiClienteController::class, 'descargarPDF'])
        ->name('admin.pdfs.download');

    Route::get('/admin/pdfs/view', fn () => view('admin.pdfs'))
        ->name('admin.pdfs.view');
});

// ---------------------------------------------------------
// Utilidades / diagnóstico (proteger en prod)
// ---------------------------------------------------------
Route::get('/phpinfo', function () {
    phpinfo();
})->middleware(['auth', 'role:admin']);

// ---------------------------------------------------------
// Documentación del cliente (protegido)
// ---------------------------------------------------------
Route::post('/clientes/{cliente}/documentacion', [ClienteDocumentacionController::class, 'store'])
    ->name('clientes.documentacion.store')
    ->middleware(['auth']);

Route::get('/cliente/{cliente}/documentacion', ClienteDocumentacionStandalone::class)
    ->name('cliente.documentacion');

// ---------------------------------------------------------
// Panel cliente autenticado (si aplica)
// ---------------------------------------------------------
Route::get('/cliente/{cliente}/dashboard', [ApiClienteController::class, 'dashboard'])
    ->name('cliente.dashboard')
    ->middleware(['auth']);

Route::post('/cliente/finalizar-proceso', [ApiClienteController::class, 'finalizarProceso'])
    ->name('finalizar.proceso');

// Firma del cliente (protegido)
Route::post('/cliente/{id}/firma', [ApiClienteController::class, 'guardarFirma'])
    ->name('cliente.guardarFirma')
    ->middleware(['web', 'auth']);

// ---------------------------------------------------------
// Acceso seguro a documentos
// ---------------------------------------------------------
Route::get('/docs/{cliente}/{file}', [SecureFileController::class, 'show'])
    ->where(['cliente' => '[0-9]+', 'file' => '.*'])
    ->middleware('auth')
    ->name('docs.show');

// ---------------------------------------------------------
// ✅ FACTURACIÓN: Preview + Guardar PNG (SIN Chrome)
// ---------------------------------------------------------
// Route::get('/admin/facturacion/recibo/preview/{key}', [ReciboFacturaController::class, 'preview'])
//     ->middleware(['auth'])
//     ->name('facturas.recibo.preview');

// Route::post('/admin/facturacion/recibo/store/{key}', [ReciboFacturaController::class, 'store'])
//     ->middleware(['auth'])
//     ->name('facturas.recibo.store');

// ---------------------------------------------------------
// FACTURACIÓN: Ver factura (SERVIDO DIRECTO)
// ---------------------------------------------------------
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/facturacion/factura/{key}', [ReciboFacturaController::class, 'show'])
        ->name('facturas.factura.show');
});
// ---------------------------------------------------------
// Reportes y Usuarios
// ---------------------------------------------------------
Route::get('/reporte', [ReportController::class, 'index'])
    ->middleware(['auth', 'permission:reports.view'])
    ->name('reporte');

Route::resource('users', UserController::class)
    ->middleware(['auth', 'role:admin']);


// ruta para validacion de productos en consignacion 
Route::post('/facturacion/validar-consignacion', function (Request $request) {
    $data = ModalProductosVariante::validarEnConsignaciones(
        (string) $request->input('cantidadSeleccionada', ''),
        (string) $request->input('codigoProducto', ''),
        (string) $request->input('codigo_bodega', ''),
        (string) $request->input('codigoVariante', ''),
        (string) $request->input('existenciaDisponible', ''),
    );

    return response()->json($data);
})
->name('facturacion.validar-consignacion')
->middleware(['web', 'auth']);







// Agregar después de la línea 155 (después de users)
Route::get('/admin/consignaciones', Consignaciones::class)
    ->name('admin.consignaciones')
    ->middleware(['auth', 'role:admin|socio|gestor cartera|super_admin']);