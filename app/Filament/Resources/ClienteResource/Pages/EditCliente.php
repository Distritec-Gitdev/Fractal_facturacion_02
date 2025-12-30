<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use App\Filament\Resources\GestionClienteResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use App\Filament\Widgets\ChatWidget; 
use Illuminate\Support\Facades\Auth;

class EditCliente extends EditRecord
{
    protected static string $resource = ClienteResource::class;
    protected ?int $oldEstadoId = null;

    /**
     * 👇 MONTA el ChatWidget en la página de edición
     */
    protected function getHeaderWidgets(): array
    {
        return [
            ChatWidget::class,
        ];
    }

    protected function viaToken(): bool
    {
        $userId = (int) auth()->id();
        $recId  = (int) ($this->record?->getKey() ?? 0);

        $isSigned = request()->hasValidSignature()
            && request()->query('via') === 'token'
            && (int) request()->query('uid') === $userId;

        $exp = (int) session("allow_edit_client.$recId", 0);
        $isSessionAllowed = $exp > 0 && now()->timestamp <= $exp;

        return $isSigned || $isSessionAllowed;
    }

    /**
     * Acciones de cabecera
     */
    protected function getHeaderActions(): array
    {
        $viaToken = $this->viaToken();

        return [
            Actions\DeleteAction::make()->visible(! $viaToken),

            //  BOTÓN Chat: usa los eventos que tu widget escucha
            Action::make('chat')
                ->icon('heroicon-o-chat-bubble-left')
                ->color('primary')
                ->tooltip('Abrir chat')
                // ->visible(fn () => auth()->user()?->hasRole('socio')) // opcional
                ->action(function (): void {
                    $id = (int) $this->record->id_cliente;

                    // Ayuda a que el widget tome el cliente en mount()
                    session()->put('chatClientId', $id);

                    // Enviar eventos DIRECTO al widget correcto
                    $this->dispatch('setClientId', clientId: $id)->to(ChatWidget::class);
                    $this->dispatch('openChat')->to(ChatWidget::class);
                }),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return $this->viaToken()
            ? []
            : parent::getBreadcrumbs();
    }

    public function mount($record): void
    {
        parent::mount($record);

        // Ventana de edición por URL firmada
        if (
            request()->hasValidSignature()
            && request()->query('via') === 'token'
            && (int) request()->query('uid') === (int) auth()->id()
        ) {
            session()->put(
                "allow_edit_client.{$this->record->getKey()}",
                now()->addMinutes(30)->timestamp
            );
        }

        //  Precarga el cliente para el widget (opcional pero útil)
        session()->put('chatClientId', (int) $this->record->id_cliente);

        // Logs de depuración
        $cliente = $this->getRecord();
        Log::info('EditCliente Mount:', [
            'cliente_id' => $cliente->id_cliente,
            'is_relation_loaded' => $cliente->relationLoaded('clientesNombreCompleto'),
            'clientesNombreCompleto_data' => $cliente->clientesNombreCompleto ? $cliente->clientesNombreCompleto->toArray() : null,
        ]);

        // Estado antes de editar (para detectar cambios)
        $this->oldEstadoId = $this->record->gestion?->ID_Estado_cr;
    }

    protected function getRedirectUrl(): string
    {
        if ($this->viaToken()) {
            return GestionClienteResource::getUrl('token', [
                'record' => $this->record->getKey(),
            ]);
        }
        return static::getResource()::getUrl('index');
    }

    public function hydrate(): void
    {
        $cliente = $this->getRecord();
        Log::info('EditCliente Hydrate:', [
            'cliente_id' => $cliente->id_cliente ?? 'N/A (record not set yet)',
            'is_relation_loaded' => $cliente ? $cliente->relationLoaded('clientesNombreCompleto') : false,
            'clientesNombreCompleto_data' => ($cliente && $cliente->clientesNombreCompleto) ? $cliente->clientesNombreCompleto->toArray() : null,
        ]);
    }

    protected function afterSave(): void
    {
        // Cargar relación ya guardada
        $this->record->load([
            'gestion.estadoCredito' => fn ($q) => $q->select('ID_Estado_cr', 'Estado_Credito'),
        ]);

        $newEstadoId = $this->record->gestion?->ID_Estado_cr;

        // Solo dispara evento si cambió
        if ((int)($newEstadoId ?? 0) !== (int)($this->oldEstadoId ?? 0)) {
            $texto = optional($this->record->gestion?->estadoCredito)->Estado_Credito;

            event(new \App\Events\EstadoCreditoUpdated(
                clienteId: (int) $this->record->id_cliente,
                estadoId: $newEstadoId ? (int) $newEstadoId : null,
                estadoTexto: $texto,
                userId: Auth::id(),
            ));

            // Actualiza el “antes” por si guardan de nuevo
            $this->oldEstadoId = $newEstadoId;
        }
    }
}
