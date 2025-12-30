<?php

namespace App\Filament\Resources\GestionClienteResource\Pages;

use App\Filament\Resources\GestionClienteResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Forms\Form;
use App\Helpers\FormHelpers;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Log;

use App\Services\RecompraServiceValSimilar; 


class EditGestionClienteModal extends EditRecord
{
    protected static string $resource = GestionClienteResource::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre1')
                    ->label('Primer Nombre')
                    ->required(),
                Forms\Components\TextInput::make('apellido1')
                    ->label('Primer Apellido')
                    ->required(),
                Forms\Components\Select::make('ID_Identificacion_Cliente')
                    ->label('Tipo de Documento')
                    ->required()
                    ->options(\App\Models\TipoDocumento::pluck('desc_identificacion', 'ID_Identificacion_Tributaria'))
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('cedula')
                    ->label('Cédula')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('residencia_id_departamento')
                    ->label('Departamento de Residencia')
                    ->required()
                    ->options(FormHelpers::departamentosOptions())
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('codigoCiudad')
                    ->label('Municipio de Residencia')
                    ->required()
                    ->options(fn ($get) => FormHelpers::municipiosOptions($get('residencia_id_departamento')))
                    ->searchable()
                    ->preload(),
                // ...agrega aquí los demás campos simples que necesites...

                // Sección para dispositivos comprados (relación)
                Forms\Components\Section::make('Dispositivos Comprados')
                    ->schema([
                        Forms\Components\Repeater::make('dispositivosComprados') // <-- Nombre de la relación en el modelo Cliente
                            ->relationship() // <-- Indica que es un repetidor de una relación
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('id_marca')
                                        ->label('Marca del Dispositivo')
                                        // ->required() // Si es requerido, descomentar
                                        ->options(\App\Models\ZMarca::all()->pluck('name_marca', 'idmarca'))
                                        ->searchable()
                                        ->preload(),
                                    Forms\Components\Select::make('idmodelo')
                                        ->label('Modelo del Dispositivo')
                                        // ->required() // Si es requerido, descomentar
                                        ->options(\App\Models\ZModelo::all()->pluck('name_modelo', 'idmodelo'))
                                        ->searchable()
                                        ->preload(),
                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('idgarantia')
                                        ->label('Garantía')
                                        // ->required() // Si es requerido, descomentar
                                        ->options(\App\Models\ZGarantia::all()->pluck('garantia', 'idgarantia'))
                                        ->searchable()
                                        ->preload(),
                                    Forms\Components\TextInput::make('imei')
                                        ->label('IMEI')
                                        ->required()
                                        ->maxLength(30)
                                        ->validationMessages(['max_length' => 'Máximo 15 dígitos permitidos.'])
                                        ->extraAttributes(['class' => 'text-lg font-bold'])
                                        ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()'])
                                        ->afterStateUpdated(function (\Filament\Forms\Get $get, ?string $state) {
                                            $svc = app(RecompraServiceValSimilar::class);
                                            $resultado = $svc->procesarRecompraPorValorSimilar(2, $state, 'dispositivos_comprados', 'imei', 'El imei '. $state .' del dispositivo ya se encuentra registrado en otro lugar.', 'valor_exacto',['pattern' => 'contains', 'normalize' => true]);
                                        
                                        }),

                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('nombre_producto')
                                        ->label('Nombre del producto')
                                        ->required()
                                        ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                ]),
                            ])
                            ->columns(2) // Ajusta el número de columnas si es necesario
                            ->defaultItems(0) // O el número de elementos por defecto que desees
                            ->disableItemCreation() // Deshabilita si no quieres permitir agregar nuevos dispositivos desde aquí
                            ->disableItemDeletion(), // Deshabilita si no quieres permitir eliminar dispositivos desde aquí

                    ]),
                Forms\Components\Grid::make(2)->schema([
                    TextInput::make('empresa_labor')
                        ->label('Empresa en la que labora')
                        ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()'])
                        ->disabled(fn (Get $get): bool => $get('es_independiente'))
                        ->default(fn ($record) => $record?->trabajo?->empresa_labor)
                        ->afterStateHydrated(function ($set, $record) {
                            $set('empresa_labor', $record?->trabajo?->empresa_labor);
                        }),
                    TextInput::make('tel_empresa')
                        ->label('Teléfono de Empresa')
                        ->numeric()
                        ->maxLength(10)
                        ->mask('9999999999')
                        ->validationMessages([
                            'max_length' => 'Máximo 10 dígitos permitidos.',
                        ])
                        ->disabled(fn (Get $get): bool => $get('es_independiente'))
                        ->default(fn ($record) => $record?->trabajo?->num_empresa)
                        ->afterStateHydrated(function ($set, $record) {
                            $set('tel_empresa', $record?->trabajo?->num_empresa);
                        }),
                ]),
                Forms\Components\Checkbox::make('es_independiente')
                    ->label('¿Es independiente?')
                    ->default(fn ($record) => $record?->trabajo?->es_independiente)
                    ->afterStateHydrated(function ($state, callable $set, $record) {
                        $set('es_independiente', $record?->trabajo?->es_independiente);
                    })
                    ->reactive(),
            ]);
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        if (!$record) return;

        $state = $this->form->getState();
        Log::info('EditGestionClienteModal: afterSave - form state', $state);
        $empresa = $state['empresa_labor'] ?? null;
        $telefono = $state['tel_empresa'] ?? null;
        $independiente = $state['es_independiente'] ?? 0;
        Log::info('EditGestionClienteModal: afterSave - valores a guardar', [
            'empresa_labor' => $empresa,
            'tel_empresa' => $telefono,
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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        Log::info('EditGestionClienteModal mutateFormDataBeforeFill Data', ['data' => $data]);

        if (isset($data['clientesContacto']) && is_array($data['clientesContacto']) && count($data['clientesContacto']) > 0) {
            $empresa = $data['clientesContacto'][0]['empresa_labor'] ?? null;
            $numEmpresa = $data['clientesContacto'][0]['tel_empresa'] ?? null;
            $esIndependiente = $data['clientesContacto'][0]['es_independiente'] ?? false;
            $data['empresa_labor'] = $empresa;
            $data['num_empresa'] = $numEmpresa;
            $data['es_independiente'] = $esIndependiente;
        }

        Log::info('EditGestionClienteModal mutateFormDataBeforeFill Data after mutation', ['data' => $data]);

        return $data;
    }

    // Aquí puedes personalizar el formulario, validaciones, etc. para clientes modal.
} 