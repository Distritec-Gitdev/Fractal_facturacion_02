<?php

namespace App\Filament\Resources\SociosGestionResource\Pages;

use App\Filament\Resources\SociosGestionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSociosGestion extends ViewRecord
{
    protected static string $resource = SociosGestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
