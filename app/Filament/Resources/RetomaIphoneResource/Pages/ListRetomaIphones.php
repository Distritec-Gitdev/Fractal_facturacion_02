<?php

namespace App\Filament\Resources\RetomaIphoneResource\Pages;

use App\Filament\Resources\RetomaIphoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRetomaIphones extends ListRecords
{
    protected static string $resource = RetomaIphoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
