<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TercerosApiService;
use Illuminate\Support\Facades\Log;
use App\Models\Cliente;
use App\Models\ClientesContacto;
use App\Models\ClienteTrabajo;
use Carbon\Carbon;
use App\Filament\Resources\GestionClienteResource;

class TestConsultaCommand extends Command
{
    protected $signature = 'test:consulta {cedula}';
    protected $description = 'Prueba la consulta de la API con una cédula específica';

    public function handle()
    {
        $cedula = $this->argument('cedula');
        $this->info("Probando consulta para cédula: {$cedula}");

        try {
            $apiService = app(TercerosApiService::class);
            $resultado = $apiService->buscarPorCedula($cedula, '44');

            if ($resultado) {
                $this->info('Cliente encontrado:');
                $this->table(
                    ['Campo', 'Valor'],
                    collect($resultado)->map(fn($value, $key) => [$key, $value])->toArray()
                );

                // Simular creación/actualización de Cliente
                $cliente = Cliente::updateOrCreate(
                    ['cedula' => $cedula],
                    [
                        'fecha_registro' => Carbon::now()->toDateString(),
                        'hora' => Carbon::now()->toTimeString(),
                        'fecha_nac' => isset($resultado['fechaNacimiento']) ? Carbon::parse($resultado['fechaNacimiento'])->format('Y-m-d') : null,
                        'ID_Identificacion_Cliente' => GestionClienteResource::mapTipoDocumentoToCodigo(GestionClienteResource::mapDescripcionToTipoDocumento($resultado['descTipoIdentificacionTrib'] ?? null)),
                        'id_departamento' => null, // No disponible en la API para este campo específico
                        'id_municipio' => null, // No disponible en la API para este campo específico
                        'ID_tipo_credito' => 1, // Asumimos un tipo de crédito por defecto para la prueba
                    ]
                );
                $this->info("Cliente con ID {$cliente->id_cliente} " . ($cliente->wasRecentlyCreated ? 'creado' : 'actualizado') . ".");

                // Simular creación/actualización de ClientesContacto
                $clientesContacto = ClientesContacto::updateOrCreate(
                    ['id_cliente' => $cliente->id_cliente],
                    [
                        'correo' => $resultado['email'] ?? null,
                        'tel' => $resultado['celular'] ?? null,
                        'tel_alternativo' => $resultado['telefono1'] ?? null,
                        'direccion' => $resultado['direccion'] ?? null,
                        'residencia_id_departamento' => null, // No disponible en la API para este campo específico
                        'residencia_id_municipio' => null, // No disponible en la API para este campo específico
                        'fecha_expedicion' => isset($resultado['fechaExpedicionCedula']) ? Carbon::parse($resultado['fechaExpedicionCedula'])->format('Y-m-d') : null,
                        // Campos laborales
                        'empresa_labor' => 'Empresa de Prueba S.A.', // Valor hardcodeado para prueba
                        'tel_empresa' => '1234567890', // Valor hardcodeado para prueba
                        'es_independiente' => false, // Valor hardcodeado para prueba
                    ]
                );
                $this->info("ClientesContacto para ID {$cliente->id_cliente} " . ($clientesContacto->wasRecentlyCreated ? 'creado' : 'actualizado') . ".");

                // Simular la lógica de afterCreate/afterSave para ClienteTrabajo
                $trabajo = $cliente->trabajo;
                if ($trabajo) {
                    $trabajo->update([
                        'empresa_labor' => $clientesContacto->empresa_labor,
                        'num_empresa' => $clientesContacto->tel_empresa,
                        'es_independiente' => $clientesContacto->es_independiente,
                    ]);
                    $this->info("ClienteTrabajo para ID {$cliente->id_cliente} actualizado.");
                } else {
                    ClienteTrabajo::create([
                        'id_cliente' => $cliente->id_cliente,
                        'empresa_labor' => $clientesContacto->empresa_labor,
                        'num_empresa' => $clientesContacto->tel_empresa,
                        'es_independiente' => $clientesContacto->es_independiente,
                    ]);
                    $this->info("ClienteTrabajo para ID {$cliente->id_cliente} creado.");
                }

            } else {
                $this->error('No se encontró el cliente');
            }
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error('Error en test:consulta', [
                'cedula' => $cedula,
                'error' => $e->getMessage()
            ]);
        }
    }
} 