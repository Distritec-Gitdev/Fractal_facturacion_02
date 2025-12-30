<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\CarteraController;
use Throwable;

class CalcularSeguimientoCartera extends Command
{
    protected $signature = 'app:calcular-seguimiento-cartera {nit}';
    protected $description = 'Calcula los días mayor de consignación para un responsable (NIT/Cédula)';

    public function handle(CarteraController $controller): int
    {
        $nit = $this->argument('nit');

        try {
            // Crear un Request con el NIT
            $request = Request::create('/api/seguimiento', 'POST', [
                'nit' => $nit
            ]);

            $respuesta = $controller->seguimiento($request);

            $this->info("NIT/Cédula: {$nit}");
            
            if (is_array($respuesta)) {
                $this->line(json_encode($respuesta, JSON_PRETTY_PRINT));
            } 

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Error ejecutando seguimiento: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}