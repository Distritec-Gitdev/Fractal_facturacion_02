<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 👇 Esto muestra el botón "Crear"
            Actions\CreateAction::make()
                ->visible(fn () => RoleResource::canCreate()),
        ];
    }
}
