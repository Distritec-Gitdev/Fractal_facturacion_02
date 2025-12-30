<?php
// app/Filament/Resources/GestionClienteResource/Pages/FirmaGestionCliente.php

namespace App\Filament\Resources\GestionClienteResource\Pages;

use App\Filament\Resources\GestionClienteResource;
use App\Models\Cliente;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;

class FirmaGestionCliente extends Page
{
    // Inyecta el Resource correcto
    protected static string $resource = \App\Filament\Resources\GestionClienteResource::class;
    protected static string $view     = 'filament.resources.gestion-cliente-resource.pages.firma-gestion-cliente';

    // Recibimos el registro Cliente
    public Cliente $record;

    // Aquí guardaremos la imagen base64 desde el canvas
    public string $signatureDataUrl = '';

    // Livewire sabe resolver el binding {record}
    public function mount(Cliente $record): void
    {
        $this->record = $record;
    }

    public function saveSignature(): void
    {
        if (! $this->signatureDataUrl) {
            Notification::make()
                ->danger()
                ->title('Debes dibujar tu firma antes de guardar.')
                ->send();
            return;
        }

        // Extraer datos base64
        [$meta, $data] = explode(',', $this->signatureDataUrl);
        $binary = base64_decode($data);
        $filename = 'firmas/firma_'.$this->record->id_cliente.'.png';

        // Almacenar en storage/app/public/firmas
        Storage::disk('public')->put($filename, $binary);

        // Guardar la ruta en el cliente
        $this->record->update(['firma_path' => $filename]);

        Notification::make()
            ->success()
            ->title('Firma guardada correctamente')
            ->send();

        // Redirigir al índice
        $this->redirect(static::getResource()::getUrl('index'));
    }
}
