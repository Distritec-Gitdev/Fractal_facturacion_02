<?php

namespace App\Filament\Resources\ConsignacionResource\Pages;

use App\Filament\Resources\ConsignacionResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\Consignacion;
use App\Models\ClienteConsignacion;
use App\Models\VarianteConsignacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateConsignacion extends CreateRecord
{
    protected static string $resource = ConsignacionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            DB::connection('productos2')->beginTransaction();

            // 1. Crear o verificar el cliente
            $cliente = $this->crearOObtenerCliente($data);
            
            if (!$cliente) {
                throw new \Exception('No se pudo crear o encontrar el cliente');
            }

            // 2. Guardar las consignaciones con sus variantes
            $this->guardarConsignaciones($data, $cliente->id_cliente);

            DB::connection('productos2')->commit();

            Notification::make()
                ->title('Consignación creada exitosamente')
                ->success()
                ->send();

            // Redirigir al listado
            return redirect()->route('filament.admin.resources.consignacions.index');

        } catch (\Exception $e) {
            DB::connection('productos2')->rollBack();
            
            Log::error('Error al crear consignación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Notification::make()
                ->title('Error al crear consignación')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }

    protected function crearOObtenerCliente(array $data): ?ClienteConsignacion
    {
        $cedula = $data['cedula'] ?? null;

        if (!$cedula) {
            throw new \Exception('La cédula del cliente es obligatoria');
        }

        // Buscar si el cliente ya existe
        $cliente = ClienteConsignacion::where('cedula', $cedula)->first();

        if ($cliente) {
            // Actualizar datos si es necesario
            $cliente->update([
                'nombre1_cliente' => $data['nombre1_cliente'] ?? $cliente->nombre1_cliente,
                'nombre2_cliente' => $data['nombre2_cliente'] ?? $cliente->nombre2_cliente,
                'apellido1_cliente' => $data['apellido1_cliente'] ?? $cliente->apellido1_cliente,
                'apellido2_cliente' => $data['apellido2_cliente'] ?? $cliente->apellido2_cliente,
            ]);

            return $cliente;
        }

        // Crear nuevo cliente
        return ClienteConsignacion::create([
            'cedula' => $cedula,
            'nombre1_cliente' => $data['nombre1_cliente'] ?? null,
            'nombre2_cliente' => $data['nombre2_cliente'] ?? null,
            'apellido1_cliente' => $data['apellido1_cliente'] ?? null,
            'apellido2_cliente' => $data['apellido2_cliente'] ?? null,
        ]);
    }

    protected function guardarConsignaciones(array $data, int $idCliente): void
    {
        $items = $data['items'] ?? [];
        $vendedorId = $data['vendedor_id'] ?? null;
        $fechaGlobal = $data['fecha'] ?? now();

        if (empty($items)) {
            throw new \Exception('Debe agregar al menos un producto');
        }

        foreach ($items as $item) {
            $codigoProducto = $item['codigo_producto'] ?? null;
            $bodegaId = $item['bodega_id'] ?? null;
            $cantidad = (int) ($item['cantidad'] ?? 0);
            $variantes = $item['variantes'] ?? [];
            $tieneVariantesDisponibles = $item['tiene_variantes_disponibles'] ?? false;
            $variantesConfiguradas = $item['variantes_configuradas'] ?? false;

            // Validaciones
            if (!$codigoProducto || !$bodegaId || $cantidad <= 0) {
                Log::warning('Item inválido detectado', ['item' => $item]);
                continue;
            }

            // Si tiene variantes configuradas, validar que coincidan con la cantidad
            if ($variantesConfiguradas && count($variantes) !== $cantidad) {
                throw new \Exception("El producto {$codigoProducto} tiene {$cantidad} unidades pero solo " . count($variantes) . " variantes configuradas");
            }

            // Crear la consignación principal
            $consignacion = Consignacion::create([
                'vendedor_id' => $vendedorId,
                'producto_id' => $codigoProducto,
                'id_cliente' => $idCliente,
                'bodega_id' => $bodegaId,
                'cantidad' => $cantidad,
                'fecha' => $fechaGlobal,
            ]);

            Log::info('Consignación creada', [
                'id' => $consignacion->id,
                'producto' => $codigoProducto,
                'cantidad' => $cantidad
            ]);

            // Guardar variantes si existen
            if ($variantesConfiguradas && !empty($variantes)) {
                $this->guardarVariantes($consignacion->id, $codigoProducto, $variantes);
            }
        }
    }

    protected function guardarVariantes(int $consignacionId, string $codigoProducto, array $variantes): void
    {
        foreach ($variantes as $variante) {
            $codigoVariante = $variante['codigo'] ?? null;

            if (!$codigoVariante) {
                Log::warning('Variante sin código detectada', ['variante' => $variante]);
                continue;
            }

            VarianteConsignacion::create([
                'producto_id' => $codigoProducto,
                'variante' => $codigoVariante,
                'estado' => '1', // o el estado que uses por defecto
                'fecha_devolucion' => null,
                'id_responsable_devolucion' => null,
                'cantidad' => 1, // cada variante es 1 unidad
            ]);

            Log::info('Variante guardada', [
                'consignacion_id' => $consignacionId,
                'producto' => $codigoProducto,
                'variante' => $codigoVariante
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null; // Ya manejamos las notificaciones en mutateFormDataBeforeCreate
    }
}