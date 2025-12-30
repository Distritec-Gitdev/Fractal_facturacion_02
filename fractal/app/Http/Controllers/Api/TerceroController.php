<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Services\YeminusApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TerceroController extends Controller
{
    protected $yeminusApi;
    

    public function __construct(YeminusApiService $yeminusApi)
    {
        $this->yeminusApi = $yeminusApi;
        $this->yeminusApi = $yeminusApi;
        
    }

    public function buscarOCrearTercero(Request $request)
    {
        $cedula = $request->input('cedula');

        // 1. Buscar en base local
        $cliente = Cliente::where('cedula', $cedula)->first();

        if ($cliente) {
            return response()->json([
                'success' => true,
                'message' => 'Cliente encontrado en base local',
                'data' => $cliente
            ]);
        }

        // 2. Si no existe, crear en Yeminus
        $apiUrl = config('services.api.url'); // Debe estar bien configurado en config/services.php
        $apiKey = config('services.api.key');

        $datos = [
            // ...arma aquí el array con los datos requeridos por Yeminus...
            'cedula' => $cedula,
            'nombre1' => $request->input('nombre1'),
            // ...otros campos...
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->post($apiUrl, $datos);

        $body = $response->json();

        if ($response->successful() && ($body['esExitoso'] ?? false)) {
            // 3. Guardar en base local
            $nuevoCliente = Cliente::create([
                'cedula' => $cedula,
                'ID_Cliente_Nombre' => $request->input('nombre1'),
                // ...otros campos...
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Cliente creado en Yeminus y guardado localmente',
                'data' => $nuevoCliente
            ]);
        } elseif (isset($body['datos']['terceroDuplicado']) && $body['datos']['terceroDuplicado']) {
            // Si ya existe en Yeminus, puedes guardar localmente también
            $nuevoCliente = Cliente::create([
                'cedula' => $cedula,
                'ID_Cliente_Nombre' => $request->input('nombre1'),
                // ...otros campos...
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Cliente ya existía en Yeminus, guardado localmente',
                'data' => $nuevoCliente
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear cliente en Yeminus',
                'error' => $body
            ], 500);
        }
    }
}