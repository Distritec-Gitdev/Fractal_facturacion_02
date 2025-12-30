<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Api\ClienteController as ApiClienteController;
use App\Http\Controllers\ClienteDocumentacionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Livewire\ClienteDocumentacion;
use App\Models\Cliente;
use App\Models\Imagenes;
use App\Http\Livewire\ClienteDocumentacionStandalone;
use App\Http\Controllers\Api\SecureFileController;
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
    // Persistir cliente seleccionado para el widget (usa role de Spatie)
    Route::post('/filament/chat/set-client/{clientId}', function (int $clientId) {
        session()->put('chatClientId', $clientId);
        return response()->noContent();
    })->name('filament.chat.set-client')->middleware('role:admin|socio|gestor cartera|asesor_agente|super_admin')
      ->whereNumber('clientId');

    // Endpoints del chat (API interna del widget)
    Route::prefix('admin/chat')->name('admin.chat.')->group(function () {
        Route::get('/messages/{clientId}', [ChatController::class, 'getMessages'])
            ->name('messages');

        Route::post('/send', [ChatController::class, 'sendMessage'])
            ->name('send');
    });

    // Contador de no leídos (mejor también con auth)
    Route::get('/chats/{clientId}/unread-count', [ChatController::class, 'unreadCount'])
        ->name('chat.unread-count');
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

// Finalización proceso cliente (si este es público por token, déjalo sin auth)
Route::post('/cliente/finalizar', [ApiClienteController::class, 'finalizarProceso'])
    ->name('cliente.finalizar');

// Guardar “acepto términos” (si lo usa el cliente público)
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

// Subida de documentación del cliente (protegido; ajusta si el cliente final lo usa)
Route::post('/clientes/{cliente}/documentacion', [ClienteDocumentacionController::class, 'store'])
    ->name('clientes.documentacion.store')
    ->middleware(['auth']);

// ---------------------------------------------------------
// Reportes y Usuarios (admin / permisos)
// ---------------------------------------------------------
Route::get('/reporte', [ReportController::class, 'index'])
    ->middleware(['auth', 'permission:reports.view'])
    ->name('reporte');

Route::resource('users', UserController::class)
    ->middleware(['auth', 'role:admin']);


Route::post('/clientes/{cliente}/documentacion', [ClienteDocumentacionController::class, 'store'])
    ->name('clientes.documentacion.store');




Route::get('/cliente/{cliente}/documentacion', ClienteDocumentacionStandalone::class)
    ->name('cliente.documentacion');

// ---------------------------------------------------------
// Panel cliente autenticado (si aplica)
// ---------------------------------------------------------
Route::get('/cliente/{cliente}/dashboard', [ApiClienteController::class, 'dashboard'])
    ->name('cliente.dashboard')
    ->middleware(['auth']);

// (Si este es distinto al de /cliente/finalizar)
Route::post('/cliente/finalizar-proceso', [ApiClienteController::class, 'finalizarProceso'])
    ->name('finalizar.proceso');

// Firma del cliente (si requiere login del usuario interno, deja auth; si es público por token, quítalo)
Route::post('/cliente/{id}/firma', [ApiClienteController::class, 'guardarFirma'])
    ->name('cliente.guardarFirma')
    ->middleware(['web', 'auth']);

Route::get('/admin/chat/messages/{clientId}', [ChatController::class, 'getMessages'])
    ->name('admin.chat.messages')
    ->whereNumber('clientId');

Route::get('/chats/{clientId}/unread-count', [ChatController::class, 'unreadCount'])
    ->name('chat.unread-count')
    ->whereNumber('clientId');

Route::post('/filament/chat/set-client/{clientId}', function (int $clientId) {
    session()->put('chatClientId', $clientId);
    return response()->noContent();
})->name('filament.chat.set-client')
  ->middleware('role:admin|socio|gestor cartera|asesor_agente|super_admin')
  ->whereNumber('clientId');


Route::get('/docs/{cliente}/{file}', [SecureFileController::class, 'show'])
    ->where(['cliente' => '[0-9]+', 'file' => '.*'])
    ->middleware('auth')             // exige sesión Laravel
    ->name('docs.show');

