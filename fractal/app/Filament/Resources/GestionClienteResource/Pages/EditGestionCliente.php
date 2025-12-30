<?php

namespace App\Filament\Resources\GestionClienteResource\Pages;

use App\Filament\Resources\GestionClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Get;

class EditGestionCliente extends EditRecord
{
    protected static string $resource = GestionClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
{
    // No cambiar el owner en edición (opcional)
    $data['user_id'] = $this->record->user_id ?? auth()->id();
    return $data;
}


    protected function afterSave(): void
    {
        $record = $this->record;
        if (!$record) return;

        $state = $this->form->getState();
        Log::info('EditGestionCliente: afterSave - form state', $state);

        // Los campos laborales ahora se manejan directamente desde clientesContacto Repeater
        $contactoData = $state['clientesContacto'] ?? null; // Acceder directamente a la data, no al índice [0]

        if ($contactoData) {
            $empresa = $contactoData['empresa_labor'] ?? null;
            $telefono = $contactoData['tel_empresa'] ?? null;
            $independiente = $contactoData['es_independiente'] ?? 0;

            Log::info('EditGestionCliente: afterSave - valores a guardar de contacto', [
                'empresa_labor' => $empresa,
                'num_empresa' => $telefono,
                'es_independiente' => $independiente,
            ]);
            $trabajo = $record->trabajo;
            if ($trabajo) {
                $trabajo->update([
                    'empresa_labor' => $empresa,
                    'num_empresa' => $telefono,
                    'es_independiente' => $independiente,
                ]);
            } else {
                \App\Models\ClienteTrabajo::create([
                    'id_cliente' => $record->id_cliente,
                    'empresa_labor' => $empresa,
                    'num_empresa' => $telefono,
                    'es_independiente' => $independiente,
                ]);
            }
        }
    }

    // Eliminar mutateFormDataBeforeFill ya que los campos se manejan directamente en el Repeater
    // protected function mutateFormDataBeforeFill(array $data): array
    // {
    //     Log::info('EditCliente mutateFormDataBeforeFill Data', ['data' => $data]);
    //
    //     if (isset($data['clientesContacto']) && is_array($data['clientesContacto']) && count($data['clientesContacto']) > 0) {
    //         $empresa = $data['clientesContacto'][0]['empresa_labor'] ?? null;
    //         $numEmpresa = $data['clientesContacto'][0]['tel_empresa'] ?? null;
    //         $esIndependiente = $data['clientesContacto'][0]['es_independiente'] ?? false;
    //         $data['empresa_labor'] = $empresa;
    //         $data['num_empresa'] = $numEmpresa;
    //         $data['es_independiente'] = $esIndependiente;
    //     }
    //
    //     Log::info('EditCliente mutateFormDataBeforeFill Data after mutation', ['data' => $data]);
    //
    //     return $data;
    // }

    protected function getFormSchema(): array
    {
        // Eliminar la definición de campos duplicados aquí, ya que están en el Repeater de GestionClienteResource
        return [
            // Grid::make(2)->schema([
            //     TextInput::make('empresa_labor')
            //         ->label('Empresa en la que labora')
            //         ->default(fn ($record) => $record->trabajo->empresa_labor ?? '')
            //         ->afterStateHydrated(function ($component, $state, $record) {
            //             $component->state($record->trabajo->empresa_labor ?? '');
            //         })
            //         ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()'])
            //         ->disabled(fn (Get $get): bool => $get('es_independiente'))
            //         ->reactive(),
            //     TextInput::make('tel_empresa')
            //         ->label('Teléfono de Empresa')
            //         ->numeric()
            //         ->maxLength(10)
            //         ->mask('9999999999')
            //         ->validationMessages([
            //             'max_length' => 'Máximo 10 dígitos permitidos.',
            //         ])
            //         ->default(fn ($record) => $record->trabajo->num_empresa ?? '')
            //         ->afterStateHydrated(function ($component, $state, $record) {
            //             $component->state($record->trabajo->num_empresa ?? '');
            //         })
            //         ->disabled(fn (Get $get): bool => $get('es_independiente'))
            //         ->reactive(),
            // ]),
            // Toggle::make('es_independiente')
            //     ->label('¿Es independiente?')
            //     ->default(fn ($record) => $record->trabajo->es_independiente ?? 0)
            //     ->afterStateHydrated(function ($component, $state, $record) {
            //         $component->state($record->trabajo->es_independiente ?? 0);
            //     })
            //     ->reactive(),
        ];
    }
}
