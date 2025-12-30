<?php

namespace App\Filament\Resources\ConsignacionResource\Pages;

use App\Filament\Resources\ConsignacionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConsignacion extends EditRecord
{
    protected static string $resource = ConsignacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}