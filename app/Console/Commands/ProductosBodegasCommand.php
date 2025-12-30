<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Api\ProductosController;

class ProductosBodegasCommand extends Command
{
    protected $signature   = 'productos:bodegas {nit} {codigo_bodega}';
    protected $description = 'Prueba ProductosController@productosBodegas desde consola';

    public function handle(ProductosController $controller): int
    {
        $nit          = $this->argument('nit');
        $codigoBodega = $this->argument('codigo_bodega');

        // 1) Creamos el Request vacío
        $request = new Request();

        // 2) Cargamos los datos que el controlador espera
        $request->replace([
            'nit'           => $nit,
            'codigo_bodega' => $codigoBodega,
        ]);


        $response = $controller->productosBodegas($request);

        if ($response instanceof JsonResponse) {
            $this->line($response->getContent());
        } else {
            dump($response);
        }

        return self::SUCCESS;
    }
}
