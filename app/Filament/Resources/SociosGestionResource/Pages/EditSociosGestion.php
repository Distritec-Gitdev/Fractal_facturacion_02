<?php

namespace App\Filament\Resources\SociosGestionResource\Pages;

use App\Filament\Resources\SociosGestionResource;
use App\Filament\Resources\GestionClienteResource;
use App\Filament\Widgets\ChatWidget;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditSociosGestion extends EditRecord
{
    protected static string $resource = SociosGestionResource::class;

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

    protected function getHeaderActions(): array
    {
        $viaToken = $this->viaToken();

        return [
            Actions\DeleteAction::make()->visible(! $viaToken),

            Action::make('chat')
                ->label('Chat')
                ->icon('heroicon-o-chat-bubble-left')
                ->color('primary')
                ->modal(false) // <-- evita modal en todas las versiones
                ->action(function () {
                    $clientId = $this->record->id_cliente;

                    // Livewire v3
                    if (method_exists($this, 'dispatch')) {
                        $this->dispatch('setClientId', $clientId)->to(ChatWidget::class);
                        $this->dispatch('openChat')->to(ChatWidget::class);
                        return;
                    }

                    // Livewire v2 (fallback)
                    if (method_exists($this, 'emitTo')) {
                        $this->emitTo(ChatWidget::class, 'setClientId', $clientId);
                        $this->emitTo(ChatWidget::class, 'openChat');
                    }
                }),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return $this->viaToken() ? [] : parent::getBreadcrumbs();
    }

    public function mount($record): void
    {
        parent::mount($record);

        if (
            request()->hasValidSignature()
            && request()->query('via') === 'token'
            && (int) request()->query('uid') === (int) auth()->id()
        ) {
            session()->put(
                "allow_edit_client.{$this->record->getKey()}",
                now()->addMinutes(10)->timestamp
            );
        }

        $cliente = $this->getRecord();
        Log::info('EditCliente Mount:', [
            'cliente_id' => $cliente->id_cliente,
            'is_relation_loaded' => $cliente->relationLoaded('clientesNombreCompleto'),
            'clientesNombreCompleto_data' => $cliente->clientesNombreCompleto ? $cliente->clientesNombreCompleto->toArray() : null,
        ]);
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
            'cliente_id' => $cliente->id_cliente ?? 'N/A',
            'is_relation_loaded' => $cliente ? $cliente->relationLoaded('clientesNombreCompleto') : false,
            'clientesNombreCompleto_data' => ($cliente && $cliente->clientesNombreCompleto) ? $cliente->clientesNombreCompleto->toArray() : null,
        ]);
    }

    /**
     * Inyecta el widget (sin columnSpanFull, sin make con props).
     */
    protected function getFooterWidgets(): array
    {
        return [
            ChatWidget::class, // súper compatible
        ];
    }
}
