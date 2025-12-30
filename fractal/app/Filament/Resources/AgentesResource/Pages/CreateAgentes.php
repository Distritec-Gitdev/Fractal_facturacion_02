<?php

namespace App\Filament\Resources\AgentesResource\Pages;

use App\Filament\Resources\AgentesResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

/**
 * Página de Filament para crear un nuevo registro de "Agente".
 * 
 * Está basada en la clase CreateRecord de Filament,
 * que ya trae toda la lógica para mostrar el formulario,
 * validar y guardar el registro en la base de datos.
 */
class CreateAgentes extends CreateRecord
{
    // Asocia esta página con el recurso "AgentesResource".
    // Filament usa este valor para saber a qué modelo/tabla corresponde.
    protected static string $resource = AgentesResource::class;
}
