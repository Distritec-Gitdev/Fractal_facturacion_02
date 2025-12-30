<?php

namespace App\Filament\Resources\ConsignacionResource\Pages;

use App\Filament\Resources\ConsignacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConsignacions extends ListRecords
{
    protected static string $resource = ConsignacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}