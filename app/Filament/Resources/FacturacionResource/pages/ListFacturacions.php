<?php

namespace App\Filament\Resources\FacturacionResource\Pages;

use App\Filament\Resources\FacturacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFacturacions extends ListRecords
{
    protected static string $resource = FacturacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Generar Factura')        // 👈 aquí cambiamos el texto
                ->icon('heroicon-o-receipt-percent'), // opcional, un icono más “factura”
        ];
    }
}
