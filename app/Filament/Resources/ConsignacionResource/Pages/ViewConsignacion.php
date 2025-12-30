<?php

namespace App\Filament\Resources\ConsignacionResource\Pages;

use App\Filament\Resources\ConsignacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewConsignacion extends ViewRecord
{
    protected static string $resource = ConsignacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}