<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TercerosApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TercerosBusquedaController extends Controller
{
    protected $tercerosApi;

    public function __construct(TercerosApiService $tercerosApi)
    {
        $this->tercerosApi = $tercerosApi;
    }

    public function buscar(Request $request)
    {
        $query = trim((string) $request->input('query'));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Debe enviar al menos 2 caracteres para buscar.',
                'data'    => [],
            ], 422);
        }

        try {
            $lista = $this->tercerosApi->buscarTercerosPorQuery($query);

            return response()->json([
                'success' => true,
                'message' => 'Consulta realizada correctamente.',
                'data'    => $lista,
            ]);
        } catch (\Throwable $e) {
            Log::error('TercerosBusquedaController - Error en buscar', [
                'exception' => $e,
                'message'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar terceros en el servicio externo.',
                'data'    => [],
            ], 500);
        }
    }
}
