<?php

namespace App\Filament\Resources\AgentesResource\Pages;

use App\Filament\Resources\AgentesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * Página de Filament para listar los registros de "Agentes".
 *
 * Extiende de ListRecords, que ya incluye toda la lógica para:
 * - Mostrar una tabla con los registros del recurso.
 * - Paginación, búsqueda y filtros.
 * - Acciones en cada fila (editar, eliminar, etc.) según lo definido en el Resource.
 */
class ListAgentes extends ListRecords
{
    // Asocia esta página con el recurso AgentesResource.
    // Filament usa esta referencia para saber qué modelo y configuración aplicar.
    protected static string $resource = AgentesResource::class;

    /**
     * Define las acciones que se mostrarán en la cabecera (parte superior de la lista).
     *
     * En este caso, solo agrega la acción de "Crear".
     * Esto genera un botón en la parte superior para registrar un nuevo agente.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(), // Botón "Crear agente"
        ];
    }
}
