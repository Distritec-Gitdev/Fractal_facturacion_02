<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\ProductosController;

class ProductoDisponiblesCommand extends Command
{

    protected $signature = 'app:producto-disponibles-command {codigoProducto} {codigo_bodega}';

    protected $description = 'Prueba ProductosController@productoDisponibles desde consola';

    public function handle(ProductosController $controller): int
    {
        $codigoProducto       = $this->argument('codigoProducto');
        $codigoBodega         = $this->argument('codigo_bodega');


        $request = new Request();

        $request->replace([
            'codigoProducto'       => $codigoProducto,
            'codigo_bodega'        => $codigoBodega,
        ]);


        $response = $controller->productoDisponibles($request);


        if ($response instanceof JsonResponse) {
            $this->line($response->getContent());
        } else {
            dump($response);
        }

        return self::SUCCESS;
    }
}
