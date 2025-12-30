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
use Filament\Forms\Components\HasOne;
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
use App\Models\CanalVentafd;
use Filament\Notifications\Notification;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\TipoDocumento;
use App\Models\TipoCredito;
use Illuminate\Support\Carbon;

use App\Models\Asesor;
use App\Models\AaPrin;
use App\Models\SocioDistritec;
use App\Models\Sede;

Use App\Models\Zcuotas;
use App\Services\RecompraServiceValSimilar;

use Illuminate\Support\Facades\Auth;
use App\Events\ClienteCreatedLight;


class GestionConvenios extends CreateRecord
{
    protected static string $resource = GestionClienteResource::class;

    // Anular el método authorizeAccess para permitir el acceso directo a esta página
    protected function authorizeAccess(): void
    {
        // Aquí puedes poner tu lógica de permisos específica para esta página si la necesitas.
        // Por ahora, simplemente no lanzamos una excepción para permitir el acceso.
        // Si usas Spatie, podrías comprobar un permiso específico aquí:
        // if (! auth()->user()->can('acceder gestion convenios')) {
        //     abort(403);
        // }
    }





    public function form(Form $form): Form
    {
        return $form
            ->schema([
                 // Campo oculto: por si acaso (pero lo reforzamos en mutate), capturar el user_id del usuario que crea el cliente
            Hidden::make('user_id')
                ->dehydrated(true)
                ->default(fn () => auth()->id()),
                Wizard::make([
                    Step::make('Datos del Cliente')
                        ->schema([
                            Grid::make(2)->schema([
                              // Select::make('ID_Canal_venta')
                              //     ->label('Canal de Venta')
                              //     ->required()
                              //     ->options(\App\Models\CanalVentafd::pluck('canal', 'ID_Canal_venta'))
                              //     ->searchable()
                              //     ->preload(),
                                 Hidden::make('ID_Canal_venta')->default(2),
                                 Select::make('ID_Identificacion_Cliente')
                                ->label('Tipo de Documento')
                                ->required()
                                ->options(\App\Models\TipoDocumento::pluck('desc_identificacion', 'ID_Identificacion_Tributaria'))
                                ->searchable()
                               // ->disabled()    
                                ->dehydrated() 
                                ->preload(),
                                TextInput::make('cedula')
                                    ->extraAttributes([
                                        'class' => 'text-lg font-bold',
                                        'onInput' => 'this.value = this.value.toUpperCase()'
                                    ])
                                    ->label('Cédula')
                                    ->required()
                                    ->numeric()
                                    ->minLength(5)
                                    ->maxLength(15)
                                    ->readOnly()
                                    ->validationMessages([
                                        'max_length' => 'Máximo 15 dígitos permitidos.',
                                        'min_length' => 'Mínimo 5 dígitos permitidos.',
                                    ]),
                            ]),
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
                            DatePicker::make('fecha_nac')
                                    ->label('Fecha de Nacimiento')
                                    ->format('Y-m-d')
                                    ->displayFormat('d/m/Y')
                                    ->required()
                                    ->maxDate(Carbon::yesterday())   // bloquea hoy y futuras en el picker
                                    ->rule('before:today'),         // valida en servidor que sea < hoy
                            Section::make('Nombre Completo')
                                ->schema([
                                    Repeater::make('clientesNombreCompleto')
                                        ->relationship()
                                        ->schema([
                                            Grid::make(2)->schema([
                                                TextInput::make('Primer_nombre_cliente')->readOnly()->label('Primer Nombre')->required()->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                                TextInput::make('Segundo_nombre_cliente')->readOnly()->label('Segundo Nombre')->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                            ]),
                                            Grid::make(2)->schema([
                                                TextInput::make('Primer_apellido_cliente')->readOnly()->label('Primer Apellido')->required()->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                                TextInput::make('Segundo_apellido_cliente')->readOnly()->label('Segundo Apellido')->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                            ]),
                                        ])
                                        ->maxItems(1)
                                        ->disableItemCreation()
                                        ->disableItemDeletion(),
                                ]),
                            Hidden::make('fecha_registro')
                                ->default(now()->toDateString()),
                            Hidden::make('hora')
                                ->default(now()->format('H:i:s')),
                        ]),
                   

                    Step::make('Datos Personales')
                        ->schema([
                            Forms\Components\Repeater::make('clientesContacto')
                                ->relationship('clientesContacto')
                                ->label('Datos de Contacto y Laborales')
                                ->minItems(1)
                                ->maxItems(1)
                                ->schema([
                                    Grid::make(2)->schema([
                                        DatePicker::make('fecha_expedicion')
                                       //  ->label('Fecha de expedición')
                                       //  ->hint('En que fecha sacó la cedula?')
                                       //  ->hintColor('info')
                                       //  ->required()
                                       //  ->hintIcon('phosphor-push-pin-bold')
                                       //  ->nullable()
                                       //  ->maxDate(Carbon::yesterday())   // bloquea hoy y futuras en el picker
                                       //  ->rule('before:today'),         // valida en servidor que sea < hoy
                                           ->label('Fecha deexpedición del documento')
                                    ->format('Y-m-d')
                                    ->displayFormat('d/m/Y')
                                    ->required()
                                    ->maxDate(Carbon::yesterday())   // bloquea hoy y futuras en el picker
                                    ->rule('before:today'),         // valida en servidor que sea < hoy
                                        TextInput::make('correo')
                                            ->label('Correo')
                                            ->required()
                                            ->email()
                                            //->readOnly()
                                            ->maxLength(100)
                                            ->hint('Utilice un correo verdadero')
                                            ->regex('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/')
                                            ->validationMessages([
                                                'regex' => 'El formato del correo electrónico es inválido.',
                                            ]),
                                        TextInput::make('tel')
                                            ->label('Número de Celular')
                                            ->tel()
                                            ->required()
                                            //->readOnly()
                                            ->hint('Digite solo números')
                                            ->maxLength(10)
                                            ->numeric()
                                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                                //---------- EVALUAR RECOMPRA -----------
                                                $svc = app(RecompraServiceValSimilar::class);
                                                $valor = session('es_recompra');
                                                $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel_alternativo', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);

                                                $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal1', 'Celular_rf1', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal2', 'Celular_rf2', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                //---------------------------------------
                                            }),
                                        TextInput::make('tel_alternativo')
                                            ->label('Teléfono Alternativo')
                                            ->tel()
                                            ->required()
                                            ->minLength(10)
                                            ->maxLength(10)
                                            ->validationAttribute('teléfono alternativo')
                                            ->helperText('Debe tener exactamente 10 dígitos.')
                                            ->extraInputAttributes([
                                                'inputmode' => 'numeric',
                                                'pattern'   => '\d{10}',
                                                'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                            ])
                                            ->live(onBlur: true)

                                            // ✅ Validación servidor (incluye cruce con clienteTrabajo.0.num_empresa)
                                            ->rule(fn (\Filament\Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                                $value = (string) $value;
                                                if ($value !== '' && ! preg_match('/^\d{10}$/', $value)) {
                                                    $fail('El teléfono alternativo debe tener exactamente 10 dígitos numéricos.');
                                                    return;
                                                }

                                                $telPrincipal   = (string) ($get('clientesContacto.tel') ?? '');
                                                $telEmpresa     = (string) ($get('clienteTrabajo.num_empresa') ?? '');

                                                if ($value !== '' && $telPrincipal !== '' && $value === $telPrincipal) {
                                                    $fail('El teléfono alternativo no puede ser igual al teléfono principal.');
                                                    return;
                                                }

                                                if ($value !== '' && $telEmpresa !== '' && $value === $telEmpresa) {
                                                    $fail('El teléfono alternativo no puede ser igual al teléfono de la empresa.');
                                                    return;
                                                }
                                            })

                                            // ✅ Validación en vivo
                                           ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                            // Telef. principal en el mismo repeater (si Filament lo resuelve local)
                                            $telLocal = $get('tel');

                                            // Telef. principal desde la ruta absoluta por si el local no aplica
                                            $telAbsoluto = $get('clientesContacto.0.tel');

                                            // Empresa (ESTÁ en otro repeater): leer por ruta absoluta
                                            $telefonoEmpresa = $get('clienteTrabajo.0.num_empresa');

                                            // Elegir el valor disponible del principal
                                            $tel = $telLocal ?: $telAbsoluto;

                                            // Validación de formato exacto 10 dígitos
                                            if (! preg_match('/^\d{10}$/', (string) $state)) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Formato inválido')
                                                    ->body('El teléfono alternativo debe tener exactamente 10 dígitos numéricos.')
                                                    ->warning()
                                                    ->send();
                                                $set('tel_alternativo', '');
                                                return;
                                            }

                                            // Duplicado con principal
                                            if ($state && $tel && $state === $tel) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Error de validación')
                                                    ->body('El teléfono alternativo no puede ser igual al teléfono principal.')
                                                    ->danger()
                                                    ->send();
                                                $set('tel_alternativo', '');
                                                return;
                                            }

                                            // Duplicado con teléfono empresa (del otro repeater)
                                            if ($state && $telefonoEmpresa && $state === $telefonoEmpresa) {
                                                \Filament\Notifications\Notification::make()
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
                                            $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                            $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel_alternativo', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);

                                            $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal1', 'Celular_rf1', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                            $resultado = $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal2', 'Celular_rf2', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                            //---------------------------------------
                                        }),


                                         Select::make('residencia_id_departamento')
                                            ->label('Departamento de Residencia')
                                            ->required()
                                            ->options(ZDepartamentos::whereNotNull('name_departamento')->pluck('name_departamento', 'id'))
                                            ->searchable()
                                            ->reactive()
                                            ->afterStateUpdated(fn (callable $set) => $set('residencia_id_municipio', null)),
                                        Select::make('residencia_id_municipio')
                                            ->label('Municipio de Residencia')
                                            ->required()
                                            ->options(function (callable $get) {
                                                $departamentoId = $get('residencia_id_departamento');
                                                if ($departamentoId) {
                                                    return ZMunicipios::where('departamento_id', $departamentoId)
                                                        ->whereNotNull('name_municipio')
                                                        ->pluck('name_municipio', 'id');
                                                }
                                                return [];
                                            })
                                            ->searchable(),
                                        TextInput::make('direccion')
                                            ->label('Dirección')
                                            ->maxLength(255)
                                            ->required()
                                            ->readOnly(),
                                    ]),
                                   
                                ])
                                
                                ->columns(2)
                                ->deletable(false)   // 👈 oculta el botón de eliminar
                                ->addable(false)     // 👈 opcional: oculta el botón de agregar
                                ->reorderable(false) // 👈 opcional: oculta el handle de arrastrar
                                ->cloneable(false), // 👈 opcional: oculta clonar
                                ]),

                         
                    Step::make('Datos trabajo')
                        ->schema([
                                // Campo editable: empresa
                                Forms\Components\Repeater::make('clienteTrabajo')
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
                                        ->unique('clientes_trabajo', 'num_empresa', ignoreRecord: true)  
                                        ->extraInputAttributes([
                                            'inputmode' => 'numeric',
                                            'pattern'   => '\d{10}',
                                            'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                            'unique' => 'Este teléfono de la empresa ya se encuentra registrado.',
                                        ])
                                        // ✅ Validar solo al perder foco
                                        ->live(onBlur: true)

                                        ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                            // No validar si es independiente
                                            if ($get('es_independiente')) {
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
                                        })

                                        // ✅ Respaldo en servidor (submit)
                                        ->rule(function (Get $get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                if ($get('es_independiente')) return;

                                                $norm = fn ($v) => preg_replace('/\D+/', '', (string) $v);
                                                $empresa = $norm($value);

                                                // Solo si hay 10 dígitos
                                                if (strlen($empresa) !== 10) return;

                                                $telPrincipal   = $norm($get('../../clientesContacto.0.tel'));
                                                $telAlternativo = $norm($get('../../clientesContacto.0.tel_alternativo'));

                                                if ($telPrincipal !== '' && $empresa === $telPrincipal) {
                                                    $fail('El teléfono de la empresa no puede ser igual al teléfono principal.');
                                                }
                                                if ($telAlternativo !== '' && $empresa === $telAlternativo) {
                                                    $fail('El teléfono de la empresa no puede ser igual al teléfono alternativo.');
                                                }
                                            };
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
                    Step::make('Datos del Equipo')
                        ->schema([
                            Section::make('Dispositivos Comprados')
                                ->schema([
                                    Repeater::make('dispositivosComprados')
                                        ->relationship()
                                        ->schema([
                                            //Grid::make(2)->schema([
                                                //Select::make('id_marca')
                                                    //->label('Marca del Dispositivo')
                                                    //->required()
                                                    //->options(ZMarca::all()->pluck('name_marca', 'idmarca'))
                                                    //->searchable()
                                                    //->preload()
                                                    //->reactive()
                                                    //->afterStateUpdated(function (Set $set) {
                                                       // $set('idmodelo', null);
                                                   // })
                                                    //->extraAttributes(['class' => 'text-lg font-bold']),
                                               // Select::make('idmodelo')
                                                    //->label('Modelo del Dispositivo')
                                                   // ->required()
                                                    //->options(function (Get $get) {
                                                        //$marcaId = $get('id_marca');
                                                        //if ($marcaId) {
                                                            //return ZModelo::where('idmarca', $marcaId)
                                                               // ->whereNotNull('name_modelo')
                                                                //->pluck('name_modelo', 'idmodelo');
                                                       // }
                                                        //return [];
                                                  //  })
                                                   // ->searchable()
                                                   // ->preload()
                                                    //->extraAttributes(['class' => 'text-lg font-bold']),
                                           // ]),
                                            Grid::make(2)->schema([
                                                Select::make('idgarantia')
                                                    ->label('Garantía')
                                                    ->required()
                                                    ->options(ZGarantia::all()->pluck('garantia', 'idgarantia'))
                                                    ->searchable()
                                                    ->preload(),
                                                TextInput::make('imei')
                                                    ->label('IMEI')
                                                    ->required()
                                                    //->numeric()
                                                    ->minLength(5)
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



                                                TextInput::make('producto_convenio')
                                                    ->label('Nombre del producto')
                                                    ->required()
                                                    ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                                    //->disabled()
                                                    //->default('N/A')
                                                    //->dehydrated(false),
                                            ]),
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
                        ]),


                    Step::make('Detalles Cliente')
                    ->schema([
                        Section::make('Detalles')
                            ->schema([
                                Repeater::make('detallesCliente')
                                    ->relationship()
                                    ->minItems(1)
                                    ->maxItems(1)
                                    ->defaultItems(1)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->cloneable(false)

                                     // Normaliza y resuelve idsede ANTES de crear
                                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                        // Helper local para normalizar a entero (1/0/2/3 o null)
                                        $toInt = static function ($v): ?int {
                                            if ($v === null) return null;
                                            if (is_bool($v)) return $v ? 1 : 0;
                                            if (is_numeric($v)) return (int) $v;
                                            if (is_string($v)) {
                                                $s = strtoupper(trim($v));
                                                if (in_array($s, ['SI','SÍ','YES','Y'], true)) return 1;
                                                if (in_array($s, ['NO','N'], true)) return 0;
                                                if (is_numeric($s)) return (int) $s;
                                            }
                                            return null;
                                        };

                                        // 1) Leer valores desde sesión y/o payload
                                        $tieneRecompraRaw   = session('es_recompra')        ?? ($data['tiene_recompra']   ?? ($data['es_recompra'] ?? null));
                                        $estaProductoRaw    = session('esta_el_producto')   ?? ($data['esta_el_producto'] ?? null);

                                        // 2) Normalizar
                                        $tieneRecompra      = $toInt($tieneRecompraRaw);
                                        $estaProducto       = $toInt($estaProductoRaw);

                                        // Si usas 3 = "Indiferente" como placeholder, NO permitir guardado
                                        if ($estaProducto === 3) $estaProducto = null;

                                        $data['tiene_recompra']   = $tieneRecompra;
                                        $data['esta_el_producto'] = $estaProducto;

                                        // 3) Dependencia (sesión > payload)
                                        $idDep = session('Id_Dependencia')
                                            ?? session('tipo_dependencia')
                                            ?? ($data['Id_Dependencia'] ?? null);
                                        $data['Id_Dependencia'] = filled($idDep) ? (int) $idDep : null;

                                        // 4) idsede según rol
                                        $isAgent = \Illuminate\Support\Facades\Auth::user()?->hasAnyRole(['agente_admin','asesor_agente']);
                                        if ($isAgent) {
                                            $idsedeAgent    = $data['idsede_agent'] ?? \Illuminate\Support\Facades\Auth::user()?->agentSedeId();
                                            $data['idsede'] = filled($idsedeAgent) ? (int) $idsedeAgent : null;
                                        } else {
                                            $data['idsede'] = isset($data['idsede']) && filled($data['idsede']) ? (int) $data['idsede'] : null;
                                        }

                                        unset($data['idsede_agent']);

                                        \Log::debug('detallesCliente BEFORE CREATE', [
                                            'tiene_recompra'   => $data['tiene_recompra'],
                                            'esta_el_producto' => $data['esta_el_producto'],
                                            'Id_Dependencia'   => $data['Id_Dependencia'],
                                            'idsede'           => $data['idsede'],
                                            'session'          => [
                                                'es_recompra'      => session('es_recompra'),
                                                'esta_el_producto' => session('esta_el_producto'),
                                            ],
                                        ]);

                                        return $data;
                                    })

                                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                        // Helper local
                                        $toInt = static function ($v): ?int {
                                            if ($v === null) return null;
                                            if (is_bool($v)) return $v ? 1 : 0;
                                            if (is_numeric($v)) return (int) $v;
                                            if (is_string($v)) {
                                                $s = strtoupper(trim($v));
                                                if (in_array($s, ['SI','SÍ','YES','Y'], true)) return 1;
                                                if (in_array($s, ['NO','N'], true)) return 0;
                                                if (is_numeric($s)) return (int) $s;
                                            }
                                            return null;
                                        };

                                        // 1) Sesión / payload
                                        $tieneRecompraRaw   = session('es_recompra')        ?? ($data['tiene_recompra']   ?? ($data['es_recompra'] ?? null));
                                        $estaProductoRaw    = session('esta_el_producto')   ?? ($data['esta_el_producto'] ?? null);

                                        // 2) Normalizar
                                        $tieneRecompra      = $toInt($tieneRecompraRaw);
                                        $estaProducto       = $toInt($estaProductoRaw);
                                        if ($estaProducto === 3) $estaProducto = null;

                                        $data['tiene_recompra']   = $tieneRecompra;
                                        $data['esta_el_producto'] = $estaProducto;

                                        // 3) Dependencia
                                        $idDep = session('Id_Dependencia')
                                            ?? session('tipo_dependencia')
                                            ?? ($data['Id_Dependencia'] ?? null);
                                        $data['Id_Dependencia'] = filled($idDep) ? (int) $idDep : null;

                                        // 4) idsede según rol
                                        $isAgent = \Illuminate\Support\Facades\Auth::user()?->hasAnyRole(['agente_admin','asesor_agente']);
                                        if ($isAgent) {
                                            $idsedeAgent    = $data['idsede_agent'] ?? \Illuminate\Support\Facades\Auth::user()?->agentSedeId();
                                            $data['idsede'] = filled($idsedeAgent) ? (int) $idsedeAgent : null;
                                        } else {
                                            $data['idsede'] = isset($data['idsede']) && filled($data['idsede']) ? (int) $data['idsede'] : null;
                                        }

                                        unset($data['idsede_agent']);

                                        \Log::debug('detallesCliente BEFORE SAVE', [
                                            'tiene_recompra'   => $data['tiene_recompra'],
                                            'esta_el_producto' => $data['esta_el_producto'],
                                            'Id_Dependencia'   => $data['Id_Dependencia'],
                                            'idsede'           => $data['idsede'],
                                            'session'          => [
                                                'es_recompra'      => session('es_recompra'),
                                                'esta_el_producto' => session('esta_el_producto'),
                                            ],
                                        ]);

                                        return $data;
                                    })


                                    ->schema([
                                        Grid::make(2)->schema([
                                            // === Oculto: SIEMPRE guarda 1 ===
                                       //  Hidden::make('es_recompra')
                                       //      ->default(1)
                                       //      ->dehydrated(true)
                                       //      ->dehydrateStateUsing(fn () => 1),

                                       //  // === Oculto: tiene_recompra desde la sesión (no visible, no “No” por defecto) ===
                                       //  Hidden::make('tiene_recompra')
                                       //      ->dehydrated(true)
                                       //      ->default(function () {
                                       //          $raw = session('es_recompra'); 
                                       //          if (is_string($raw)) return trim(strtolower($raw)) === 'Si' ? 1 : 2;
                                       //          if (is_bool($raw))   return $raw ? 1 : 2;
                                       //          return 2; // si no hay sesión, queda 2, pero los mutate() arriba lo re-forzarán también
                                       //      })
                                       //      ->dehydrateStateUsing(fn ($state) => (int) $state),

                                                

                                            // ======= TUS CAMPOS EXISTENTES =======
                                            Select::make('idplataforma')
                                                ->label('Convenio')
                                                ->required()
                                                ->options(
                                                    \App\Models\ZPlataformaCredito::whereIn('plataforma', [
                                                        'CELYA','BANCO DE BOGOTA','TEAVALO','SISTECREDITO','ADDI',
                                                        'SUFI','BRILLA','ALCANOS','METROGAS','GASES DEL ORIENTE','VANTI','CUSIAGANAS',
                                                    ])->pluck('plataforma', 'idplataforma')
                                                )
                                                ->searchable()
                                                ->preload(),

                                            Select::make('idcomision')
                                                ->label('Comisión')
                                                ->required()
                                                ->options(\App\Models\ZComision::all()->pluck('comision', 'id'))
                                                ->searchable()
                                                ->preload(),

                                            // ====== CÓDIGO ASESOR (AUTO) ======
                                                Select::make('codigo_asesor')
                                                    ->label('Código Asesor')
                                                    ->options(\App\Models\InfTrab::pluck('Codigo_vendedor', 'Codigo_vendedor'))
                                                    ->searchable()
                                                    ->reactive()
                                                    ->required()
                                                    ->disabled()
                                                    ->required(fn () => ! Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->hidden(fn ()   =>   Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    // Si es agente, NO enviar este campo al guardar (no “se tiene en cuenta”)
                                                    ->dehydrated(fn () => ! Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->default(function ($record) {
                                                        if ($record?->codigo_asesor) return $record->codigo_asesor;

                                                        $cedula = auth()->user()?->cedula;
                                                        if (! $cedula) return null;

                                                        if (auth()->user()?->hasRole('socio')) {
                                                            $socio = \App\Models\SocioDistritec::where('Cedula', $cedula)->first();
                                                            return $socio?->soc_cod_vendedor;
                                                        }

                                                        $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                                        if (! $asesor) return null;

                                                        $aaPrin = \App\Models\AaPrin::where('ID_Asesor', $asesor->ID_Asesor)
                                                            ->where('ID_Estado', '!=', 3)
                                                            ->orderByDesc('ID_Inf_trab')
                                                            ->first();

                                                        if (! $aaPrin) return null;

                                                        $infTrab = \App\Models\InfTrab::find($aaPrin->ID_Inf_trab);
                                                        return $infTrab?->Codigo_vendedor;
                                                    })
                                                    ->afterStateHydrated(function ($state, callable $set) {
                                                        $cedula = auth()->user()?->cedula;

                                                        if (auth()->user()?->hasRole('socio')) {
                                                            $socio = $cedula ? \App\Models\SocioDistritec::where('Cedula', $cedula)->first() : null;
                                                            if ($socio) {
                                                                if (blank($state)) {
                                                                    $set('codigo_asesor', $socio->soc_cod_vendedor);
                                                                }
                                                                $set('nombre_asesor', $socio->Socio ?? '');
                                                                $set('ID_Socio', $socio->ID_Socio);
                                                            }
                                                            return;
                                                        }

                                                        if ($state === null || $state === '') {
                                                            if ($cedula) {
                                                                $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                                                if ($asesor) {
                                                                    $aaPrin = \App\Models\AaPrin::where('ID_Asesor', $asesor->ID_Asesor)
                                                                        ->where('ID_Estado', '!=', 3)
                                                                        ->orderByDesc('ID_Inf_trab')
                                                                        ->with(['sede'])
                                                                        ->first();

                                                                    if ($aaPrin) {
                                                                        $infTrab = \App\Models\InfTrab::find($aaPrin->ID_Inf_trab);
                                                                        if ($infTrab) {
                                                                            $set('codigo_asesor', $infTrab->Codigo_vendedor);
                                                                            $set('ID_Asesor', $aaPrin->ID_Asesor);
                                                                            $set('idsede',   $aaPrin->ID_Sede); // para NO agentes
                                                                            $set('nombre_asesor', $asesor->Nombre ?? '');
                                                                            $set('nombre_sede',  $aaPrin->sede->Name_Sede ?? '');
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }

                                                        if ($state !== null && $state !== '') {
                                                            $infTrab = \App\Models\InfTrab::where('Codigo_vendedor', $state)
                                                                ->with('aaPrin.asesor', 'aaPrin.sede')
                                                                ->first();

                                                            if ($infTrab && $infTrab->aaPrin) {
                                                                $set('ID_Asesor', $infTrab->aaPrin->ID_Asesor);
                                                                $set('idsede',   $infTrab->aaPrin->ID_Sede); // para NO agentes
                                                                $set('nombre_asesor', $infTrab->aaPrin->asesor->Nombre ?? '');
                                                                $set('nombre_sede',  $infTrab->aaPrin->sede->Name_Sede ?? '');
                                                            } else {
                                                                $set('ID_Asesor', null);
                                                                $set('idsede',   null);
                                                                $set('nombre_asesor', '');
                                                                $set('nombre_sede',  '');
                                                            }
                                                        }
                                                    })
                                                    ->afterStateUpdated(function ($state, callable $set) {
                                                        $infTrab = \App\Models\InfTrab::where('Codigo_vendedor', $state)
                                                            ->with('aaPrin.asesor', 'aaPrin.sede')
                                                            ->first();

                                                        if ($infTrab && $infTrab->aaPrin) {
                                                            $set('ID_Asesor', $infTrab->aaPrin->ID_Asesor);
                                                            $set('idsede',    $infTrab->aaPrin->ID_Sede); // para NO agentes
                                                            $set('nombre_asesor', $infTrab->aaPrin->asesor->Nombre ?? 'N/A');
                                                            $set('nombre_sede',  $infTrab->aaPrin->sede->Name_Sede ?? 'N/A');
                                                        } else {
                                                            $set('ID_Asesor', null);
                                                            $set('idsede',    null);
                                                            $set('nombre_asesor', '');
                                                            $set('nombre_sede',  '');
                                                        }
                                                    })
                                                    ->extraAttributes(['class' => 'text-lg font-bold']),
                                                // ====== FIN CÓDIGO ASESOR ======

                                                // ====== SELECT DE SEDES (solo SOCIO) ======
                                                Select::make('idsede_select')
                                                    ->label('Sede')
                                                    ->options(function (Forms\Get $get) {
                                                        $idSocio = $get('ID_Socio');
                                                        if (! $idSocio) return [];
                                                        return \App\Models\Sede::where('ID_Socio', $idSocio)
                                                            ->orderBy('Name_Sede')
                                                            ->pluck('Name_Sede', 'ID_Sede')
                                                            ->toArray();
                                                    })
                                                    ->visible(fn () => auth()->user()?->hasRole('socio'))
                                                    ->required(fn () => auth()->user()?->hasRole('socio'))
                                                    ->reactive()
                                                    ->afterStateUpdated(function ($state, callable $set) {
                                                        if ($state) {
                                                            $sede = \App\Models\Sede::find($state);
                                                            $set('idsede', $state); // para NO agentes
                                                            $set('nombre_sede', $sede?->Name_Sede ?? '');
                                                        } else {
                                                            $set('idsede', null);
                                                            $set('nombre_sede', '');
                                                        }
                                                    })
                                                    ->extraAttributes(['class' => 'text-lg font-bold']),
                                                // ====== FIN SELECT DE SEDES ======

                                                Forms\Components\TextInput::make('nombre_asesor')
                                                    ->label('Nombre Asesor')
                                                    ->disabled()
                                                    ->required(fn () => ! Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->hidden(fn ()   =>   Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    // Si es agente, NO enviar este campo al guardar (no “se tiene en cuenta”)
                                                    ->dehydrated(fn () => ! Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->extraAttributes(['class' => 'text-lg font-bold']),

                                                Forms\Components\TextInput::make('nombre_sede')
                                                    ->label('Sede')
                                                    ->disabled()
                                                    ->required(fn () => ! Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->hidden(fn ()   =>   Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    // Si es agente, NO enviar este campo al guardar (no “se tiene en cuenta”)
                                                    ->dehydrated(fn () => ! Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                                ->extraAttributes(['class' => 'text-lg font-bold']),

                                                // Ocultos que sí existen en DB
                                                Hidden::make('ID_Asesor')->dehydrated(true),
                                                Hidden::make('ID_Socio')->dehydrated(false),
                                                Hidden::make('id_tipo_credito')->default(2),

                                                // --- idsede REAL para usuarios normales (NO agentes) ---
                                                Hidden::make('idsede')
                                                    ->hidden(fn () => \Illuminate\Support\Facades\Auth::user()?->hasAnyRole(['agente_admin','asesor_agente']))
                                                    ->dehydrated(fn () => ! \Illuminate\Support\Facades\Auth::user()?->hasAnyRole(['agente_admin','asesor_agente']))
                                                    ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : null),


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
                                                            ->disabled()   
                                                            ->hidden(fn ()     => ! Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                            //->required(fn ()   =>   Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                            ->dehydrated(fn () =>   Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente'])),            // no editable
                                                                // 👈 forzamos que se envíe igual

                                                // --- idsede auxiliar para AGENTES (se mapea a idsede en mutate* ) ---
                                                        Hidden::make('idsede_agent')
                                                            ->default(fn () => \Illuminate\Support\Facades\Auth::user()?->agentSedeId())
                                                            ->afterStateHydrated(function (\Filament\Forms\Components\Hidden $component, $state) {
                                                                if (blank($state)) {
                                                                    $component->state(\Illuminate\Support\Facades\Auth::user()?->agentSedeId());
                                                                }
                                                            })
                                                            ->hidden(fn () => ! \Illuminate\Support\Facades\Auth::user()?->hasAnyRole(['agente_admin','asesor_agente']))
                                                            ->dehydrated(fn () => \Illuminate\Support\Facades\Auth::user()?->hasAnyRole(['agente_admin','asesor_agente']))
                                                            ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : null),
                                        ]),
                                    ]),
                            ]),
                        ]),


                ])->columnSpanFull()
            ]);
    }

    public function mount(): void
    {
        parent::mount();

        $datosApi = session('convenio_buscado_data', null);
        $tipoDocumentoSesion = request()->query('tipoDocumento', null);

        // Limpiar los datos de la sesión después de recuperarlos
        session()->forget('convenio_buscado_data');
        session()->forget('busqueda_tipo_documento');

        // Log detallado de los datos recuperados de la sesión
        Log::info('Datos de API recuperados en GestionConvenios mount:', ['datos' => $datosApi, 'tipo_documento_sesion' => $tipoDocumentoSesion]);

        // Obtener el estado inicial del formulario
        $formData = $this->form->getRawState() ?? [];

        // Inicializar clientesContacto como un array vacío si no existe o no es un array
        // Esto es crucial para las relaciones HasOne al crear un nuevo registro
        if (!isset($formData['clientesContacto']) || !is_array($formData['clientesContacto'])) {
            $formData['clientesContacto'] = [];
        }

        // ** NUEVO: Inicializar las estructuras de los Repeaters con un elemento vacío si no existen **
        if (!isset($formData['clientesNombreCompleto']) || !is_array($formData['clientesNombreCompleto']) || count($formData['clientesNombreCompleto']) === 0) {
            $formData['clientesNombreCompleto'] = [[
                'Primer_nombre_cliente' => '',
                'Segundo_nombre_cliente' => '',
                'Primer_apellido_cliente' => '',
                'Segundo_apellido_cliente' => '',
            ]];
        }
        if (!isset($formData['dispositivosComprados']) || !is_array($formData['dispositivosComprados']) || count($formData['dispositivosComprados']) === 0) {
            $formData['dispositivosComprados'] = [[
                'id_marca' => null,
                'idmodelo' => null,
                'idgarantia' => null,
                'imei' => null,
                'nombre_producto' => 'N/A',
            ]];
        }
         if (!isset($formData['detallesCliente']) || !is_array($formData['detallesCliente']) || count($formData['detallesCliente']) === 0) {
            $formData['detallesCliente'] = [[
                'idplataforma' => null,
                'idcomision' => null,
                'tiene_recompra' => null,
                'Id_Dependencia' => null,
                'codigo_asesor' => null,
                'ID_Asesor' => null, // Asegurarse de incluir todos los campos del repeater
                'idsede' => null, // Asegurarse de incluir todos los campos del repeater
                'id_tipo_credito' => 2, // Asegurarse de incluir todos los campos del repeater
            ]];
        }


        if ($datosApi && is_array($datosApi)) {
            // Asignar datos del cliente principal a los campos directos
            $formData['cedula'] = $datosApi['nit'] ?? ($datosApi['codigoInterno'] ?? null);
            
            $descTipoIdentificacion = $datosApi['descTipoIdentificacionTrib'] ?? null;
            Log::info('GestionConvenios Mount - descTipoIdentificacion from API:', ['value' => $descTipoIdentificacion]);

            // Simplificar la obtención de ID_Identificacion_Cliente
            $tipoIdentificacionId = \App\Models\TipoDocumento::where('desc_identificacion', $descTipoIdentificacion)->value('ID_Identificacion_Tributaria');
            $formData['ID_Identificacion_Cliente'] = $tipoIdentificacionId ?? '';
            Log::info('GestionConvenios Mount - ID_Identificacion_Cliente set to:', ['value' => $formData['ID_Identificacion_Cliente']]);

            $formData['fecha_nac'] = $datosApi['fechaNacimiento'] ?? null;
            Log::info('GestionConvenios Mount - fecha_nac set to:', ['value' => $formData['fecha_nac']]);

            // Llenar id_departamento y id_municipio del formulario principal
            $departamentoPrincipalId = GestionClienteResource::getDepartamentoIdPorNombre($datosApi['descripcionZona'] ?? null);
            $municipioPrincipalId = GestionClienteResource::getMunicipioIdPorNombre($datosApi['descripcionCiudad'] ?? null);
            $formData['id_departamento'] = $departamentoPrincipalId;
            $formData['id_municipio'] = $municipioPrincipalId;
            Log::info('GestionConvenios Mount - id_departamento set to:', ['value' => $formData['id_departamento']]);
            Log::info('GestionConvenios Mount - id_municipio set to:', ['value' => $formData['id_municipio']]);

            // --- CORREGIDO: Llenar repeaters como array de arrays ---
            $nombreCompletoKey = array_key_first($formData['clientesNombreCompleto'] ?? []);
            if ($nombreCompletoKey !== null) {
                $formData['clientesNombreCompleto'][$nombreCompletoKey]['Primer_nombre_cliente'] = isset($datosApi['nombre1']) ? (string) $datosApi['nombre1'] : '';
                $formData['clientesNombreCompleto'][$nombreCompletoKey]['Segundo_nombre_cliente'] = isset($datosApi['nombre2']) ? (string) $datosApi['nombre2'] : '';
                $formData['clientesNombreCompleto'][$nombreCompletoKey]['Primer_apellido_cliente'] = isset($datosApi['apellido1']) ? (string) $datosApi['apellido1'] : '';
                $formData['clientesNombreCompleto'][$nombreCompletoKey]['Segundo_apellido_cliente'] = isset($datosApi['apellido2']) ? (string) $datosApi['apellido2'] : '';
            }
            $contactoKey = array_key_first($formData['clientesContacto'] ?? []);
            if ($contactoKey !== null) {
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
                        Log::error('Error parseando fecha de expedición de API', ['fecha_api' => $fechaExpedicionApi, 'error' => $e->getMessage()]);
                    }
                }
                $formData['clientesContacto'][$contactoKey]['fecha_expedicion'] = $fechaExpedicionFormatted;
                $formData['clientesContacto'][$contactoKey]['correo'] = $datosApi['email'] ?? '';
                $formData['clientesContacto'][$contactoKey]['tel'] = $datosApi['celular'] ?? '';
                $formData['clientesContacto'][$contactoKey]['tel_alternativo'] = $datosApi['telefono1'] ?? '';
                $formData['clientesContacto'][$contactoKey]['residencia_id_departamento'] = $idDepartamentoResidencia;
                $formData['clientesContacto'][$contactoKey]['residencia_id_municipio'] = $datosApi['codigoCiudad'] ?? null;
                $formData['clientesContacto'][$contactoKey]['direccion'] = $datosApi['direccion'] ?? '';
                $formData['clientesContacto'][$contactoKey]['es_independiente'] = isset($datosApi['esIndependiente']) ? (bool) $datosApi['esIndependiente'] : false;
                $formData['clientesContacto'][$contactoKey]['empresa_labor'] = $datosApi['empresaLabora'] ?? '';
                $formData['clientesContacto'][$contactoKey]['tel_empresa'] = $datosApi['telefonoEmpresa'] ?? '';
            }

            // Mapear y asignar datos de dispositivosComprados al primer elemento inicializado
            $dispositivosCompradosKey = array_key_first($formData['dispositivosComprados'] ?? []);
            if ($dispositivosCompradosKey !== null) {
                 $nombrePlataformaApi = $datosApi['plataformaApi'] ?? null;
                 $idPlataformaApi = null;
                 if ($nombrePlataformaApi) {
                     $plataforma = \App\Models\ZPlataformaCredito::where('plataforma', $nombrePlataformaApi)->first();
                     if ($plataforma) {
                         $idPlataformaApi = $plataforma->idplataforma;
                     }
                 }

                 $formData['dispositivosComprados'][$dispositivosCompradosKey]['id_marca'] = $idPlataformaApi;
                 $formData['dispositivosComprados'][$dispositivosCompradosKey]['idmodelo'] = $datosApi['modelo'] ?? null;
                 $formData['dispositivosComprados'][$dispositivosCompradosKey]['idgarantia'] = $datosApi['garantia'] ?? null;
                 $formData['dispositivosComprados'][$dispositivosCompradosKey]['imei'] = $datosApi['imei'] ?? null;
                 $formData['dispositivosComprados'][$dispositivosCompradosKey]['nombre_producto'] = $nombrePlataformaApi ?? 'N/A';
            }

            // Mapear y asignar datos de detallesCliente al primer elemento inicializado
            $detallesClienteKey = array_key_first($formData['detallesCliente'] ?? []);
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
                 $formData['detallesCliente'][$detallesClienteKey]['id_tipo_credito'] = 2; // Always set to 2 for Convenio
            }

            // Llenar el formulario con los datos preparados
            // Al inicializar los repeaters con [0] = [] ANTES de fill(), Filament debería poder llenarlos
            $this->form->fill($formData);

            // Logs para verificar el estado final de los repeaters
             // Log::info('Estado final del formulario para clientesNombreCompleto:', [
             //     'state' => $this->form->getComponent('clientesNombreCompleto')?->getState() ?? 'Componente no encontrado',
             // ]);
              Log::info('Estado final del formulario para clientesContacto:', [
                 'state' => $this->form->getComponent('clientesContacto')?->getState() ?? 'Componente no encontrado',
             ]);
            // Log::info('Estado final del formulario para dispositivosComprados:', [
             //     'state' => $this->form->getComponent('dispositivosComprados')?->getState() ?? 'Componente no encontrado',
            // ]);
            // Log::info('Estado final del formulario para detallesCliente:', [
             //     'state' => $this->form->getComponent('detallesCliente')?->getState() ?? 'Componente no encontrado',
            // ]);

            // Logear el valor de la cédula directamente desde formData después de fill
            \Illuminate\Support\Facades\Log::info('GestionConvenios: Cédula en formData después de fill:', ['cedula' => $formData['cedula'] ?? 'N/A (no en formData)']);

            // Notificación de éxito
            Notification::make()
                ->title('Cliente encontrado')
                ->success()
                ->send();
        }

            //no permite acceso al rol de agente
      // if (auth()->user()?->hasRole('asesor_agente')) {
      //       abort(403);
      //   }
    }

    private function mapCodigoToTipoDocumento($codigo)
    {
        return match($codigo) {
            '13' => 'CC',
            '12' => 'TI',
            '22' => 'CE',
            '41' => 'PA',
            '11' => 'RC',
            '31' => 'NIT',
            default => 'CC',
        };
    }

    public function getTitle(): string
    {
        return 'Crear Referencia de Convenio';
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
        // Log para verificar los datos que se van a crear, incluyendo ID_Identificacion_Cliente y cedula
        \Illuminate\Support\Facades\Log::info('GestionConvenios: Datos antes de crear: ' . json_encode([
            'ID_Identificacion_Cliente' => $data['ID_Identificacion_Cliente'] ?? 'N/A',
            'cedula' => $data['cedula'] ?? 'N/A',
            'ID_tipo_credito_main_record' => $data['ID_tipo_credito'] ?? 'N/A',
            'detallesCliente' => $data['detallesCliente'] ?? 'No data',
        ]));

        // Asegurarse de que id_tipo_credito sea 2 para este formulario de Convenios
        $data['ID_tipo_credito'] = 2;


         // Normalizar por si quedó algún alias colgado
    if (!empty($data['detallesCliente']) && is_array($data['detallesCliente'])) {
        foreach ($data['detallesCliente'] as $i => $row) {
            // Si por alguna razón llegaron con alias, vuelca a columnas reales
            if (isset($row['id_asesor']) && !isset($row['ID_Asesor'])) {
                $data['detallesCliente'][$i]['ID_Asesor'] = $row['id_asesor'];
            }
            if (isset($row['id_sede']) && !isset($row['idsede'])) {
                $data['detallesCliente'][$i]['idsede'] = $row['id_sede'];
            }
            // Remueve alias para que no molesten
            unset($data['detallesCliente'][$i]['id_asesor'], $data['detallesCliente'][$i]['id_sede']);
        }
    }

        // Asegurarse de que los datos de detallesCliente incluyan el id_tipo_credito si existen
        if (isset($data['detallesCliente']) && is_array($data['detallesCliente'])) {
            foreach ($data['detallesCliente'] as $key => $detalle) {
                $data['detallesCliente'][$key]['id_tipo_credito'] = 2;
                \Illuminate\Support\Facades\Log::info('GestionConvenios: ID_tipo_credito en detallesCliente repeater', ['key' => $key, 'ID_tipo_credito_repeater' => $data['detallesCliente'][$key]['id_tipo_credito']]);
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {

         $cliente = $this->record->refresh();
         // Crea la fila en gestion si no existe
        $this->record->gestion()->firstOrCreate(
            ['id_cliente' => $this->record->id_cliente], // condiciones
            ['id_cliente' => $this->record->id_cliente], // valores
        );
        
        $record = $this->record;
        if (!$record) return;

        // Obtener el registro relacionado que ya fue creado por Filament
        $clienteNombreCompleto = $record->clientesNombreCompleto()->first();
        if ($clienteNombreCompleto) {
            $record->ID_Cliente_Nombre = $clienteNombreCompleto->ID_Cliente_Nombre;
            \Illuminate\Support\Facades\Log::info('DEBUG: ID_Cliente_Nombre de ClientesNombreCompleto (Convenios) antes de guardar en Cliente', [
                'ID_Cliente_Nombre' => $clienteNombreCompleto->ID_Cliente_Nombre,
                'Cliente ID' => $record->id_cliente
            ]);
            $record->save();
            \Illuminate\Support\Facades\Log::info('Registro ClientesNombreCompleto ya existente y Cliente actualizado en afterCreate (Convenios).', [
                'ID_Cliente_Nombre_saved' => $record->ID_Cliente_Nombre
            ]);
        }

          // 3) Evento LIGERO solo para sonido + refresh tabla
    try {
        event(new \App\Events\ClienteCreatedLight(
            clienteId: (int) $cliente->id_cliente,
            cedula: (string) ($cliente->cedula ?? ''),
            userId: auth()->id() ? (int) auth()->id() : null,
        ));

        \Log::debug('✅ [afterCreate] ClienteCreatedLight disparado', [
            'cliente_id' => $cliente->id_cliente,
        ]);
    } catch (\Throwable $e) {
        \Log::error('❌ [afterCreate] Error disparando ClienteCreatedLight: '.$e->getMessage(), [
            'cliente_id' => $cliente->id_cliente,
        ]);
    }

    }

    protected function afterSave(): void
    {
        $record = $this->record;
        if (!$record) return;

       
    }


       protected function getFormActions(): array
    {
        return [
            $this->getCancelFormAction(),
            $this->getCreateFormAction()->label('Crear'),
        ];
    }


    
}    