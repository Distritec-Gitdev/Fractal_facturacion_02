<?php

namespace App\Filament\Resources\AgentesResource\Pages;

use App\Filament\Resources\AgentesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de Filament para editar un registro de "Agente".
 * 
 * Extiende de la clase EditRecord de Filament,
 * que ya implementa toda la lógica necesaria para:
 * - Mostrar el formulario con los datos cargados.
 * - Validar los cambios.
 * - Guardar las actualizaciones en la base de datos.
 */
class EditAgentes extends EditRecord
{
    // Asocia esta página con el recurso AgentesResource.
    // Filament usa este valor para saber qué modelo/tabla gestionar.
    protected static string $resource = AgentesResource::class;

    /**
     * Define las acciones que se mostrarán en la cabecera de la página de edición.
     * 
     * En este caso, solo se incluye la acción de "Eliminar".
     * Esto significa que cuando un usuario edita un agente,
     * también tendrá un botón para borrar el registro.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(), // Botón para eliminar el agente
        ];
    }
}
