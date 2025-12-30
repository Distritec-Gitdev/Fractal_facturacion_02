<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TercerosBusquedaController;
use App\Http\Controllers\Api\RecompraController;
use App\Http\Controllers\Api\CarteraController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/cliente', [\App\Http\Controllers\Api\ClienteController::class, 'obtenerCliente']);
Route::post('/terceros/buscar-crear', [\App\Http\Controllers\Api\TerceroController::class, 'buscarOCrearTercero']);
Route::post('/cliente/agregar', [\App\Http\Controllers\Api\ClienteController::class, 'agregarCliente']);
Route::get('/test-consulta/{cedula}', [\App\Http\Controllers\Api\ClienteController::class, 'testConsulta']);

// consulta tercero facturación
Route::post('/terceros/buscar', [TercerosBusquedaController::class, 'buscar'])
    ->name('api.terceros.buscar');

// validar recompra
Route::post('/recompra/validar', [RecompraController::class, 'validar'])
    ->name('api.recompra.validar');

// NUEVO: días de atraso cartera (distribución / crédito)
Route::post('/cartera/dias-atraso', [CarteraController::class, 'diasAtraso'])
    ->name('api.cartera.dias_atraso');

Route::post('/cartera/seguimiento', [CarteraController::class, 'seguimiento'])
    ->name('api.cartera.seguimiento');
