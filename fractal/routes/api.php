<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/cliente', [\App\Http\Controllers\Api\ClienteController::class, 'obtenerCliente']);
Route::post('/terceros/buscar-crear', [\App\Http\Controllers\Api\TerceroController::class, 'buscarOCrearTercero']);
Route::post('/cliente/agregar', [\App\Http\Controllers\Api\ClienteController::class, 'agregarCliente']);
Route::get('/test-consulta/{cedula}', [\App\Http\Controllers\Api\ClienteController::class, 'testConsulta']);