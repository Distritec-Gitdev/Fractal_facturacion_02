<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

/**
 * Página de Filament para crear un nuevo registro de "Cliente".
 * 
 * Hereda de CreateRecord, que ya incluye toda la lógica para:
 * - Mostrar el formulario de creación definido en ClienteResource.
 * - Validar los datos ingresados.
 * - Guardar el nuevo cliente en la base de datos.
 */
class CreateCliente extends CreateRecord
{
    // Vincula esta página al recurso ClienteResource,
    // el cual define el modelo (Cliente), los campos y las reglas de validación.
    protected static string $resource = ClienteResource::class;
}
