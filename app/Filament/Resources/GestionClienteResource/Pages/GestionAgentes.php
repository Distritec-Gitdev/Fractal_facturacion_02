<?php

namespace App\Filament\Resources\GestionClienteResource\Pages;

use App\Filament\Resources\GestionClienteResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use App\Models\ZDepartamentos;
use App\Models\ZMunicipios;
use App\Models\ZMarca;
use App\Models\ZModelo;
use App\Models\ZGarantia;
use App\Models\ZPago;
use App\Models\ZPlataformaCredito;
use App\Models\ZComision;
use App\Models\InfTrab;
use App\Models\ZPdvAgenteDistritec;
use Filament\Notifications\Notification;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\TipoDocumento;
use Illuminate\Support\Facades\Redirect;
use \Livewire\Livewire;
use Illuminate\Support\Carbon;
use App\Services\TercerosApiService;
use Filament\Forms\Components\HasOne;
use App\Models\Agentes;

Use App\Models\Zcuotas;

use App\Services\RecompraServiceValSimilar; 

class GestionAgentes extends CreateRecord
{
    protected static string $resource = GestionClienteResource::class;

    // Anular el método authorizeAccess para permitir el acceso directo a esta página
    protected function authorizeAccess(): void
    {
        // Aquí puedes poner tu lógica de permisos específica para esta página si la necesitas.
        // Por ahora, simplemente no lanzamos una excepción para permitir el acceso.
        // Si usas Spatie, podrías comprobar un permiso específico aquí:
        // if (! auth()->user()->can('acceder gestion agentes')) {
        //     abort(403);
        // }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                 Hidden::make('user_id')
                ->dehydrated(true)
                ->default(fn () => auth()->id()),
                Wizard::make([
                    Step::make('Datos del Cliente')
                        ->schema([
                            Hidden::make('fecha_registro')
                                ->default(now()->toDateString()),
                            Hidden::make('hora')
                                ->default(now()->format('H:i:s')),
                            Select::make('ID_Canal_venta')
                                ->label('Canal de Venta')
                                ->required()
                                ->options(\App\Models\CanalVentafd::pluck('canal', 'ID_Canal_venta'))
                                ->searchable()
                                ->preload(),
                         // Select::make('ID_Identificacion_Cliente')
                         //     ->label('Tipo de Documento')
                         //     ->required()
                         //     ->options(\App\Models\TipoDocumento::pluck('desc_identificacion', 'ID_Identificacion_Tributaria'))
                         //     ->searchable()
                         //     ->preload()
                         //     //->disabled()    
                         //     ->dehydrated()
                         //     ->dehydrateStateUsing(fn ($state) => (int) $state),

                         Select::make('ID_Identificacion_Cliente')
                                ->label('Tipo de Documento')
                                ->required()
                                ->options(\App\Models\TipoDocumento::pluck('desc_identificacion', 'ID_Identificacion_Tributaria'))
                                ->searchable()
                               // ->disabled()    
                                ->dehydrated() 
                                ->preload(),
                            TextInput::make('cedula')
                                ->extraAttributes(['class' => 'text-lg font-bold'])
                                ->label('Cédula')
                                ->required()
                                ->readOnly()
                                ->numeric()
                                ->minLength(5)
                                ->maxLength(15)
                                ->validationMessages([
                                    'max_length' => 'Máximo 15 dígitos permitidos.',
                                    'min_length' => 'Mínimo 5 dígitos permitidos.',
                                ])
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                    \Log::info('AFTER STATE UPDATED cedula', ['state' => $state]);
                                    if ($state) {
                                        $notification = Notification::make()
                                            ->title('Cargando datos...')
                                            ->persistent();
                                        $notification->send();
                                        try {
                                            $apiService = app(\App\Services\TercerosApiService::class);
                                            $codigoTipoDoc = \App\Filament\Resources\GestionClienteResource::mapTipoDocumentoToCodigo($get('ID_Identificacion_Cliente') ?? '');
                                            \Log::info('Consultando API', ['cedula' => $state, 'codigoTipoDoc' => $codigoTipoDoc]);
                                            $tercero = $apiService->buscarPorCedula($state, $codigoTipoDoc);
                                            \Log::info('Respuesta de la API', ['tercero' => $tercero]);
                                            if ($tercero) {
                                                // Llenar datos personales
                                                \Log::info('Mapeando datos personales', [
                                                    'nombre1' => $tercero['nombre1'] ?? null,
                                                    'nombre2' => $tercero['nombre2'] ?? null,
                                                    'apellido1' => $tercero['apellido1'] ?? null,
                                                    'apellido2' => $tercero['apellido2'] ?? null,
                                                ]);
                                                $set('clientesNombreCompleto.Primer_nombre_cliente', isset($tercero['nombre1']) ? (string) $tercero['nombre1'] : '');
                                                $set('clientesNombreCompleto.Segundo_nombre_cliente', isset($tercero['nombre2']) ? (string) $tercero['nombre2'] : '');
                                                $set('clientesNombreCompleto.Primer_apellido_cliente', isset($tercero['apellido1']) ? (string) $tercero['apellido1'] : '');
                                                $set('clientesNombreCompleto.Segundo_apellido_cliente', isset($tercero['apellido2']) ? (string) $tercero['apellido2'] : '');
                                                // Llenar datos de contacto
                                                \Log::info('Mapeando datos de contacto', [
                                                    'correo' => $tercero['email'] ?? null,
                                                    'celular' => $tercero['celular'] ?? null,
                                                    'telefono1' => $tercero['telefono1'] ?? null,
                                                    'direccion' => $tercero['direccion'] ?? null,
                                                ]);
                                                $set('clientesContacto.correo', $tercero['email'] ?? '');
                                                $set('clientesContacto.tel', $tercero['celular'] ?? '');
                                                $set('clientesContacto.tel_alternativo', $tercero['telefono1'] ?? '');
                                                $set('clientesContacto.direccion', $tercero['direccion'] ?? '');
                                                // Departamento y municipio residencia
                                                $idDepartamentoResidencia = null;
                                                $nombreDepartamentoApi = $tercero['descripcionZona'] ?? null;
                                                if ($nombreDepartamentoApi) {
                                                    $departamento = \App\Models\ZDepartamentos::where('name_departamento', $nombreDepartamentoApi)->first();
                                                    if ($departamento) {
                                                        $idDepartamentoResidencia = $departamento->id;
                                                    }
                                                }
                                                \Log::info('Departamento residencia', ['nombre' => $nombreDepartamentoApi, 'id' => $idDepartamentoResidencia]);
                                                $set('clientesContacto.residencia_id_departamento', $idDepartamentoResidencia);
                                                $set('clientesContacto.residencia_id_municipio', $tercero['codigoCiudad'] ?? null);
                                                // Fecha de expedición
                                                $fechaExpedicionFormatted = null;
                                                $fechaExpedicionApi = $tercero['fechaExpedicionCedula'] ?? null;
                                                if ($fechaExpedicionApi) {
                                                    try {
                                                        $fecha = new \DateTime($fechaExpedicionApi);
                                                        $fechaExpedicionFormatted = $fecha->format('Y-m-d');
                                                    } catch (\Exception $e) {
                                                        \Log::error('Error parseando fecha de expedición', ['fecha_api' => $fechaExpedicionApi, 'error' => $e->getMessage()]);
                                                    }
                                                }
                                                \Log::info('Fecha expedición', ['api' => $fechaExpedicionApi, 'formateada' => $fechaExpedicionFormatted]);
                                                $set('clientesContacto.fecha_expedicion', $fechaExpedicionFormatted);
                                                // Empresa y teléfono empresa
                                                $set('clientesContacto.empresa_labor', $tercero['nombreComercial'] ?? '');
                                                $set('clientesContacto.tel_empresa', $tercero['telefono_empresa'] ?? '');
                                                // Es independiente (si tienes lógica para esto)
                                                $set('clientesContacto.es_independiente', false);
                                                \Log::info('Formulario después de set', [
                                                    'clientesNombreCompleto' => $get('clientesNombreCompleto'),
                                                    'clientesContacto' => $get('clientesContacto'),
                                                ]);
                                                Notification::make()
                                                    ->title('Cliente encontrado, datos cargados')
                                                    ->success()
                                                    ->send();
                                            } else {
                                                Notification::make()
                                                    ->title('No se encontraron datos para esta cédula')
                                                    ->warning()
                                                    ->seconds(3)
                                                    ->send();
                                            }
                                        } catch (Exception $e) {
                                            Notification::make()
                                                ->title('Error al consultar los datos')
                                                ->body($e->getMessage())
                                                ->danger()
                                                ->seconds(5)
                                                ->send();
                                            \Log::error('Error al consultar los datos del cliente', [
                                                'exception' => $e,
                                                'message' => $e->getMessage()
                                            ]);
                                        }
                                    }
                                }),
                            
                            Grid::make(2)->schema([
                                 Select::make('id_departamento')
                                    ->label('Departamento Expedición Documento')
                                    ->placeholder('Seleccione un departamento')
                                    ->required()
                                    ->options(
                                        ZDepartamentos::query()
                                            ->orderBy('name_departamento')
                                            ->pluck('name_departamento', 'id')
                                            ->toArray()
                                    )
                                    ->searchable()
                                    ->preload()   // carga el listado, pero no selecciona ninguno
                                    ->reactive()
                                    ->default(null)
                                    ->afterStateHydrated(function (Select $component, $state) {
                                        // Si es página de "crear" (no hay record montado), forzamos vacío
                                        $livewire = $component->getContainer()->getLivewire();
                                        $isCreate = blank(data_get($livewire, 'record'));
                                        if ($isCreate) {
                                            $component->state(null);
                                        }
                                    }),

                                Select::make('id_municipio')
                                    ->label('Municipio Expedición Documento')
                                    ->placeholder('Seleccione un municipio')
                                    ->required()
                                    ->options(fn (Get $get) => ZMunicipios::query()
                                        ->where('departamento_id', $get('id_departamento')) // 👈 directo, sin variable intermedia
                                        ->orderBy('name_municipio')
                                        ->pluck('name_municipio', 'id')
                                        ->toArray()
                                    )
                                    ->searchable()
                                    // No preloads para no traer nada hasta que elijan departamento
                                    ->default(null)
                                    ->afterStateHydrated(function (Select $component, $state) {
                                        // Si es "crear", no cargamos nada al inicio
                                        $livewire = $component->getContainer()->getLivewire();
                                        $isCreate = blank(data_get($livewire, 'record'));
                                        if ($isCreate) {
                                            $component->state(null);
                                        }
                                    })
                                    ->disabled(fn (Get $get) => blank($get('id_departamento'))),
                            ]),
                        
                            DatePicker::make('fecha_nac')->label('Fecha de Nacimiento')->format('Y-m-d')->displayFormat('d/m/Y')->required()->maxDate(Carbon::yesterday())   // bloquea hoy y futuras en el picker
                                    ->rule('before:today'),         // valida en servidor que sea < hoy
                        
                            Section::make('Nombre Completo')
                                ->relationship('clientesNombreCompleto')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('Primer_nombre_cliente')->label('Primer Nombre')->readOnly()->required()->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        TextInput::make('Segundo_nombre_cliente')->label('Segundo Nombre')->readOnly()->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                    ]),
                                    Grid::make(2)->schema([
                                        TextInput::make('Primer_apellido_cliente')->label('Primer Apellido')->readOnly()->required()->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        TextInput::make('Segundo_apellido_cliente')->label('Segundo Apellido')->readOnly()->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                    ]),
                                ]),

                            Section::make('Datos Personales')
                                ->relationship('clientesContacto')
                                ->schema([
                                    Grid::make(2)->schema([
                                        DatePicker::make('fecha_expedicion')
                                            ->label('Fecha de expedición')
                                            ->hint('En que fecha saco la cedula?')
                                            ->hintColor('info')
                                            ->hintIcon('phosphor-push-pin-bold')
                                            ->format('Y-m-d')
                                            ->displayFormat('d/m/Y')
                                            ->required()
                                           // ->nullable()
                                            ->maxDate(Carbon::yesterday())   // bloquea hoy y futuras en el picker
                                            ->rule('before:today'),         // valida en servidor que sea < hoy
                                         // Campo deshabilitado: se llena desde la API
                                        TextInput::make('correo')
                                            ->label('Correo')
                                            ->email()
                                            ->maxLength(255)
                                            ->required(),
                                            //->readOnly(),
                                    ]),
                                    Grid::make(2)->schema([
                                        TextInput::make('tel')
                                            ->label('Teléfono')
                                            ->required()
                                            ->numeric()
                                            //->readOnly()
                                            ->maxLength(10)
                                            ->mask('9999999999')
                                            ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()'])
                                            ->validationMessages([
                                                'max_length' => 'Máximo 10 dígitos permitidos.',
                                            ]),
                                        TextInput::make('tel_alternativo')
                                        ->label('Teléfono Alternativo')
                                        ->tel()                    // teclado numérico en móviles (no valida por sí solo)
                                        ->required()
                                        ->minLength(10)            // exactamente 10
                                        ->maxLength(10)
                                       
                                        ->validationAttribute('teléfono alternativo')
                                        ->helperText('Debe tener exactamente 10 dígitos.')
                                        // Frontend: forzar solo dígitos y cortar a 10
                                        ->extraInputAttributes([
                                            'inputmode' => 'numeric',
                                            'pattern'   => '\d{10}',
                                            'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                        ])
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                            $tel             = $get('tel');                // teléfono principal
                                            $telefonoEmpresa = $get('num_empresa');   // teléfono empresa

                                             $clean = $state ? preg_replace('/\D+/', '', (string) $state) : null;

                                            // Si por alguna razón llega con no-dígitos o longitud incorrecta tras blur, limpia y avisa
                                           
                                                  if (! $clean || strlen($clean) !== 10) {
                                                Notification::make()
                                                    ->title('Formato inválido')
                                                    ->body('El teléfono alternativo debe tener exactamente 10 dígitos numéricos.')
                                                    ->warning()
                                                    ->send();
                                                $set('tel_alternativo', '');
                                                return;
                                                  }
                                            

                                            if ($state && $tel && $state === $tel) {
                                                Notification::make()
                                                    ->title('Error de validación')
                                                    ->body('El teléfono alternativo no puede ser igual al teléfono principal.')
                                                    ->danger()
                                                    ->send();
                                                $set('tel_alternativo', '');
                                                return;
                                            }

                                            if ($state && $telefonoEmpresa && $state === $telefonoEmpresa) {
                                                Notification::make()
                                                    ->title('Error de validación')
                                                    ->body('El teléfono alternativo no puede ser igual al teléfono de la empresa.')
                                                    ->danger()
                                                    ->send();
                                                $set('tel_alternativo', '');
                                                return;
                                            }

                                            //---------- EVALUAR RECOMPRA -----------
                                            $svc = app(RecompraServiceValSimilar::class);
                                            $valor = session('es_recompra');

                                            Log::error('Valor de esRecompra en GestionAgentes'.  $valor);


                                            $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                            $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel_alternativo', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);

                                            $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal1', 'Celular_rf1', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                            $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal2', 'Celular_rf2', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                            //---------------------------------------
                                        }),
                                    ]),
                                    Grid::make(2)->schema([
                                        Select::make('residencia_id_departamento')
                                            ->label('Departamento Residencia')
                                            ->required()
                                            ->options(\App\Models\ZDepartamentos::all()->pluck('name_departamento', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->distinct()
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('residencia_id_municipio', null);
                                            }),
    
                                        Select::make('residencia_id_municipio')
                                            ->label('Municipio Residencia')
                                            ->required()
                                            ->options(function (Get $get) {
                                                $departamentoId = $get('residencia_id_departamento');
                                                if ($departamentoId) {
                                                    return \App\Models\ZMunicipios::where('departamento_id', $departamentoId)->pluck('name_municipio', 'id');
                                                }
                                                return [];
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->distinct()
                                            ->required(),
                                    ]),
                                    Grid::make(2)->schema([
                                        TextInput::make('direccion')->label('Dirección') ->readOnly()->required()->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                    ]),
                                   
                                ])
                                ->columns(2),
                        ]),
                    
                     Step::make('Datos trabajo')
                                ->schema([
                                        // Campo editable: empresa
                                        Repeater::make('clienteTrabajo')
                                        ->relationship('clienteTrabajo')
                                        ->label('Datos de Contacto y Laborales')
                                        ->minItems(1)
                                        ->maxItems(1)
                                           ->schema([
                                        TextInput::make('empresa_labor')
                                            ->label('Empresa')
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            // 👇 Solo obligatoria si NO es independiente
                                            ->required(fn (Get $get) => ! (bool) $get('es_independiente'))
                                            // 👇 No se guarda cuando es independiente
                                            ->dehydrated(fn (Get $get) => ! (bool) $get('es_independiente'))
                                            // 👇 Se deshabilita cuando es independiente
                                            ->disabled(fn (Get $get) => (bool) $get('es_independiente'))
                                            ->helperText(fn (Get $get) => $get('es_independiente') ? 'INDEPENDIENTE' : null),

                                        // Campo editable: teléfono empresa
                                        TextInput::make('num_empresa')
                                        ->label('Teléfono Empresa')
                                        ->tel()
                                        ->minLength(10)
                                        ->maxLength(10)
                                        // Solo requerido / guardado si NO es independiente:
                                        ->required(fn (Get $get) => ! (bool) $get('es_independiente'))
                                        ->dehydrated(fn (Get $get) => ! (bool) $get('es_independiente'))
                                        ->disabled(fn (Get $get) => (bool) $get('es_independiente'))
                                        ->validationAttribute('teléfono de la empresa')
                                        ->helperText('Debe tener exactamente 10 dígitos.')
                                        // Frontend: permitir solo números y cortar a 10
                                        ->extraInputAttributes([
                                            'inputmode' => 'numeric',
                                            'pattern'   => '\d{10}',
                                            'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                        ])
                                        // ✅ Validar solo al perder foco
                                        ->live(onBlur: true)

                                        ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                            // No validar si es independiente
                                            if ($get('es_independiente')) {
                                                return;
                                            }

                                              $clean = $state ? preg_replace('/\D+/', '', (string) $state) : null;

                                                // Validar formato inmediatamente (10 dígitos)
                                                if (! $clean || strlen($clean) !== 10) {
                                                    Notification::make()
                                                        ->title('Formato inválido')
                                                        ->body('El celular de la referencia 1 debe tener exactamente 10 dígitos.')
                                                        ->warning()
                                                        ->send();
                                                    $set('Celular_rf1', '');
                                                    $set('../../rf1_cel_clean', null);
                                                    return;
                                                }

                                            $norm = fn ($v) => preg_replace('/\D+/', '', (string) $v);
                                            $empresa = $norm($state);

                                            // Permitir teclear hasta 10 sin bloquear
                                            if ($empresa === '') return;

                                            // Si pegaron más de 10, recorta
                                            if (strlen($empresa) > 10) {
                                                $empresa = substr($empresa, 0, 10);
                                                $set('num_empresa', $empresa);
                                            }

                                            // Solo comparar con 10 dígitos completos
                                            if (strlen($empresa) < 10) return;

                                            // 👇 OJO: rutas RELATIVAS al otro repeater
                                            $telPrincipal   = $norm($get('../../clientesContacto.tel'));
                                            $telAlternativo = $norm($get('../../clientesContacto.tel_alternativo'));

                                            if ($telPrincipal !== '' && $empresa === $telPrincipal) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Error de validación')
                                                    ->body('El teléfono de la empresa no puede ser igual al teléfono principal.')
                                                    ->danger()
                                                    ->send();
                                                $set('num_empresa', '');
                                                return;
                                            }

                                            if ($telAlternativo !== '' && $empresa === $telAlternativo) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Error de validación')
                                                    ->body('El teléfono de la empresa no puede ser igual al teléfono alternativo.')
                                                    ->danger()
                                                    ->send();
                                                $set('num_empresa', '');
                                                return;
                                            }

                                            //---------- EVALUAR RECOMPRA -----------
                                            $svc = app(RecompraServiceValSimilar::class);
                                            $valor = session('es_recompra');
                                            $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                            $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel_alternativo', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);

                                            $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal1', 'Celular_rf1', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                            $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal2', 'Celular_rf2', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                            //---------------------------------------
                                        }),

                                       


                                        // Control: Es Independiente
                                        Checkbox::make('es_independiente')
                                            ->label('Es Independiente')
                                            ->reactive()
                                            ->afterStateUpdated(function (bool $state, Set $set) {
                                                // Si marcan Independiente, limpiamos los campos y los ignoramos al guardar
                                                if ($state) {
                                                    $set('empresa_labor', null);
                                                    $set('num_empresa', null);
                                                }
                                            }),

                                         ])
                                          ->deletable(false)   // 👈 oculta el botón de eliminar
                                        ->addable(false)     // 👈 opcional: oculta el botón de agregar
                                        ->reorderable(false) // 👈 opcional: oculta el handle de arrastrar
                                        ->cloneable(false), // 👈 opcional: oculta clonar

                                    ]),

                    Step::make('Referencia 1')
                        ->schema([
                            Repeater::make('referenciasPersonales1')
                                ->relationship()
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('Primer_Nombre_rf1')->label('Primer Nombre')->required()->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        TextInput::make('Segundo_Nombre_rf1')->label('Segundo Nombre')->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        TextInput::make('Primer_Apellido_rf1')->label('Primer Apellido')->required()->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        TextInput::make('Segundo_Apellido_rf1')->label('Segundo Apellido')->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        TextInput::make('Celular_rf1')
                                             ->label('Celular')
                                            ->required()
                                            ->minLength(10)                     // exactamente 10
                                            ->maxLength(10)
                                            ->rule('regex:/^\d{10}$/')          // valida servidor: solo 10 dígitos
                                            ->validationAttribute('celular de la referencia 1')
                                            ->validationMessages([
                                                'required' => 'El celular de la referencia 1 es obligatorio.',
                                                'min'      => 'Debe tener exactamente 10 dígitos.',
                                                'max'      => 'Debe tener exactamente 10 dígitos.',
                                                'regex'    => 'Solo se permiten números (10 dígitos).',
                                            ])
                                            // Frontend: forzar dígitos y recortar a 10
                                            ->extraInputAttributes([
                                                'inputmode' => 'numeric',
                                                'pattern'   => '\d{10}',
                                                'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                            ])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                                // Normalizar a solo dígitos
                                                $clean = $state ? preg_replace('/\D+/', '', (string) $state) : null;

                                                // Validar formato inmediatamente (10 dígitos)
                                                if (! $clean || strlen($clean) !== 10) {
                                                    Notification::make()
                                                        ->title('Formato inválido')
                                                        ->body('El celular de la referencia 1 debe tener exactamente 10 dígitos.')
                                                        ->warning()
                                                        ->send();
                                                    $set('Celular_rf1', '');
                                                    $set('../../rf1_cel_clean', null);
                                                    return;
                                                }

                                                // Guardar versión normalizada en buffer global para Referencia 2
                                                $set('../../rf1_cel_clean', $clean);

                                                $norm = fn ($v) => preg_replace('/\D+/', '', (string) $v);

                                                $contactos       = $get('../../clientesContacto') ?? [];

                                                // Traer teléfonos del cliente y normalizarlos
                                                $tel             = $get('../../clientesContacto.tel');
                                                $telAlternativo  = $get('../../clientesContacto.tel_alternativo');
                                                // clienteTrabajo
                                                $trabajos        = $get('../../clienteTrabajo') ?? [];
                                                $telefonoEmpresa = '';
                                                if (is_array($trabajos)) {
                                                    foreach ($trabajos as $row) {
                                                        $telefonoEmpresa = $norm($row['num_empresa'] ?? '');
                                                        break; // solo el primero
                                                    }
                                                }

                                                $telClean            = $tel             ? preg_replace('/\D+/', '', (string) $tel) : null;
                                                $telAlternativoClean = $telAlternativo  ? preg_replace('/\D+/', '', (string) $telAlternativo) : null;
                                                $telefonoEmpresaClean= $telefonoEmpresa ? preg_replace('/\D+/', '', (string) $telefonoEmpresa) : null;

                                                // Validar que NO sea igual a ningún teléfono del cliente (comparación sobre dígitos)
                                                if ($telClean && $clean === $telClean) {
                                                    Notification::make()
                                                        ->title('Error de validación')
                                                        ->body('El celular de la referencia 1 no puede ser igual al teléfono principal del cliente.')
                                                        ->danger()
                                                        ->send();
                                                    $set('Celular_rf1', '');
                                                    $set('../../rf1_cel_clean', null);
                                                    return;
                                                }

                                                if ($telAlternativoClean && $clean === $telAlternativoClean) {
                                                    Notification::make()
                                                        ->title('Error de validación')
                                                        ->body('El celular de la referencia 1 no puede ser igual al teléfono alternativo del cliente.')
                                                        ->danger()
                                                        ->send();
                                                    $set('Celular_rf1', '');
                                                    $set('../../rf1_cel_clean', null);
                                                    return;
                                                }

                                                if ($telefonoEmpresaClean && $clean === $telefonoEmpresaClean) {
                                                    Notification::make()
                                                        ->title('Error de validación')
                                                        ->body('El celular de la referencia 1 no puede ser igual al teléfono de la empresa del cliente.')
                                                        ->danger()
                                                        ->send();
                                                    $set('Celular_rf1', '');
                                                    $set('../../rf1_cel_clean', null);
                                                    return;
                                                }
                                                //---------- EVALUAR RECOMPRA -----------
                                                $svc = app(RecompraServiceValSimilar::class);
                                                $valor = session('es_recompra');
                                                $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel_alternativo', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);

                                                $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal1', 'Celular_rf1', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal2', 'Celular_rf2', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                //---------------------------------------
                                            }),
                                    ]),
                                  //  Grid::make(2)->schema([
                                  //      Select::make('Parentesco_rf1')
                                  //          ->label('Parentesco')
                                  //          ->default(26)
                                  //          ->disabled()
                                  //          ->dehydrated(),
                                  //      Select::make('idtiempo_conocerlonum')
                                  //          ->label('Tiempo de Conocerlo')
                                  //          ->default(21)
                                  //          ->disabled()
                                  //          ->dehydrated(),
                                  //      Select::make('idtiempoconocerlo')
                                  //          ->label('Tiempo de Conocerlo')
                                  //          ->default(5)
                                  //          ->disabled()
                                  //          ->dehydrated(),
                                  //  ]),
                                ])
                                ->maxItems(1)
                                ->disableItemCreation()
                                ->disableItemDeletion(),
                        ]),

                    Step::make('Referencia 2')
                        ->schema([
                            Repeater::make('referenciasPersonales2')
                                ->relationship()
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('Primer_Nombre_rf2')->label('Primer Nombre')->required()->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        TextInput::make('Segundo_Nombre_rf2')->label('Segundo Nombre')->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        TextInput::make('Primer_Apellido_rf2')->label('Primer Apellido')->required()->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        TextInput::make('Segundo_Apellido_rf2')->label('Segundo Apellido')->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        TextInput::make('Celular_rf2')
                                            ->label('Celular')
                                            ->required()
                                            ->minLength(10)                 // exactamente 10
                                            ->maxLength(10)
                                            ->rule('regex:/^\d{10}$/')      // servidor: solo 10 dígitos
                                            ->validationAttribute('celular de la referencia 2')
                                            ->validationMessages([
                                                'required' => 'El celular de la referencia 2 es obligatorio.',
                                                'min'      => 'Debe tener exactamente 10 dígitos.',
                                                'max'      => 'Debe tener exactamente 10 dígitos.',
                                                'regex'    => 'Solo se permiten números (10 dígitos).',
                                            ])
                                            // Frontend: forzar dígitos y cortar a 10
                                            ->extraInputAttributes([
                                                'inputmode' => 'numeric',
                                                'pattern'   => '\d{10}',
                                                'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                            ])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                                if (!$state) {
                                                    return;
                                                }

                                                // Obtener todos los datos del formulario para debug
                                                \Illuminate\Support\Facades\Log::info('Todos los datos del formulario (rf2):', $get());

                                                // Normalizar a solo dígitos
                                                $stateClean = preg_replace('/\D+/', '', (string) $state);

                                                // Validación inmediata de formato
                                                if (! $stateClean || strlen($stateClean) !== 10) {
                                                    Notification::make()
                                                        ->title('Formato inválido')
                                                        ->body('El celular de la referencia 2 debe tener exactamente 10 dígitos numéricos.')
                                                        ->warning()
                                                        ->send();
                                                    $set('Celular_rf2', '');
                                                    return;
                                                }

                                                $norm = fn ($v) => preg_replace('/\D+/', '', (string) $v);

                                                $contactos       = $get('../../clientesContacto') ?? [];

                                                // Teléfonos del cliente (normalizados)
                                                $tel             = $get('../../clientesContacto.tel');
                                                $telAlternativo  = $get('../../clientesContacto.tel_alternativo');
                                                // clienteTrabajo
                                                $trabajos        = $get('../../clienteTrabajo') ?? [];
                                                $telefonoEmpresa = '';
                                                if (is_array($trabajos)) {
                                                    foreach ($trabajos as $row) {
                                                        $telefonoEmpresa = $norm($row['num_empresa'] ?? '');
                                                        break; // solo el primero
                                                    }
                                                }

                                                $telClean             = $tel             ? preg_replace('/\D+/', '', (string) $tel) : null;
                                                $telAlternativoClean  = $telAlternativo  ? preg_replace('/\D+/', '', (string) $telAlternativo) : null;
                                                $telefonoEmpresaClean = $telefonoEmpresa ? preg_replace('/\D+/', '', (string) $telefonoEmpresa) : null;

                                                // Celular de la referencia 1 (varias rutas + buffer global)
                                                $celularRf1 = $get('../../referenciasPersonales1.0.Celular_rf1')
                                                    ?? $get('../../referenciasPersonales1.Celular_rf1')
                                                    ?? $get('../referenciasPersonales1.0.Celular_rf1')
                                                    ?? $get('../referenciasPersonales1.Celular_rf1')
                                                    ?? $get('../../rf1_cel_clean');

                                                $celularRf1Clean = $celularRf1 !== null ? preg_replace('/\D+/', '', (string) $celularRf1) : null;

                                                // Logs de comparación
                                                \Illuminate\Support\Facades\Log::info('Validación Celular_rf2 - Valores obtenidos', [
                                                    'state'                  => $state,
                                                    'stateClean'             => $stateClean,
                                                    'tel'                    => $tel,
                                                    'telClean'               => $telClean,
                                                    'telAlternativo'         => $telAlternativo,
                                                    'telAlternativoClean'    => $telAlternativoClean,
                                                    'telefonoEmpresa'        => $telefonoEmpresa,
                                                    'telefonoEmpresaClean'   => $telefonoEmpresaClean,
                                                    'celularRf1'             => $celularRf1,
                                                    'celularRf1Clean'        => $celularRf1Clean,
                                                    'comparacion_rf1'        => ($stateClean && $celularRf1Clean && $stateClean === $celularRf1Clean) ? 'IGUAL' : 'DIFERENTE',
                                                ]);

                                                // Validar contra teléfonos del cliente
                                                if ($telClean && $stateClean === $telClean) {
                                                    Notification::make()
                                                        ->title('Error de validación')
                                                        ->body('El celular de la referencia 2 no puede ser igual al teléfono principal del cliente.')
                                                        ->danger()
                                                        ->send();
                                                    $set('Celular_rf2', '');
                                                    return;
                                                }

                                                if ($telAlternativoClean && $stateClean === $telAlternativoClean) {
                                                    Notification::make()
                                                        ->title('Error de validación')
                                                        ->body('El celular de la referencia 2 no puede ser igual al teléfono alternativo del cliente.')
                                                        ->danger()
                                                        ->send();
                                                    $set('Celular_rf2', '');
                                                    return;
                                                }

                                                if ($telefonoEmpresaClean && $stateClean === $telefonoEmpresaClean) {
                                                    Notification::make()
                                                        ->title('Error de validación')
                                                        ->body('El celular de la referencia 2 no puede ser igual al teléfono de la empresa del cliente.')
                                                        ->danger()
                                                        ->send();
                                                    $set('Celular_rf2', '');
                                                    return;
                                                }

                                                // Validar contra celular de la referencia 1
                                                if ($celularRf1Clean && $stateClean === $celularRf1Clean) {
                                                    \Illuminate\Support\Facades\Log::info('DUPLICADO DETECTADO: Celular_rf2 igual a Celular_rf1', [
                                                        'celular_rf2'       => $state,
                                                        'celular_rf2_clean' => $stateClean,
                                                        'celular_rf1'       => $celularRf1,
                                                        'celular_rf1_clean' => $celularRf1Clean,
                                                    ]);

                                                    Notification::make()
                                                        ->title('Error de validación')
                                                        ->body('El celular de la referencia 2 no puede ser igual al celular de la referencia 1.')
                                                        ->danger()
                                                        ->send();
                                                    $set('Celular_rf2', '');
                                                    return;
                                                }
                                                //---------- EVALUAR RECOMPRA -----------
                                                $svc = app(RecompraServiceValSimilar::class);
                                                $valor = session('es_recompra');
                                                $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel_alternativo', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);

                                                $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal1', 'Celular_rf1', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal2', 'Celular_rf2', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                //---------------------------------------
                                            }),
                                    ]),
                                //    Grid::make(2)->schema([
                                //        Select::make('Parentesco_rf2')
                                //            ->label('Parentesco')
                                //            ->default(26)
                                //            ->disabled()
                                //            ->dehydrated(),
                                //        Select::make('idtiempo_conocerlonum')
                                //            ->label('Tiempo de Conocerlo')
                                //            ->default(21)
                                //            ->disabled()
                                //            ->dehydrated(),
                                //        Select::make('idtiempoconocerlo')
                                //            ->label('Tiempo de Conocerlo')
                                //            ->default(5)
                                //            ->disabled()
                                //            ->dehydrated(),
                                //    ]),
                                ])
                                ->maxItems(1)
                                ->disableItemCreation()
                                ->disableItemDeletion(),
                        ]),

                    Step::make('Datos del Equipo')
                        ->schema([
                            Section::make('Dispositivos Comprados')
                                ->schema([
                                    Repeater::make('dispositivosComprados')
                                        ->relationship()
                                        ->schema([
                                           // Grid::make(2)->schema([
                                           //     Select::make('id_marca')
                                           //         ->label('Marca del Dispositivo')
                                           //         ->required()
                                           //         ->options(ZMarca::all()->pluck('name_marca', 'idmarca'))
                                           //         ->searchable()
                                           //         ->preload()
                                           //         ->default(15)
                                           //         ->disabled(),
                                           //     Select::make('idmodelo')
                                           //         ->label('Modelo del Dispositivo')
                                           //         ->required()
                                           //         ->options(ZModelo::all()->pluck('name_modelo', 'idmodelo'))
                                           //         ->searchable()
                                           //         ->preload()
                                           //         ->default(85)
                                           //         ->disabled(),
                                           // ]),
                                            Grid::make(2)->schema([
                                                Select::make('idgarantia')
                                                    ->label('Garantía')
                                                    ->required()
                                                    ->options(ZGarantia::all()->pluck('garantia', 'idgarantia'))
                                                    ->searchable()
                                                    ->preload(),
                                                   // ->default(3)
                                                    //->disabled(),
                                                TextInput::make('producto_convenio')
                                                    ->label('Nombre del producto')
                                                    ->required()
                                                    ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                                    //->disabled()
                                                    //->default('N/A')
                                                    //->dehydrated(false),
                                                TextInput::make('imei')
                                                    ->label('IMEI')
                                                    //->required()
                                                    ->maxLength(15)
                                                    ->validationMessages([
                                                        'max_length' => 'Máximo 15 caracteres permitidos.',
                                                    ])
                                                    ->extraAttributes(['class' => 'text-lg font-bold'])
                                                    ->extraInputAttributes([
                                                        'maxlength' => '15',
                                                        'onInput' => 'this.value = this.value.toUpperCase()',
                                                    ])
                                                    ->afterStateUpdated(function (\Filament\Forms\Get $get, ?string $state) {
                                                        $svc = app(RecompraServiceValSimilar::class);
                                                        $resultado = $svc->procesarRecompraPorValorSimilar(2, $state, 'dispositivos_comprados', 'imei', 'El imei '. $state .' del dispositivo ya se encuentra registrado en otro lugar.', 'valor_exacto',['pattern' => 'contains', 'normalize' => true]);
                                                    
                                                    }),
                                            ]),
                                        //    Grid::make(2)->schema([
                                        //        TextInput::make('nombre_producto')
                                        //            ->label('Nombre del producto')
                                        //            ->required()
                                        //            ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        //    ]),
                                        ])
                                          ->deletable(false)   // 👈 oculta el botón de eliminar
                                        ->addable(false)     // 👈 opcional: oculta el botón de agregar
                                        ->reorderable(false) // 👈 opcional: oculta el handle de arrastrar
                                        ->cloneable(false), // 👈 opcional: oculta clonar
                                ]),


                                 Section::make('Pagos de Dispositivos')
                                ->schema([
                                    Repeater::make('dispositivosPago')
                                        ->relationship()
                                        ->schema([
                                            Grid::make(2)->schema([
                                                DatePicker::make('fecha_pc')
                                                    ->label('Fecha Primer Cuota')
                                                    ->required()
                                                    ->displayFormat('d/m/Y')
                                                    ->native(false)
                                                    ->format('Y-m-d')
                                                    ->minDate(now()),
                                                Select::make('idpago')
                                                    ->label('Periodo de Pago')
                                                    ->required()
                                                    ->options(ZPago::all()->pluck('periodo_pago', 'idpago'))
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('num_cuotas')
                                                    ->label('Número de Cuotas')
                                                    ->required()
                                                    ->options(Zcuotas::all()->pluck('num_cuotas', 'idcuotas'))
                                                    ->searchable(),
                                            ]),
                                            Grid::make(2)->schema([
                                                TextInput::make('cuota_inicial')
                                            ->label('Cuota inicial')
                                            ->required()
                                            ->prefix('$') // símbolo de pesos solo visual
                                            // Mostrar con puntos al cargar/editar
                                            ->formatStateUsing(function ($state) {
                                                if ($state === null || $state === '') {
                                                    return $state;
                                                }
                                                $digits = preg_replace('/\D+/', '', (string) $state);
                                                return $digits === '' ? '' : number_format((int) $digits, 0, ',', '.');
                                            })
                                            // Guardar como entero (sin separadores)
                                            ->dehydrateStateUsing(function (string $state): int {
                                                $cleanedState = preg_replace('/[^0-9]/', '', $state);
                                                $intValue = (int) $cleanedState;
                                                Log::info('Dehydrating cuota_inicial', [
                                                    'original_state' => $state,
                                                    'cleaned_state'  => $cleanedState,
                                                    'int_value'      => $intValue,
                                                ]);
                                                return $intValue;
                                            })
                                            // Validación en servidor (Laravel) usando el valor limpio
                                            ->rules([
                                                'required',
                                               
                                            ])
                                            ->validationAttribute('cuota inicial')
                                            // Frontend: permitir solo dígitos y formatear con puntos al escribir
                                            ->extraInputAttributes([
                                                'inputmode' => 'numeric',
                                                'oninput'   => "
                                                    const digits = this.value.replace(/[^0-9]/g,'');
                                                    this.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                                ",
                                            ]),

                                            
                                            //Forms\Components\TextInput::make('num_cuotas')
                                           // ->numeric()
                                            //->extraAttributes(['class' => 'text-lg font-bold']),

                                     
                                          


                                       
                                        TextInput::make('valor_cuotas')
                                            ->label('Valor de cuotas')
                                            ->required()
                                            ->prefix('$') // símbolo de pesos solo visual
                                            // Mostrar con puntos al cargar/editar
                                            ->formatStateUsing(function ($state) {
                                                if ($state === null || $state === '') {
                                                    return $state;
                                                }
                                                $digits = preg_replace('/\D+/', '', (string) $state);
                                                return $digits === '' ? '' : number_format((int) $digits, 0, ',', '.');
                                            })
                                            // Guardar como entero (sin puntos)
                                            ->dehydrateStateUsing(function (string $state): int {
                                                $cleanedState = preg_replace('/[^0-9]/', '', $state);
                                                $intValue = (int) $cleanedState;
                                                Log::info('Dehydrating valor_cuotas', [
                                                    'original_state' => $state,
                                                    'cleaned_state'  => $cleanedState,
                                                    'int_value'      => $intValue,
                                                ]);
                                                return $intValue;
                                            })
                                            // Validación en servidor (Laravel) con el valor limpio
                                            ->rules([
                                                'required',
                                               
                                            ])
                                            ->validationAttribute('valor de cuotas')
                                            // Frontend: permitir solo dígitos y formatear con puntos
                                            ->extraInputAttributes([
                                                'inputmode' => 'numeric',
                                                'oninput'   => "
                                                    const digits = this.value.replace(/[^0-9]/g,'');
                                                    this.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                                ",
                                            ]),
                                            ]),
                                        ])->deletable(false)   // 👈 oculta el botón de eliminar
                                            ->addable(false)     // 👈 opcional: oculta el botón de agregar
                                            ->reorderable(false) // 👈 opcional: oculta el handle de arrastrar
                                            ->cloneable(false), // 👈 opcional: oculta clonar
                                ]),

                                            
                                            

                                
                            Forms\Components\Select::make('idplataforma')
                                ->hidden()
                                ->dehydrated(),
                            Forms\Components\Select::make('idcomision')
                                ->hidden()
                                ->dehydrated(),
                      //   TextInput::make('idsede')
                      //       ->hidden()
                      //       // Valor por defecto al crear:
                      //       ->default(fn () => auth()->user()?->agentSedeId())
                      //       // Si estás editando y viene vacío desde BD, lo setea:
                      //       ->afterStateHydrated(function (TextInput $component, $state) {
                      //           if (blank($state)) {
                      //               $component->state(auth()->user()?->agentSedeId());
                      //           }
                      //       })
                      //       // Guardar como entero (o null):
                      //       ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : null)
                      //       // Solo se envía si tiene valor:
                      //       ->dehydrated(fn ($state) => filled($state)),

                            Hidden::make('id_tipo_credito')
                                ->default(3),
                            Forms\Components\TextInput::make('verificacion_tipo_credito')
                                ->label('Valor 
                                creID_Tipo_credito (Verificación)')
                                ->disabled()
                                ->dehydrated(false)
                                ->hidden(),


                                
                        ]),   
                    Step::make('Detalles Cliente')
    ->schema([
        Repeater::make('detallesCliente')
            ->relationship()
            ->afterStateHydrated(fn ($state) =>
                \Illuminate\Support\Facades\Log::info('Hydrating detallesCliente repeater', ['state' => $state])
            )
            // 🔸 asegura 1 item nuevo cuando estás creando (como deshabilitaste agregar)
            ->defaultItems(1)
            ->schema([
                Grid::make(1)->schema([

                    Select::make('ID_PDV_agente')
                        ->label('Punto de Venta')
                        ->options(fn () => \App\Models\Agentes::query()
                            ->orderBy('nombre_pv')
                            ->pluck('nombre_pv', 'id_tercero')
                            ->toArray()
                        )
                        ->searchable()
                        ->default(fn () => auth()->user()?->agentTerceroId())
                        ->afterStateHydrated(function (\Filament\Forms\Components\Select $component, $state) {
                            if (blank($state)) {
                                $component->state(auth()->user()?->agentTerceroId());
                            }
                        })
                        ->placeholder('Sin agente detectado')
                        ->disabled()               // no editable
                        ->dehydrated(true),        // 👈 forzamos que se envíe igual

                    // ✅ cámbialo a Hidden y fuerza deshidratación
                    \Filament\Forms\Components\Hidden::make('idsede')
                        ->default(fn () => auth()->user()?->agentSedeId())
                        ->afterStateHydrated(function (\Filament\Forms\Components\Hidden $component, $state) {
                            if (blank($state)) {
                                $component->state(auth()->user()?->agentSedeId());
                            }
                        })
                        ->dehydrateStateUsing(fn ($state) => is_null($state) ? null : (int) $state)
                        ->dehydrated(true),        // 👈 siempre
                ]),
            ])

            // 🔸 Cinturón y tirantes: si algo no vino del front, lo completamos aquí
            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                $data['ID_PDV_agente'] = $data['ID_PDV_agente']
                    ?? auth()->user()?->agentTerceroId();
                $data['idsede'] = array_key_exists('idsede', $data)
                    ? (is_null($data['idsede']) ? auth()->user()?->agentSedeId() : (int) $data['idsede'])
                    : auth()->user()?->agentSedeId();

                \Log::info('detallesCliente BEFORE CREATE', ['data' => $data]);
                return $data;
            })
            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                $data['ID_PDV_agente'] = $data['ID_PDV_agente']
                    ?? auth()->user()?->agentTerceroId();
                $data['idsede'] = array_key_exists('idsede', $data)
                    ? (is_null($data['idsede']) ? auth()->user()?->agentSedeId() : (int) $data['idsede'])
                    : auth()->user()?->agentSedeId();

                \Log::info('detallesCliente BEFORE SAVE', ['data' => $data]);
                return $data;
            })

            ->maxItems(1)
            ->disableItemCreation()
            ->disableItemDeletion(),
    ])
    ->columnSpanFull()

                ])
                ->columnSpanFull()
            ]);
    }

    public function mount(): void
    {
        parent::mount();

        $datosApi = session('convenio_buscado_data', null);
        $tipoDocumentoSesion = request()->query('tipoDocumento', null);

        // Log para ver los datos de la API recuperados en mount
        \Illuminate\Support\Facades\Log::info('GestionAgentes mount - datosApi from session', ['datosApi' => $datosApi, 'tipoDocumentoSesion' => $tipoDocumentoSesion]);

        $formData = $this->form->getRawState() ?? [];

        // Eliminar inicialización manual de clientesNombreCompleto y clientesContacto
        // Filament's Section::relationship() handles this automatically
        if (!isset($formData['referenciasPersonales1']) || !is_array($formData['referenciasPersonales1']) || count($formData['referenciasPersonales1']) === 0) {
            $formData['referenciasPersonales1'] = [[
                'Parentesco_rf1' => 26,
                'idtiempo_conocerlonum' => 21,
                'idtiempoconocerlo' => 5,
            ]];
        }
        if (!isset($formData['referenciasPersonales2']) || !is_array($formData['referenciasPersonales2']) || count($formData['referenciasPersonales2']) === 0) {
            $formData['referenciasPersonales2'] = [[
                'Parentesco_rf2' => 26,
                'idtiempo_conocerlonum' => 21,
                'idtiempoconocerlo' => 5,
            ]];
        }
        if (!isset($formData['detallesCliente']) || !is_array($formData['detallesCliente']) || count($formData['detallesCliente']) === 0) {
            $formData['detallesCliente'] = [[
                'idplataforma' => null,
                'idcomision' => null,
                'tiene_recompra' => null,
                'codigo_asesor' => null,
                'ID_PDV_agente' => null,
                'ID_Asesor' => null,
                //'idsede' => null,
            ]];
        }
        if (!isset($formData['dispositivosComprados']) || !is_array($formData['dispositivosComprados']) || count($formData['dispositivosComprados']) === 0) {
            $formData['dispositivosComprados'] = [[
                'id_marca' => 15,
                'idmodelo' => 85,
                'idgarantia' => 3,
                'imei' => '',
                'nombre_producto' => '',
            ]];
        }
        if (isset($formData['id_tipo_credito'])) {
            $formData['verificacion_tipo_credito'] = $formData['id_tipo_credito'];
        }

        // --- SIEMPRE llenar los datos del formulario con los datos de la API si existen ---
        if ($datosApi && is_array($datosApi)) {
            // Mapear y asignar datos de clientesNombreCompleto directamente en formData
            $formData['clientesNombreCompleto'] = [
                'Primer_nombre_cliente' => isset($datosApi['nombre1']) ? (string) $datosApi['nombre1'] : '',
                'Segundo_nombre_cliente' => isset($datosApi['nombre2']) ? (string) $datosApi['nombre2'] : '',
                'Primer_apellido_cliente' => isset($datosApi['apellido1']) ? (string) $datosApi['apellido1'] : '',
                'Segundo_apellido_cliente' => isset($datosApi['apellido2']) ? (string) $datosApi['apellido2'] : '',
            ];
            
            // Mapear y asignar datos de clientesContacto directamente en formData
            $idDepartamentoResidencia = null;
            $nombreDepartamentoApi = $datosApi['descripcionZona'] ?? null;
            if ($nombreDepartamentoApi) {
                $departamento = \App\Models\ZDepartamentos::where('name_departamento', $nombreDepartamentoApi)->first();
                if ($departamento) {
                    $idDepartamentoResidencia = $departamento->id;
                }
            }
            $fechaExpedicionFormatted = null;
            $fechaExpedicionApi = $datosApi['fechaExpedicionCedula'] ?? null;
            if ($fechaExpedicionApi) {
                try {
                    $fecha = new \DateTime($fechaExpedicionApi);
                    $fechaExpedicionFormatted = $fecha->format('Y-m-d');
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error parseando fecha de expedición de API', ['fecha_api' => $fechaExpedicionApi, 'error' => $e->getMessage()]);
                }
            }
            $formData['clientesContacto'] = [
                'fecha_expedicion' => $fechaExpedicionFormatted,
                'correo' => $datosApi['email'] ?? '',
                'tel' => $datosApi['celular'] ?? '',
                'tel_alternativo' => $datosApi['telefono1'] ?? '',
                'residencia_id_departamento' => $idDepartamentoResidencia,
                'residencia_id_municipio' => $datosApi['codigoCiudad'] ?? null,
                'direccion' => $datosApi['direccion'] ?? '',
                'es_independiente' => isset($datosApi['esIndependiente']) ? (bool) $datosApi['esIndependiente'] : false,
                'empresa_labor' => $datosApi['empresaLabora'] ?? '',
                'tel_empresa' => $datosApi['telefonoEmpresa'] ?? '',
                'tiene_recompra' => session('es_recompra') === 1 ? 1 : (session('es_recompra') === 2 ? 2 : null),
            ];

            $formData['cedula'] = $datosApi['nit'] ?? ($datosApi['codigoInterno'] ?? null);
            if ($tipoDocumentoSesion !== null && is_numeric($tipoDocumentoSesion)) {
                $formData['ID_Identificacion_Cliente'] = (int) $tipoDocumentoSesion;
            } else {
                $formData['ID_Identificacion_Cliente'] = (int) \App\Filament\Resources\GestionClienteResource::mapTipoDocumentoToCodigo(
                    \App\Filament\Resources\GestionClienteResource::mapDescripcionToTipoDocumento($datosApi['descTipoIdentificacionTrib'] ?? null)
                ) ?? " " ;
            }
            $formData['fecha_nac'] = $datosApi['fechaNacimiento'] ? \Illuminate\Support\Carbon::parse($datosApi['fechaNacimiento'])->format('Y-m-d') : null;

            // Rellenar id_departamento y id_municipio del formulario principal
            $departamentoPrincipalId = \App\Filament\Resources\GestionClienteResource::getDepartamentoIdPorNombre($datosApi['descripcionZona'] ?? null);
            $municipioPrincipalId = \App\Filament\Resources\GestionClienteResource::getMunicipioIdPorNombre($datosApi['descripcionCiudad'] ?? null);
            $formData['id_departamento'] = $departamentoPrincipalId;
            $formData['id_municipio'] = $municipioPrincipalId;
            \Illuminate\Support\Facades\Log::info('GestionAgentes Mount - id_departamento set to:', ['value' => $formData['id_departamento']]);
            \Illuminate\Support\Facades\Log::info('GestionAgentes Mount - id_municipio set to:', ['value' => $formData['id_municipio']]);

            if (isset($formData['detallesCliente']) && is_array($formData['detallesCliente'])) {
                $detallesClienteKey = array_key_first($formData['detallesCliente']);
                if ($detallesClienteKey !== null) {
                    $nombrePlataformaApi = $datosApi['plataformaApi'] ?? null;
                    $idPlataformaApi = null;
                    if ($nombrePlataformaApi) {
                        $plataforma = \App\Models\ZPlataformaCredito::where('plataforma', $nombrePlataformaApi)->first();
                        if ($plataforma) {
                            $idPlataformaApi = $plataforma->idplataforma;
                        }
                    }
                    $formData['detallesCliente'][$detallesClienteKey]['idplataforma'] = $idPlataformaApi;
                    $formData['detallesCliente'][$detallesClienteKey]['codigo_asesor'] = $datosApi['codigoAsesor'] ?? null;
                    if ($formData['detallesCliente'][$detallesClienteKey]['codigo_asesor'] !== null) {
                        $this->form->getComponent('detallesCliente')
                            ->getContainer()
                            ->getState()[0]
                            ->getComponent('codigo_asesor')
                            ->callAfterStateHydrated();
                    }
                    $formData['detallesCliente'][$detallesClienteKey]['ID_PDV_agente'] = $datosApi['idpdvAgente'] ?? null;
                }
            }
            
            // Log para ver formData justo antes de fill
            \Illuminate\Support\Facades\Log::info('GestionAgentes mount - formData before fill', ['formData' => $formData]);
            $this->form->fill($formData);

            // Eliminar logs de estado de componentes después de fill y rellenado explícito de componentes, ya no son necesarios.
            Notification::make()
                ->title('Cliente encontrado')
                ->success()
                ->send();
        } else {
            // Si NO hay datos de la API, asegurar que los repeaters se inicializan con un solo elemento [0] vacío
            // Para clientesNombreCompleto (HasOne) y clientesContacto (HasOne) ya no se inicializan aquí para precarga.
        }
    }

    private function mapCodigoToTipoDocumento($codigo)
    {
        return match($codigo) {
            '1' => 'CC',
            '12' => 'TI',
            '22' => 'CE',
            '41' => 'PA',
            '11' => 'RC',
            '31' => 'NIT',
            default => ' ',
        };
    }

    public function getTitle(): string
    {
        return 'Crear Gestión de Plataforma';
    }

     protected function getRedirectUrl(): string
{
    // Si por alguna razón aún no hay record, vuelve al index.
    if (! $this->record) {
        return static::getResource()::getUrl('index');
    }

    // Canal seleccionado en el formulario
    $canal = (int) ($this->record->ID_Canal_venta ?? 0);

    // Usa la PK real del modelo (getKey es más robusto que asumir "id")
    $key = $this->record->getKey();

    return match ($canal) {
        1       => static::getResource()::getUrl('firma', ['record' => $key]),
        2       => static::getResource()::getUrl('token', ['record' => $key]),
        default => static::getResource()::getUrl('index'),
    };
}
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Asegurarse de que el ID_tipo_credito sea 3 para agentes
        $data['ID_tipo_credito'] = 3;

        // Asegurar que ID_Identificacion_Cliente se guarde como entero
        if (isset($data['ID_Identificacion_Cliente']) && is_string($data['ID_Identificacion_Cliente'])) {
            $data['ID_Identificacion_Cliente'] = (int) $data['ID_Identificacion_Cliente'];
        }

        if (blank($data['idsede'] ?? null)) {
        $data['idsede'] = auth()->user()?->agentSedeId();
    }

        // Manejar los datos de clientesNombreCompleto y clientesContacto que vienen como HasOne
        // Si los campos relacionados están presentes, Filament los procesará automáticamente
        // si la relación se define con ->relationship() en la Section.
        // No es necesario mutarlos aquí para la creación principal del Cliente.

        // Se asegura que `detallesCliente` tenga el idplataforma de Credito
        if (isset($data['detallesCliente']) && is_array($data['detallesCliente'])) {
            foreach ($data['detallesCliente'] as $key => $detalle) {
                // Asegurarse de que ID_Tipo_credito sea 1 para detallesCliente
                $data['detallesCliente'][$key]['id_tipo_credito'] = 3;

                // Añadir log para verificar idplataforma
                \Illuminate\Support\Facades\Log::info('mutateFormDataBeforeCreate - detallesCliente idplataforma', ['idplataforma' => $detalle['idplataforma'] ?? null]);

                // Manejar otros campos si es necesario
            }
        }

        // Manejar `ID_PDV_agente` dentro de `detallesCliente` si no está configurado
        if (!isset($data['ID_PDV_agente']) && isset($data['detallesCliente'][0]['ID_PDV_agente'])) {
            $data['ID_PDV_agente'] = $data['detallesCliente'][0]['ID_PDV_agente'];
        }

        // Eliminar 'clientesNombreCompleto' y 'clientesContacto' del array principal de datos
        // para que no intenten ser guardados directamente en el modelo Cliente.
        // Estos serán manejados explícitamente en afterCreate().
        unset($data['clientesNombreCompleto']);
        unset($data['clientesContacto']);

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
        {
            if (blank($data['idsede'] ?? null)) {
                $data['idsede'] = auth()->user()?->agentSedeId();
            }
            return $data;
        }

    protected function mutateFormDataBeforeFill(array $data): array
    {
         // Asegurarse de que los datos de detallesCliente incluyan el id_tipo_credito si existen
         if (isset($data['detallesCliente']) && is_array($data['detallesCliente'])) {
             foreach ($data['detallesCliente'] as $key => $detalle) {
                 $data['detallesCliente'][$key]['id_tipo_credito'] = 3;
             }
         }
        return $data;
    }

    protected function afterCreate(): void
    {
        // Crea la fila en gestion si no existe
        $this->record->gestion()->firstOrCreate(
            ['id_cliente' => $this->record->id_cliente], // condiciones
            ['id_cliente' => $this->record->id_cliente], // valores
        );
        // Recuperar los datos del formulario
        $data = $this->form->getState();
        $cliente = $this->getRecord(); // El registro de Cliente recién creado

        // Datos de clientesNombreCompleto
        $nombreCompletoData = $data['clientesNombreCompleto'] ?? [];
        if (!empty($nombreCompletoData)) {
            $clienteNombreCompleto = $cliente->clientesNombreCompleto()->create($nombreCompletoData);
            // Actualizar el cliente principal con el ID_Cliente_Nombre
            $cliente->ID_Cliente_Nombre = $clienteNombreCompleto->ID_Cliente_Nombre;
            \Illuminate\Support\Facades\Log::info('DEBUG: ID_Cliente_Nombre de ClientesNombreCompleto antes de guardar en Cliente', ['ID_Cliente_Nombre' => $clienteNombreCompleto->ID_Cliente_Nombre, 'Cliente ID' => $cliente->id_cliente]);
            $cliente->save();
            \Illuminate\Support\Facades\Log::info('Registro ClientesNombreCompleto creado y Cliente actualizado en afterCreate.', ['data' => $nombreCompletoData, 'ID_Cliente_Nombre_saved' => $cliente->ID_Cliente_Nombre]);
        }

        // Datos de clientesContacto
        $contactoData = $data['clientesContacto'] ?? [];
        if (!empty($contactoData)) {
            $cliente->clientesContacto()->create($contactoData);
            \Illuminate\Support\Facades\Log::info('Registro ClientesContacto creado en afterCreate.', ['data' => $contactoData]);
        }

        \Illuminate\Support\Facades\Log::info('AfterCreate de GestionAgentes ejecutado.');
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        if (!$record) return;

        $contacto = $record->clientesContacto()->first();
        if ($contacto) {
            \Log::info('GestionAgentes afterSave - Datos laborales recogidos', [
                'empresa_labor' => $contacto->empresa_labor,
                'tel_empresa' => $contacto->tel_empresa,
                'es_independiente' => $contacto->es_independiente,
            ]);
            $trabajo = $record->trabajo;
            if ($trabajo) {
                $trabajo->update([
                    'empresa_labor' => $contacto->empresa_labor,
                    'num_empresa' => $contacto->tel_empresa,
                    'es_independiente' => $contacto->es_independiente,
                ]);
            } else {
                \App\Models\ClienteTrabajo::create([
                    'id_cliente' => $record->id_cliente,
                    'empresa_labor' => $contacto->empresa_labor,
                    'num_empresa' => $contacto->tel_empresa,
                    'es_independiente' => $contacto->es_independiente,
                ]);
            }
        }
    }


    protected function getFormActions(): array
    {
        return [
            $this->getCancelFormAction(),
            $this->getCreateFormAction()->label('Crear'),
        ];
    }
} 