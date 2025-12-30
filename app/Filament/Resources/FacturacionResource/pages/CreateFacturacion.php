<?php

namespace App\Filament\Resources\FacturacionResource\Pages;

use App\Filament\Resources\FacturacionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFacturacion extends CreateRecord
{
    protected static string $resource = FacturacionResource::class;

     // 👇 aquí cambias el título del encabezado
    protected static ?string $title = 'Crear factura';
}
