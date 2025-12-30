<?php

namespace App\Filament\Resources\RetomaIphoneResource\Pages;

use App\Filament\Resources\RetomaIphoneResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRetomaIphone extends EditRecord
{
    protected static string $resource = RetomaIphoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
