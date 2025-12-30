<?php

namespace App\Filament\Resources\SociosGestionResource\Pages;

use App\Filament\Resources\SociosGestionResource;
use App\Filament\Widgets\ChatWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;


class ListSociosGestion extends ListRecords
{
    protected static string $resource = SociosGestionResource::class;

    // Escucha broadcast para refrescar la tabla (tu línea original)
   protected $listeners = ['echo:cliente,.ClienteUpdated' => '$refresh'];

    /**
     * Monta el ChatWidget en la página de LISTADO.
     * (No usa columnSpanFull ni make(), compatible v2/v3)
     */
    protected function getFooterWidgets(): array
    {
        return [
            ChatWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
        ];
    }

    
}
