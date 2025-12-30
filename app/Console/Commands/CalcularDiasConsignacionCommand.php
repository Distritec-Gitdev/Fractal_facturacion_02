<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ConsignacionService;
use Throwable;

class CalcularDiasConsignacionCommand extends Command
{
    protected $signature = 'app:calcular-dias-consignacion {nit}';
    protected $description = 'Calcula los días mayor de consignación para un responsable (NIT/Cédula)';

    public function handle(ConsignacionService $service): int
    {
        $nit = (string) $this->argument('nit');

        try {
            $dias = $service->calcularDiasConsignacion($nit);

            $this->info("NIT/Cédula: {$nit}");
            $this->line("Días (mayor consignación): {$dias}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Error ejecutando calcularDiasConsignacion: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
