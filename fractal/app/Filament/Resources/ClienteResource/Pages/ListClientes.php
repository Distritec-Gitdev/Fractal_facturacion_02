<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\ChatWidget;
use Filament\Actions;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

class ListClientes extends ListRecords
{
    protected static string $resource = ClienteResource::class;

    protected $listeners = [
        'cliente-creado' => 'onClienteCreado',
        'gestor-actualizado'         => 'onGestorActualizado',
    ];

    // Echo para refrescar la tabla cuando se emite ClienteUpdated
   //protected $listeners = ['echo:cliente,ClienteUpdated' => '$refresh'];

    protected function getHeaderWidgets(): array
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

    public function render(): View
    {
        $start = microtime(true);

        $view = parent::render();   // render original de Filament

        $elapsed = (microtime(true) - $start) * 1000;

        Log::info('ListClientes::render', [
            'ms'  => $elapsed,
            'url' => request()->fullUrl() ?? null,
        ]);

        return $view;
    }

     #[On('estado-credito-actualizado')]
    public function onEstadoCreditoActualizado($payload = null): void
    {
        \Log::debug('🎯 ListClientes recibió estado-credito-actualizado', [
            'payload' => $payload,
        ]);

        if (!is_array($payload)) {
            return;
        }

        $clienteId = (int)($payload['clienteId'] ?? 0);
        $estadoId  = (int)($payload['estadoId'] ?? 0);
        $texto     = (string)($payload['texto'] ?? '');

        \Log::debug('🎯 Procesando estado-credito-actualizado', [
            'cliente_id' => $clienteId,
            'estado_id'  => $estadoId,
            'texto'      => $texto,
        ]);

        if ($clienteId <= 0) {
            return;
        }

        // Aquí refrescas la tabla de Filament
        // Opción A: refresh completo del componente
        $this->dispatch('$refresh');

        // Opción B (si usas InteractsWithTable y quieres ser explícito):
        // if (method_exists($this, 'resetTable')) {
        //     $this->resetTable();
        // }
    }

    public function onClienteCreado(array $payload = []): void
{
    $clienteId = $payload['clienteId'] ?? null;

    \Log::debug(' [ListClientes@onClienteCreado] Evento cliente-creado recibido', [
        'clienteId' => $clienteId,
    ]);

    if (method_exists($this, 'resetTable')) {
        $this->resetTable();   // Filament v3
    } else {
        $this->dispatch('$refresh');
    }
}

public function onGestorActualizado($payload = null): void
{
    \Log::debug(' [ListClientes] gestor-actualizado recibido', [
        'payload' => $payload,
    ]);

    if (!is_array($payload)) {
        return;
    }

    $this->resetTable();
}

}
