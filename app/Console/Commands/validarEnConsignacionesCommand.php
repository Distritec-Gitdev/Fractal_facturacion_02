<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\ProductosController;

class validarEnConsignacionesCommand extends Command
{

    protected $signature = 'app:validarEnConsignaciones {cantidadSeleccionada} {codigoProducto} {codigo_bodega} {codigoVariante} {existenciaDisponible}';

    protected $description = 'Prueba ProductosController@validarEnConsignaciones desde consola';

    public function handle(ProductosController $controller): int
    {

        $cantidadSeleccionada = $this->argument('cantidadSeleccionada');
        $codigoProducto =       $this->argument('codigoProducto');   
        $codigo_bodega =         $this->argument('codigo_bodega');
        $codigoVariante =       $this->argument('codigoVariante');
        $existenciaDisponible = $this->argument('existenciaDisponible');
       
        $request = new Request();

        $request->replace([
            'cantidadSeleccionada'  => $cantidadSeleccionada,    
            'codigoProducto'        => $codigoProducto,    
            'codigo_bodega'          => $codigo_bodega,        
            'codigoVariante'        => $codigoVariante,          
            'existenciaDisponible'  => $existenciaDisponible,    
        ]);


        $response = $controller->validarEnConsignaciones($request);


        if ($response instanceof JsonResponse) {
            $this->line($response->getContent());
        } else {
            dump($response);
        }

        return self::SUCCESS;
    }
}
