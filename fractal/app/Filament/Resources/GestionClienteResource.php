<?php

namespace App\Filament\Resources;

use Filament\Notifications\Actions\Action; // <- NOTIFICATION Action (se mantiene)
use Illuminate\Support\Facades\Log;
use App\Filament\Resources\GestionClienteResource\Pages;
use App\Filament\Resources\GestionClienteResource\RelationManagers;
use App\Models\GestionCliente;
use App\Models\Cliente;
use App\Services\TercerosApiService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use App\Models\ReferenciaPersonal1;
use App\Models\ZParentescoPersonal;
use App\Models\ZDepartamentos;
use App\Models\ZMunicipios;
use App\Models\ZTiempoConocerloNum;
use App\Models\ZTiempoConocerlo;
use Filament\Forms\Components\Repeater;
use App\Models\ZMarca;
use App\Models\ZModelo;
use App\Models\ZGarantia;
use App\Models\ZPago;
use App\Models\ZPlataformaCredito;
use App\Models\ZComision;
use App\Models\InfTrab;
use App\Models\ZPdvAgenteDistritec;
use App\Models\ClientesContacto;
use App\Models\CanalVentafd;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\HasOne;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Exception;
use Illuminate\Support\Facades\Redirect;
use Livewire\Livewire;
use App\Models\TipoDocumento;
use App\Models\TipoCredito;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Carbon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\IconColumn\IconColumnSize;
use App\Models\Imagenes;
use Illuminate\Support\Facades\Storage;

use Illuminate\Database\Eloquent\Model;

use App\Filament\Resources\GestionClienteResource\Widgets\GestionClienteStats;

use App\Models\Asesor;
use App\Models\AaPrin;
use App\Models\SocioDistritec;
use App\Models\Sede;

use Filament\Forms\Components\FileUpload;
use Illuminate\Filesystem\Filesystem;
use Filament\Tables\Columns\TextColumn;

Use App\Models\Zcuotas;

use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\BadgeColumn;

use App\Models\ZEstadocredito;

use App\Events\ClienteUpdated;
use Illuminate\Validation\Rule;
use App\Services\RecompraServiceValSimilar;
use Illuminate\Support\Facades\Auth;

// ✅ Imports para abrir el Chat como widget (sin modal)
use Filament\Tables\Actions\Action as TableAction; // evita conflicto con Notification Action
use App\Filament\Widgets\ChatWidget;

use Illuminate\Support\HtmlString;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;

use Filament\Forms\Components\Textarea;
use App\Models\Sistema_operativo;





class GestionClienteResource extends Resource
{
    protected static ?string $model = \App\Models\Cliente::class;
    protected static ?string $label = 'Gestión de Cliente';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    ////////////////////////////////////


    protected static function canAll(string $permission): bool
    {
        $u = auth()->user();
        if (! $u) return false;

        // super_admin viene de Filament Shield (por defecto: super_admin)
        $super = config('filament-shield.super_admin.name', 'super_admin');

        // admin / super_admin pasan siempre; el resto necesita el permiso
        return $u->hasAnyRole([$super, 'admin']) || $u->can($permission);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAll('view_any_gestion_clientes');
    }

    public static function canViewAny(): bool
    {
        return static::canAll('view_any_gestion_clientes');
    }

    public static function canCreate(): bool
    {
        return static::canAll('create_gestion_clientes');
    }

    // Estas usan la Policy para chequear dueño vs admin/super
    public static function canView(Model $record): bool
    {
        return auth()->user()->can('view', $record);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can('delete', $record);
    }

    public static function canDeleteAny(): bool
    {
        $u = auth()->user();
        $super = config('filament-shield.super_admin.name', 'super_admin');
        return $u?->hasAnyRole([$super, 'admin']) ?? false;
    }

    public static function form(Form $form): Form
    {

        $sucupoId = \App\Models\ZPlataformaCredito::where('plataforma', 'SUCUPO')->value('idplataforma');
        $isSucupo = function (Get $get) use ($sucupoId): bool {
            // intentamos varias rutas, y tomamos la primera que tenga contenido
            $detalles =
                $get('../../../detallesCliente')
                ?? $get('../../../../detallesCliente')
                ?? $get('../../detallesCliente')
                ?? $get('detallesCliente')
                ?? [];

            $primero = collect($detalles)->first();
            $idPlataforma = $primero['idplataforma'] ?? null;

            logger()->debug('IS_SUCUPO CHECK', [
                'detalles_raw' => $detalles,
                'primero' => $primero,
                'idPlataforma' => $idPlataforma,
                'sucupoId' => $sucupoId,
                'result' => (int) $idPlataforma === (int) $sucupoId,
            ]);

            return (int) $idPlataforma === (int) $sucupoId;
        };
        return $form
            ->schema([
                Hidden::make('user_id')
                    ->dehydrated(true)
                    ->default(fn () => auth()->id()),

                Wizard::make([
                    Step::make('Datos del Cliente')
                        ->schema([
                         //  Select::make('ID_Canal_venta')
                         //      ->label('Canal de Venta')
                         //      ->required()
                         //      ->options(\App\Models\CanalVentafd::pluck('canal', 'ID_Canal_venta'))
                         //      ->searchable()
                         //      ->preload(),
                            Hidden::make('ID_Canal_venta')->default(2),
                            Select::make('ID_Identificacion_Cliente')
                                ->label('Tipo de Documento')
                                ->required()
                                ->options(\App\Models\TipoDocumento::pluck('desc_identificacion', 'ID_Identificacion_Tributaria'))
                                ->searchable()
                                ->dehydrated()
                                ->preload(),
                            TextInput::make('cedula')
                                ->extraAttributes(['class' => 'text-lg font-bold'])
                                ->label('Cédula')
                                ->required()
                                ->numeric()
                                ->readOnly()
                                ->minLength(5)
                                ->maxLength(15)
                                ->validationMessages([
                                    'max_length' => 'Máximo 15 dígitos permitidos.',
                                    'min_length' => 'Mínimo 5 dígitos permitidos.',
                                ])
                                ->live()
                                ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()'])
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                    if ($state) {
                                        $notification = Notification::make()
                                            ->title('Cargando datos...')
                                            ->persistent();

                                        $notification->send();

                                        try {
                                            $apiService = app(TercerosApiService::class);
                                            $tipoDoc = $get('ID_Identificacion_Cliente') ?? 'CC';
                                            $codigoTipoDoc = self::mapTipoDocumentoToCodigo($tipoDoc);
                                            $tercero = $apiService->buscarPorCedula($state, $codigoTipoDoc);

                                            Log::info('API Response:', ['response' => $tercero]);

                                            if ($tercero && isset($tercero['datos']['nits'][0])) {
                                                $terceroData = $tercero['datos']['nits'][0];
                                                Log::info('Extracted Tercero Data:', ['data' => $terceroData]);

                                                $tipoDocumentoId = \App\Models\TipoDocumento::where('desc_identificacion', $terceroData['descTipoIdentificacionTrib'] ?? null)->value('ID_Identificacion_Tributaria');
                                                $set('ID_Identificacion_Cliente', $tipoDocumentoId);
                                                Log::info('Setting ID_Identificacion_Cliente:', ['value' => $tipoDocumentoId]);

                                                $set('cedula', $terceroData['nit'] ?? null);
                                                Log::info('Setting cedula:', ['value' => $terceroData['nit'] ?? null]);

                                                $municipioId = self::getMunicipioIdPorCodigo($terceroData['codigoCiudad'] ?? null);
                                                $departamentoId = self::getDepartamentoIdPorCodigoCiudad($terceroData['codigoCiudad'] ?? null);
                                                $fechaNacimiento = $terceroData['fechaNacimiento'] ? Carbon::parse($terceroData['fechaNacimiento'])->toDateString() : null;

                                                Log::info('Departamento data from API and ID:', ['name' => ($terceroData['descripcionZona'] ?? null), 'id' => $departamentoId]);
                                                Log::info('Municipio data from API and ID:', ['name' => ($terceroData['descripcionCiudad'] ?? null), 'id' => $municipioId]);
                                                Log::info('Fecha Nacimiento:', ['value' => $fechaNacimiento]);

                                                $set('id_departamento', $departamentoId);
                                                $set('id_municipio', $municipioId);
                                                $set('fecha_nac', $fechaNacimiento);

                                                Notification::make()
                                                    ->title('Cliente encontrado EXITOSAMENTE.')
                                                    ->success()
                                                    ->send();

                                                session()->put('convenio_buscado_data', $terceroData);
                                                session()->put('busqueda_tipo_documento', $tipoDoc);
                                            } else {
                                                Notification::make()
                                                    ->title('Cliente no encontrado en la API.')
                                                    ->warning()
                                                    ->send();
                                                Log::info('Cliente no encontrado en la API para cédula:', ['cedula' => $state]);
                                            }
                                        } catch (Exception $e) {
                                            Log::error('Error al buscar cliente en la API: ' . $e->getMessage(), ['exception' => $e]);
                                            Notification::make()
                                                ->title('Error al buscar cliente')
                                                ->body('Hubo un problema al intentar buscar los datos del cliente. Intente de nuevo.')
                                                ->danger()
                                                ->send();
                                        }
                                    }
                                }),

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
                                ->preload()
                                ->reactive()
                                ->default(null)
                                ->afterStateHydrated(function (Select $component, $state) {
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
                                    ->where('departamento_id', $get('id_departamento'))
                                    ->orderBy('name_municipio')
                                    ->pluck('name_municipio', 'id')
                                    ->toArray()
                                )
                                ->searchable()
                                ->default(null)
                                ->afterStateHydrated(function (Select $component, $state) {
                                    $livewire = $component->getContainer()->getLivewire();
                                    $isCreate = blank(data_get($livewire, 'record'));
                                    if ($isCreate) {
                                        $component->state(null);
                                    }
                                })
                                ->disabled(fn (Get $get) => blank($get('id_departamento'))),

                            DatePicker::make('fecha_nac')
                                ->label('Fecha de Nacimiento')
                                ->format('Y-m-d')
                                ->displayFormat('d/m/Y')
                                ->required()
                                ->maxDate(Carbon::yesterday())
                                ->rule('before:today'),

                            Section::make('Nombre Completo')
                                ->relationship('clientesNombreCompleto')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('Primer_nombre_cliente')
                                            ->label('Primer Nombre')
                                            ->maxLength(255)
                                            ->required()
                                            ->readOnly(),
                                        TextInput::make('Segundo_nombre_cliente')
                                            ->label('Segundo Nombre')
                                            ->maxLength(255)
                                            ->readOnly()
                                            ->nullable(),
                                        TextInput::make('Primer_apellido_cliente')
                                            ->label('Primer Apellido')
                                            ->maxLength(255)
                                            ->required()
                                            ->readOnly(),
                                        TextInput::make('Segundo_apellido_cliente')
                                            ->label('Segundo Apellido')
                                            ->maxLength(255)
                                            ->nullable()
                                            ->readOnly(),
                                    ])->columns(2),
                                ])->columns(1),

                            Section::make('Información de Contacto')
                                ->relationship('clientesContacto')
                                ->schema([
                                    Grid::make(2)->schema([
                                        DatePicker::make('fecha_expedicion')
                                            ->label('Fecha de Expedición')
                                            ->format('Y-m-d')
                                            ->displayFormat('d/m/Y')
                                            ->required()
                                            ->maxDate(Carbon::yesterday())
                                            ->rule('before:today'),
                                        TextInput::make('correo')
                                            ->label('Correo')
                                            ->email()
                                            ->maxLength(255)
                                            ->required()
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                if ($state) {
                                                    $svc = app(\App\Services\RecompraServiceValSimilar::class);
                                                    $valor = session('es_recompra');
                                                    $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'correo', 'El correo ' . $state . ' ya se encuentra registrado.','phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                }
                                            }),
                                        TextInput::make('tel')
                                            ->label('Teléfono')
                                            ->tel()
                                            ->minLength(10)
                                            ->maxLength(10)
                                            ->helperText('Debe tener exactamente 10 dígitos.')
                                            ->extraInputAttributes([
                                                'inputmode' => 'numeric',
                                                'pattern'   => '\d{10}',
                                                'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                            ])
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                                $telAlternativo = $get('tel_alternativo');
                                                $telefonoEmpresa = $get('telefono_empresa');

                                                if ($state && $telAlternativo && $state === $telAlternativo) {
                                                    Notification::make()
                                                        ->title('Error de validación')
                                                        ->body('El teléfono principal no puede ser igual al teléfono alternativo.')
                                                        ->danger()
                                                        ->send();
                                                    $set('tel', '');
                                                }

                                                if ($state && $telefonoEmpresa && $state === $telefonoEmpresa) {
                                                    Notification::make()
                                                        ->title('Error de validación')
                                                        ->body('El teléfono principal no puede ser igual al teléfono de la empresa.')
                                                        ->danger()
                                                        ->send();
                                                    $set('tel', '');
                                                }
                                                $svc = app(RecompraServiceValSimilar::class);
                                                $valor = session('es_recompra');
                                                $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel_alternativo', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);

                                                $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal1', 'Celular_rf1', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal2', 'Celular_rf2', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
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
                                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                                $tel             = $get('tel');
                                                $telefonoEmpresa = $get('telefono_empresa');

                                                if ($state) {
                                                    $svc = app(\App\Services\RecompraServiceValSimilar::class);
                                                    $valor = session('es_recompra');
                                                    Log::error('Valor de esRecompra en GestionclienteResouce'.  $valor);

                                                    $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel_alternativo', 'El telefono alterno ' . $state . ' ya se encuentra registrado.','phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                }

                                                if (! preg_match('/^\d{10}$/', (string) $state)) {
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
                                            }),

                                        Select::make('residencia_id_departamento')
                                            ->label('Departamento Residencia')
                                            ->options(ZDepartamentos::pluck('name_departamento', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(fn (Set $set) => $set('residencia_id_municipio', null))
                                            ->required()
                                            ->dehydrated(),

                                        Select::make('residencia_id_municipio')
                                            ->label('Ciudad Residencia')
                                            ->options(function (Get $get) {
                                                $departamentoId = $get('residencia_id_departamento');
                                                if ($departamentoId) {
                                                    return ZMunicipios::where('departamento_id', $departamentoId)->pluck('name_municipio', 'id');
                                                }
                                                return [];
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->dehydrated(),

                                        TextInput::make('direccion')
                                            ->label('Dirección')
                                            ->maxLength(255)
                                            ->required(),
                                           // ->readOnly(),

                                    ])->columns(2),
                                ])->columns(1),

                            Hidden::make('ID_Tipo_credito')->default(1),
                            Hidden::make('fecha_registro')->default(now()->format('Y-m-d')),
                            Hidden::make('hora')->default(now()->format('H:i:s')),
                        ]),

                    Step::make('Datos trabajo')
                        ->schema([
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
                                        ->required(fn (Get $get) => ! (bool) $get('es_independiente'))
                                        ->dehydrated(fn (Get $get) => ! (bool) $get('es_independiente'))
                                        ->disabled(fn (Get $get) => (bool) $get('es_independiente'))
                                        ->helperText(fn (Get $get) => $get('es_independiente') ? 'INDEPENDIENTE' : null),

                                    TextInput::make('num_empresa')
                                        ->label('Teléfono Empresa')
                                        ->tel()
                                        ->minLength(10)
                                        ->maxLength(10)
                                        ->required(fn (Get $get) => ! (bool) $get('es_independiente'))
                                        ->dehydrated(fn (Get $get) => ! (bool) $get('es_independiente'))
                                        ->disabled(fn (Get $get) => (bool) $get('es_independiente'))
                                        ->validationAttribute('teléfono de la empresa')
                                        ->helperText('Debe tener exactamente 10 dígitos.')
                                        ->unique('clientes_trabajo', 'num_empresa', ignoreRecord: true)
                                        ->extraInputAttributes([
                                            'inputmode' => 'numeric',
                                            'pattern'   => '\d{10}',
                                            'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                            'unique' => 'Este teléfono de la empresa ya se encuentra registrado.',
                                        ])
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                            if ($get('es_independiente')) return;

                                            $norm = fn ($v) => preg_replace('/\D+/', '', (string) $v);
                                            $empresa = $norm($state);
                                            if ($empresa === '') return;
                                            if (strlen($empresa) > 10) {
                                                $empresa = substr($empresa, 0, 10);
                                                $set('num_empresa', $empresa);
                                            }
                                            if (strlen($empresa) < 10) return;

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

                                            $svc = app(RecompraServiceValSimilar::class);
                                            $valor = session('es_recompra');
                                            $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                            $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel_alternativo', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);

                                            $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal1', 'Celular_rf1', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                            $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal2', 'Celular_rf2', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                        })
                                        ->rule(function (Get $get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                if ($get('es_independiente')) return;

                                                $norm = fn ($v) => preg_replace('/\D+/', '', (string) $v);
                                                $empresa = $norm($value);
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

                                    Checkbox::make('es_independiente')
                                        ->label('Es Independiente')
                                        ->reactive()
                                        ->afterStateUpdated(function (bool $state, Set $set) {
                                            if ($state) {
                                                $set('empresa_labor', null);
                                                $set('num_empresa', null);
                                            }
                                        }),

                                ])
                                ->deletable(false)
                                ->addable(false)
                                ->reorderable(false)
                                ->cloneable(false),

                        ]),

                    Step::make('Referencia 1')
                        ->schema([
                            Repeater::make('referenciasPersonales1')
                                ->relationship()
                                ->schema([
                                    Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('Primer_Nombre_rf1')
                                            ->label('Primer Nombre')
                                            ->required()
                                            ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        Forms\Components\TextInput::make('Segundo_Nombre_rf1')
                                            ->label('Segundo Nombre')
                                            ->nullable()
                                            ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                    ]),
                                    Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('Primer_Apellido_rf1')
                                            ->label('Primer Apellido')
                                            ->required()
                                            ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        Forms\Components\TextInput::make('Segundo_Apellido_rf1')
                                            ->label('Segundo Apellido')
                                            ->nullable()
                                            ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                    ]),
                                    Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('Celular_rf1')
                                            ->label('Celular')
                                            ->required()
                                            ->minLength(10)
                                            ->maxLength(10)
                                            ->rule('regex:/^\d{10}$/')
                                            ->validationAttribute('celular de la referencia 1')
                                            ->validationMessages([
                                                'required' => 'El celular de la referencia 1 es obligatorio.',
                                                'min'      => 'Debe tener exactamente 10 dígitos.',
                                                'max'      => 'Debe tener exactamente 10 dígitos.',
                                                'regex'    => 'Solo se permiten números (10 dígitos).',
                                            ])
                                            ->extraInputAttributes([
                                                'inputmode' => 'numeric',
                                                'pattern'   => '\d{10}',
                                                'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                            ])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                                if ($state) {
                                                    $svc = app(\App\Services\RecompraServiceValSimilar::class);
                                                    $valor = session('es_recompra');
                                                    $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal1', 'celular_rf1', 'celular ' . $state . ' ya se encuentra registrado.','phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                }

                                                $clean = $state ? preg_replace('/\D+/', '', (string) $state) : null;

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

                                                $set('../../rf1_cel_clean', $clean);

                                                $norm = fn ($v) => preg_replace('/\D+/', '', (string) $v);

                                                $contactos = $get('../../clientesContacto') ?? [];

                                                $tel             = $get('../../clientesContacto.tel');
                                                $telAlternativo  = $get('../../clientesContacto.tel_alternativo');

                                                $trabajos        = $get('../../clienteTrabajo') ?? [];
                                                $telefonoEmpresa = '';
                                                if (is_array($trabajos)) {
                                                    foreach ($trabajos as $row) {
                                                        $telefonoEmpresa = $norm($row['num_empresa'] ?? '');
                                                        break;
                                                    }
                                                }

                                                $telClean            = $tel             ? preg_replace('/\D+/', '', (string) $tel) : null;
                                                $telAlternativoClean = $telAlternativo  ? preg_replace('/\D+/', '', (string) $telAlternativo) : null;
                                                $telefonoEmpresaClean= $telefonoEmpresa ? preg_replace('/\D+/', '', (string) $telefonoEmpresa) : null;

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

                                                $svc = app(RecompraServiceValSimilar::class);
                                                $valor = session('es_recompra');
                                                $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel_alternativo', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);

                                                $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal1', 'Celular_rf1', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal2', 'Celular_rf2', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                            }),

                                        Select::make('Parentesco_rf1')
                                            ->label('Parentesco')
                                            ->required()
                                            ->visible(function (Get $get) {
                                                $detallesClienteState = $get('../detallesCliente');
                                                $tipoCredito = $detallesClienteState[0]['id_tipo_credito'] ?? null;
                                                return $tipoCredito !== 3;
                                            })
                                            ->options(ZParentescoPersonal::all()->pluck('parentesco', 'idparentesco')),
                                    ]),
                                    Grid::make(2)->schema([
                                        Checkbox::make('toda_la_vida')
                                            ->label('Toda la vida')
                                            ->reactive()
                                            ->columnSpan(2)
                                            ->visible(function (Get $get) {
                                                $detallesClienteState = $get('../detallesCliente');
                                                $tipoCredito = $detallesClienteState[0]['id_tipo_credito'] ?? null;
                                                return $tipoCredito !== 3;
                                            })
                                            ->afterStateUpdated(function (bool $state, Set $set) {
                                                if ($state) {
                                                    $set('idtiempo_conocerlonum', null);

                                                    $idTodaLaVida = ZTiempoConocerlo::whereRaw('LOWER(tiempo) = ?', ['toda la vida'])
                                                        ->value('idtiempoconocerlo');

                                                    if ($idTodaLaVida) {
                                                        $set('idtiempoconocerlo', $idTodaLaVida);
                                                    }
                                                }
                                            }),

                                        Select::make('idtiempo_conocerlonum')
                                            ->label('Tiempo de Conocerlo')
                                            ->required(fn (Get $get) => ! (bool) $get('toda_la_vida'))
                                            ->visible(function (Get $get) {
                                                $detallesClienteState = $get('../detallesCliente');
                                                $tipoCredito = $detallesClienteState[0]['id_tipo_credito'] ?? null;
                                                return $tipoCredito !== 3;
                                            })
                                            ->options(ZTiempoConocerloNum::all()->pluck('numeros', 'idtiempo_conocerlonum'))
                                            ->disabled(fn (Get $get) => (bool) $get('toda_la_vida'))
                                            ->dehydrated(),

                                        Select::make('idtiempoconocerlo')
                                            ->label('Tiempo de Conocerlo')
                                            ->required()
                                            ->visible(function (Get $get) {
                                                $detallesClienteState = $get('../detallesCliente');
                                                $tipoCredito = $detallesClienteState[0]['id_tipo_credito'] ?? null;
                                                return $tipoCredito !== 3;
                                            })
                                            ->options(ZTiempoConocerlo::all()->pluck('tiempo', 'idtiempoconocerlo'))
                                            ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                                if ($get('toda_la_vida')) {
                                                    $idTodaLaVida = ZTiempoConocerlo::whereRaw('LOWER(tiempo) = ?', ['toda la vida'])
                                                        ->value('idtiempoconocerlo');
                                                    if ($idTodaLaVida) {
                                                        $set('idtiempoconocerlo', $idTodaLaVida);
                                                    }
                                                }
                                            })
                                            ->disabled(fn (Get $get) => (bool) $get('toda_la_vida'))
                                            ->dehydrated(),
                                    ]),

                                ])
                                ->maxItems(1)
                                ->minItems(1)
                                ->deletable(false)
                                ->addable(false)
                                ->reorderable(false)
                                ->cloneable(false),
                        ]),

                    Step::make('Referencia 2')
                        ->schema([
                            Repeater::make('referenciasPersonales2')
                                ->relationship()
                                ->schema([
                                    Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('Primer_Nombre_rf2')
                                            ->label('Primer Nombre')
                                            ->required()
                                            ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        Forms\Components\TextInput::make('Segundo_Nombre_rf2')
                                            ->label('Segundo Nombre')
                                            ->nullable()
                                            ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                    ]),
                                    Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('Primer_Apellido_rf2')
                                            ->label('Primer Apellido')
                                            ->required()
                                            ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                        Forms\Components\TextInput::make('Segundo_Apellido_rf2')
                                            ->label('Segundo Apellido')
                                            ->nullable()
                                            ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                                    ]),
                                    Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('Celular_rf2')
                                            ->label('Celular')
                                            ->required()
                                            ->minLength(10)
                                            ->maxLength(10)
                                            ->rule('regex:/^\d{10}$/')
                                            ->validationAttribute('celular de la referencia 2')
                                            ->validationMessages([
                                                'required' => 'El celular de la referencia 2 es obligatorio.',
                                                'min'      => 'Debe tener exactamente 10 dígitos.',
                                                'max'      => 'Debe tener exactamente 10 dígitos.',
                                                'regex'    => 'Solo se permiten números (10 dígitos).',
                                            ])
                                            ->extraInputAttributes([
                                                'inputmode' => 'numeric',
                                                'pattern'   => '\d{10}',
                                                'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                            ])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                                if (! $state) return;

                                                \Illuminate\Support\Facades\Log::info('Todos los datos del formulario (rf2):', $get());

                                                $stateClean = preg_replace('/\D+/', '', (string) $state);
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

                                                $contactos = $get('../../clientesContacto') ?? [];

                                                $tel             = $get('../../clientesContacto.tel');
                                                $telAlternativo  = $get('../../clientesContacto.tel_alternativo');

                                                $trabajos        = $get('../../clienteTrabajo') ?? [];
                                                $telefonoEmpresa = '';
                                                if (is_array($trabajos)) {
                                                    foreach ($trabajos as $row) {
                                                        $telefonoEmpresa = $norm($row['num_empresa'] ?? '');
                                                        break;
                                                    }
                                                }

                                                $telClean             = $tel             ? preg_replace('/\D+/', '', (string) $tel) : null;
                                                $telAlternativoClean  = $telAlternativo  ? preg_replace('/\D+/', '', (string) $telAlternativo) : null;
                                                $telefonoEmpresaClean = $telefonoEmpresa ? preg_replace('/\D+/', '', (string) $telefonoEmpresa) : null;

                                                $celularRf1 = $get('../../referenciasPersonales1.0.Celular_rf1')
                                                    ?? $get('../../referenciasPersonales1.Celular_rf1')
                                                    ?? $get('../referenciasPersonales1.0.Celular_rf1')
                                                    ?? $get('../referenciasPersonales1.Celular_rf1')
                                                    ?? $get('../../rf1_cel_clean');

                                                $celularRf1Clean = $celularRf1 !== null ? preg_replace('/\D+/', '', (string) $celularRf1) : null;

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

                                                $svc = app(RecompraServiceValSimilar::class);
                                                $valor = session('es_recompra');
                                                $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                $svc->procesarRecompraPorValorSimilar($valor, $state, 'clientes_contacto', 'tel_alternativo', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);

                                                $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal1', 'Celular_rf1', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                                $svc->procesarRecompraPorValorSimilar($valor, $state, 'referencia_personal2', 'Celular_rf2', 'El telefono ' . $state. ' ya se encuentra registrado en otro lugar.', 'phone_exact',['pattern' => 'contains', 'normalize' => true]);
                                            }),
                                        Select::make('Parentesco_rf2')
                                            ->label('Parentesco')
                                            ->required()
                                            ->visible(function (Get $get) {
                                                $detallesClienteState = $get('../detallesCliente');
                                                $tipoCredito = $detallesClienteState[0]['id_tipo_credito'] ?? null;
                                                return $tipoCredito !== 3;
                                            })
                                            ->options(ZParentescoPersonal::all()->pluck('parentesco', 'idparentesco')),
                                    ]),
                                    Grid::make(2)->schema([
                                        Checkbox::make('toda_la_vida')
                                            ->label('Toda la vida')
                                            ->reactive()
                                            ->columnSpan(2)
                                            ->visible(function (Get $get) {
                                                $detalles = $get('../detallesCliente');
                                                $tipo     = $detalles[0]['id_tipo_credito'] ?? null;
                                                return $tipo !== 3;
                                            })
                                            ->afterStateUpdated(function (bool $state, Set $set) {
                                                if ($state) {
                                                    $set('idtiempo_conocerlonum', null);

                                                    $idTodaLaVida = \App\Models\ZTiempoConocerlo::whereRaw('LOWER(tiempo) = ?', ['toda la vida'])
                                                        ->value('idtiempoconocerlo');

                                                    if ($idTodaLaVida) {
                                                        $set('idtiempoconocerlo', $idTodaLaVida);
                                                    }
                                                }
                                            }),

                                        Select::make('idtiempo_conocerlonum')
                                            ->label('Tiempo de Conocerlo')
                                            ->required(fn (Get $get) => ! (bool) $get('toda_la_vida'))
                                            ->visible(function (Get $get) {
                                                $detalles = $get('../detallesCliente');
                                                $tipo     = $detalles[0]['id_tipo_credito'] ?? null;
                                                return $tipo !== 3;
                                            })
                                            ->options(\App\Models\ZTiempoConocerloNum::all()->pluck('numeros', 'idtiempo_conocerlonum'))
                                            ->disabled(fn (Get $get) => (bool) $get('toda_la_vida'))
                                            ->dehydrated(),

                                        Select::make('idtiempoconocerlo')
                                            ->label('Tiempo de Conocerlo')
                                            ->required(fn (Get $get) => ! (bool) $get('toda_la_vida'))
                                            ->visible(function (Get $get) {
                                                $detalles = $get('../detallesCliente');
                                                $tipo     = $detalles[0]['id_tipo_credito'] ?? null;
                                                return $tipo !== 3;
                                            })
                                            ->options(\App\Models\ZTiempoConocerlo::all()->pluck('tiempo', 'idtiempoconocerlo'))
                                            ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                                if ($get('toda_la_vida')) {
                                                    $idTodaLaVida = \App\Models\ZTiempoConocerlo::whereRaw('LOWER(tiempo) = ?', ['toda la vida'])
                                                        ->value('idtiempoconocerlo');
                                                    if ($idTodaLaVida) {
                                                        $set('idtiempoconocerlo', $idTodaLaVida);
                                                    }
                                                }
                                            })
                                            ->disabled(fn (Get $get) => (bool) $get('toda_la_vida'))
                                            ->dehydrated(),
                                    ]),
                                ])
                                ->disableItemCreation()
                                ->disableItemDeletion()
                                ->maxItems(1)
                                ->minItems(1)
                                ->deletable(false)
                                ->addable(false)
                                ->reorderable(false)
                                ->cloneable(false),
                        ]),

                    Step::make('Detalles Cliente')
                        ->schema([
                            Section::make('Detalles')
                                ->schema([
                                    Repeater::make('detallesCliente')
                                        ->relationship('detallesCliente')
                                        ->minItems(1)
                                        ->maxItems(1)
                                        ->defaultItems(1)

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
                                                Select::make('idplataforma')
                                                    ->label('Plataforma')
                                                    ->required()
                                                    ->options(\App\Models\ZPlataformaCredito::whereIn('plataforma', [
                                                        'PAYJOY','CELYA','CREDIMINUTO','KREDIYA','BRILLA',
                                                        'ALOCREDIT','ALCANOS','METROGAS','GASES DEL ORIENTE','VANTI', 'SUCUPO',
                                                    ])->pluck('plataforma', 'idplataforma'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->extraAttributes(['class' => 'text-lg font-bold'])
                                                     ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                        logger()->debug('PLATAFORMA UPDATED', [
                                                            'state' => $state,
                                                            // probamos rutas para ver cuál tiene data
                                                            'get(detallesCliente)' => $get('detallesCliente'),
                                                            'get(../detallesCliente)' => $get('../detallesCliente'),
                                                            'get(../../detallesCliente)' => $get('../../detallesCliente'),
                                                            'get(../../../detallesCliente)' => $get('../../../detallesCliente'),
                                                            'get(.)' => $get('.'),
                                                            'get(..)' => $get('..'),
                                                            'get(../..)' => $get('../..'),
                                                        ]);
                                                    }),

                                                Select::make('idcomision')
                                                ->label('Comisión')
                                                ->required()
                                                ->options(\App\Models\ZComision::all()->pluck('comision', 'id'))
                                                ->searchable()
                                                ->preload(),

                                                Select::make('codigo_asesor')
                                                    ->label('Código Asesor')
                                                    ->options(\App\Models\InfTrab::pluck('Codigo_vendedor', 'Codigo_vendedor'))
                                                    ->searchable()
                                                    ->reactive()
                                                    ->required()
                                                    ->disabled()
                                                    ->required(fn () => ! Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->hidden(fn ()   =>   Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
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
                                                                            $set('idsede',   $aaPrin->ID_Sede);
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
                                                                $set('idsede',   $infTrab->aaPrin->ID_Sede);
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
                                                            $set('idsede',    $infTrab->aaPrin->ID_Sede);
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
                                                            $set('idsede', $state);
                                                            $set('nombre_sede', $sede?->Name_Sede ?? '');
                                                        } else {
                                                            $set('idsede', null);
                                                            $set('nombre_sede', '');
                                                        }
                                                    })
                                                    ->extraAttributes(['class' => 'text-lg font-bold']),

                                                Forms\Components\TextInput::make('nombre_asesor')
                                                    ->label('Nombre Asesor')
                                                    ->disabled()
                                                    ->required(fn () => ! Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->hidden(fn ()   =>   Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->dehydrated(fn () => ! Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->extraAttributes(['class' => 'text-lg font-bold']),

                                                Forms\Components\TextInput::make('nombre_sede')
                                                    ->label('Sede')
                                                    ->disabled()
                                                    ->required(fn () => ! Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->hidden(fn ()   =>   Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->dehydrated(fn () => ! Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->extraAttributes(['class' => 'text-lg font-bold']),

                                                Hidden::make('ID_Asesor')->dehydrated(true),
                                                Hidden::make('ID_Socio')->dehydrated(false),
                                                Hidden::make('id_tipo_credito')->default(2),

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
                                                    ->required(fn ()   =>   Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->dehydrated(fn () =>   Auth::user()?->hasAnyRole(['agente_admin', 'asesor_agente'])),

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
                                        ])
                                        ->addable(false)
                                        ->deletable(false)
                                        ->reorderable(false)
                                        ->cloneable(false),
                                ]),
                        ]),

                    Step::make('Datos del Equipo')
                        ->schema([
                            Repeater::make('dispositivosComprados')
                                ->relationship()
                                ->schema([
                                    Grid::make(2)->schema([
                                        Select::make('id_marca')
                                            ->label('Marca del Dispositivo')
                                            ->required()
                                            //->options(ZMarca::all()->pluck('name_marca', 'idmarca'))
                                            ->options(function (Get $get) use ($isSucupo) {
                                                    // Si es SUCUPO: solo IPHONE (id 9)
                                                    if ($isSucupo($get)) {
                                                        return ZMarca::where('idmarca', 9)
                                                            ->pluck('name_marca', 'idmarca')
                                                            ->toArray();
                                                    }

                                                    // Si NO es SUCUPO: todas las marcas como ya lo tenías
                                                    return ZMarca::query()
                                                        ->pluck('name_marca', 'idmarca')
                                                        ->toArray();
                                                })
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->afterStateUpdated(function (Set $set) {
                                                $set('idmodelo', null);
                                            })
                                            ->extraAttributes(['class' => 'text-lg font-bold']),
                                        Select::make('idmodelo')
                                            ->label('Modelo del Dispositivo')
                                            ->required()
                                            ->options(function (Get $get) {
                                                $marcaId = $get('id_marca');
                                                if ($marcaId) {
                                                    return ZModelo::where('idmarca', $marcaId)
                                                        ->whereNotNull('name_modelo')
                                                        ->pluck('name_modelo', 'idmodelo');
                                                }
                                                return [];
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->extraAttributes(['class' => 'text-lg font-bold']),
                                        Select::make('estado_bateria')
                                            ->label('Estado de la Batería')
                                            ->options(collect(range(1, 100))->mapWithKeys(fn ($n) => [$n => "{$n}%"])->toArray())
                                            ->searchable()
                                            ->preload()          
                                            ->optionsLimit(100)  
                                            ->visible($isSucupo)
                                            ->required($isSucupo)
                                            ->dehydrated($isSucupo),

                                        Select::make('estado_pantalla')
                                            ->label('Estado de la Pantalla')
                                            ->options(collect(range(1, 10))->mapWithKeys(fn ($n) => [$n => "{$n}/10"])->toArray())
                                            ->searchable()
                                            ->visible($isSucupo)
                                            ->required($isSucupo)
                                            ->dehydrated($isSucupo),

                                        Select::make('ID_Sit_operativo')
                                            ->label('Sistema Operativo')
                                            ->options(\App\Models\Sistema_operativo::query()->pluck('name_sisOpet', 'ID_Sit_operativo')->toArray())
                                            ->searchable()
                                            ->visible($isSucupo)
                                            ->required($isSucupo)
                                            ->dehydrated($isSucupo),

                                        Textarea::make('observacion_equipo')
                                            ->label('Observaciones del Equipo')
                                            ->rows(4)
                                            ->maxLength(65535)
                                            ->columnSpanFull()
                                            ->visible($isSucupo)
                                            ->dehydrated($isSucupo),
                                                                                    
                                        Select::make('idgarantia')
                                            ->label('Garantía')
                                            ->required()
                                            //->options(ZGarantia::all()->pluck('garantia', 'idgarantia'))

                                            ->options(function (Get $get) use ($isSucupo) {
                                                    // Si es SUCUPO: solo IPHONE (id 9)
                                                    if ($isSucupo($get)) {
                                                        return ZGarantia::where('idgarantia', 3)
                                                            ->pluck('garantia', 'idgarantia')
                                                            ->toArray();
                                                    }

                                                    // Si NO es SUCUPO: todas las GARANTIAS 
                                                    return ZGarantia::query()
                                                        ->pluck('garantia', 'idgarantia')
                                                        ->toArray();
                                                })
                                            ->searchable()
                                            ->preload()
                                            ->extraAttributes(['class' => 'text-lg font-bold']),
                                        Forms\Components\TextInput::make('imei')
                                            ->label('IMEI')
                                            ->required()
                                            ->maxLength(15)
                                            ->validationMessages(['max_length' => 'Máximo 15 dígitos permitidos.'])
                                            ->extraAttributes(['class' => 'text-lg font-bold'])
                                            ->extraInputAttributes([
                                                'maxlength' => '15',
                                                'onInput' => 'this.value = this.value.toUpperCase()',
                                            ])
                                            ->afterStateUpdated(function (\Filament\Forms\Get $get, ?string $state) {
                                                $svc = app(RecompraServiceValSimilar::class);
                                                $svc->procesarRecompraPorValorSimilar('No', $state, 'dispositivos_comprados', 'imei', 'El imei '. $state .' del dispositivo ya se encuentra registrado en otro lugar.', 'valor_exacto',['pattern' => 'contains', 'normalize' => true]);
                                            }),
                                    ]),
                                ])
                                ->deletable(false)
                                ->addable(false)
                                ->reorderable(false)
                                ->cloneable(false),

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
                                            //->options(ZPago::all()->pluck('periodo_pago', 'idpago'))
                                            ->options(function (Get $get) use ($isSucupo) {
                                                    // Si es SUCUPO
                                                    if ($isSucupo($get)) {
                                                        return ZPago::where('idpago', 3)
                                                            ->pluck('periodo_pago', 'idpago')
                                                            ->toArray();
                                                    }

                                                    // Si NO es SUCUPO: todas las GARANTIAS 
                                                    return ZPago::query()
                                                        ->pluck('periodo_pago', 'idpago')
                                                        ->toArray();
                                                })
                                            ->searchable()
                                            ->preload()
                                            ->extraAttributes(['class' => 'text-lg font-bold']),
                                        Select::make('num_cuotas')
                                            ->label('Número de Cuotas')
                                            ->required()
                                            ->options(Zcuotas::all()->pluck('num_cuotas', 'idcuotas'))
                                            ->searchable(),
                                        TextInput::make('cuota_inicial')
                                            ->label('Cuota inicial')
                                            ->required()
                                            ->prefix('$')
                                            ->formatStateUsing(function ($state) {
                                                if ($state === null || $state === '') return $state;
                                                $digits = preg_replace('/\D+/', '', (string) $state);
                                                return $digits === '' ? '' : number_format((int) $digits, 0, ',', '.');
                                            })
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
                                            ->rules(['required'])
                                            ->validationAttribute('cuota inicial')
                                            ->extraInputAttributes([
                                                'inputmode' => 'numeric',
                                                'oninput'   => "
                                                    const digits = this.value.replace(/[^0-9]/g,'');
                                                    this.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                                ",
                                            ]),
                                        TextInput::make('valor_cuotas')
                                            ->label('Valor de cuotas')
                                            ->required()
                                            ->prefix('$')
                                            ->formatStateUsing(function ($state) {
                                                if ($state === null || $state === '') return $state;
                                                $digits = preg_replace('/\D+/', '', (string) $state);
                                                return $digits === '' ? '' : number_format((int) $digits, 0, ',', '.');
                                            })
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
                                            ->rules(['required'])
                                            ->validationAttribute('valor de cuotas')
                                            ->extraInputAttributes([
                                                'inputmode' => 'numeric',
                                                'oninput'   => "
                                                    const digits = this.value.replace(/[^0-9]/g,'');
                                                    this.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                                ",
                                            ]),
                                    ]),
                                ])
                                ->deletable(false)
                                ->addable(false)
                                ->reorderable(false)
                                ->cloneable(false),
                        ]),
                ])
                ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        // Helpers para compactar celdas/encabezados
        $compactCell = fn () => ['class' => 'py-1 px-2 text-[11px] whitespace-nowrap'];
        $compactHead = fn () => ['class' => 'py-1 px-2 text-[10px] uppercase tracking-wide'];

        return $table
            ->defaultSort('id_cliente', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id_cliente')
                    ->label('ID Cliente')
                     ->searchable()
                    ->sortable(),

            //   Tables\Columns\TextColumn::make('canalFD.canal')
            //       ->label('Canal de venta')
            //       ->sortable()
            //       ->searchable()
            //       ->toggleable()
            //       ->hidden(false)
            //       ->getStateUsing(function ($record) {
            //           $tipoVenId = $record->ID_Canal_venta;
            //           $canalfd = \App\Models\CanalVentafd::find($tipoVenId);
            //           
            //           \Illuminate\Support\Facades\Log::info('Debug canal Direct Find - ID Cliente: ' . $record->id_cliente . ', ID_Identificacion_Cliente: ' . ($tipoVenId ?? 'NULL') . ', TipoDocumento Direct Find Object: ' . json_encode($canalfd));

            //           if ($canalfd) {
            //               return $canalfd->canal ?? '';
            //           } else {
            //               $canalRelacion = $record->CanalVentafd;
            //               \Illuminate\Support\Facades\Log::info('Debug canal Relation - ID Cliente: ' . $record->id_cliente . ', TipoDocumento Relation Object: ' . json_encode($canalRelacion));
            //               return '';
            //           }
            //       }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->sortable()
                    ->searchable(),

                 IconColumn::make('documentacion')
                    ->label('Documentación')
                    ->getStateUsing(fn (Cliente $record) => true)
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->tooltip('Ver/Editar Documentos')
                    ->alignCenter()
                    ->size(IconColumnSize::Large)
                    ->sortable(false)
                    ->action(
                        Tables\Actions\Action::make('documentacion')
                            ->modalHeading('Documentación Cliente')
                            ->modalWidth('7xl')
                            ->modalContent(fn (Cliente $record) => view(
                                'filament.modals.documentation-modal-wrapper',
                                [
                                    'cliente'  => $record,
                                    'imagenes' => Imagenes::firstOrNew(['id_cliente' => $record->id_cliente]),
                                ]
                            ))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false)
                            ->modalCloseButton(true)
                            ->stickyModalHeader()
                    ),

                Tables\Columns\TextColumn::make('gestion.contImagenes_ID_SI_NO')
                    ->label('Estado Doc.')
                    ->sortable()
                    ->alignCenter()
                    ->searchable()
                    ->html()
                    ->formatStateUsing(function ($state) {
                        // Normaliza: null, '', '  ', '0', 0 => NO cargado
                        $raw = is_null($state) ? null : trim((string) $state);

                        // Cargado solo si es 1 (o equivalente numérico)
                        $isLoaded = ($raw !== null) && (intval($raw) === 1);

                        if ($isLoaded) {
                            // Verde: Documentación cargada
                            return new HtmlString(
                                '<span style="
                                    display:inline-block;
                                    padding:2px 10px;
                                    border-radius:9999px;
                                    background:#dcfce7;        /* verde claro */
                                    color:#065f46;             /* texto verde oscuro */
                                    border:1px solid #86efac;  /* borde */
                                    font-weight:600;
                                    font-size:12px;
                                    line-height:1;
                                    white-space:nowrap;
                                ">Documentación cargada</span>'
                            );
                        }

                        // Rojo: Sin documentación (valor vacío, null, distinto de 1)
                        return new HtmlString(
                            '<span style="
                                display:inline-block;
                                padding:2px 10px;
                                border-radius:9999px;
                                background:#fee2e2;        /* rojo claro */
                                color:#7f1d1d;             /* texto rojo oscuro */
                                border:1px solid #fca5a5;  /* borde */
                                font-weight:600;
                                font-size:12px;
                                line-height:1;
                                white-space:nowrap;
                            ">Sin documentación</span>'
                        );
                    }),


                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->getStateUsing(function ($record) {
                        $row = DB::table('clientes_nombre_completo')
                            ->select(['Primer_nombre_cliente','Segundo_nombre_cliente','Primer_apellido_cliente','Segundo_apellido_cliente'])
                            ->where('ID_Cliente', $record->id_cliente)
                            ->first();

                        if (!$row) return new \Illuminate\Support\HtmlString('N/A');

                        $nombres   = trim(collect([$row->Primer_nombre_cliente ?? null, $row->Segundo_nombre_cliente ?? null])->filter()->implode(' '));
                        $apellidos = trim(collect([$row->Primer_apellido_cliente ?? null, $row->Segundo_apellido_cliente ?? null])->filter()->implode(' '));

                        return new \Illuminate\Support\HtmlString(e($nombres) . '<br>' . e($apellidos));
                    })
                    ->html()
                    ->tooltip(fn ($state) => trim(preg_replace('/\s+/', ' ', strip_tags((string) $state))))
                    ->extraCellAttributes(function () use ($compactCell) { $a = $compactCell(); $a['class'] .= ' text-center'; return $a; })
                    ->extraHeaderAttributes(function () use ($compactHead) { $a = $compactHead(); $a['class'] .= ' text-center'; return $a; })
                    ->alignCenter()
                    ->toggleable()
                    ->sortable(false)
                    ->searchable(false),

                  Tables\Columns\TextColumn::make('tipoDocumento.siglas')
                    ->label('Tipo Doc.')
                    ->getStateUsing(function ($record) {
                        $tipoDocId = $record->ID_Identificacion_Cliente;
                        $tipoDocumento = \App\Models\TipoDocumento::find($tipoDocId);
                        if (! $tipoDocumento) return 'N/A';

                        $siglas = $tipoDocumento->siglas ?? $tipoDocumento->sigla ?? null;
                        if (! $siglas) {
                            $desc  = (string) ($tipoDocumento->desc_identificacion ?? '');
                            $words = preg_split('/\s+/', trim($desc), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                            $siglas = strtoupper(collect($words)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
                        }
                        $descUpper = strtoupper((string) ($tipoDocumento->desc_identificacion ?? ''));
                        if (strtoupper($siglas) === 'P' || str_contains($descUpper, 'PASAPORTE')) $siglas = 'PPT';
                        return $siglas ?: 'N/A';
                    })
                    ->extraCellAttributes(function () use ($compactCell) { $a = $compactCell(); $a['class'] .= ' text-center'; return $a; })
                    ->extraHeaderAttributes(function () use ($compactHead) { $a = $compactHead(); $a['class'] .= ' text-center'; return $a; })
                    ->alignCenter()
                    ->sortable(false)
                    ->searchable(false),

            //    Tables\Columns\TextColumn::make('tipoCredito.Tipo')
            //        ->label('Tipo de Crédito')
            //        ->sortable()
            //        ->searchable(),

              //  Tables\Columns\TextColumn::make('fecha_registro')
              //      ->label('Fecha Registro')
              //      ->date(),

                Tables\Columns\TextColumn::make('cedula')
                    ->label('Cédula')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->hidden(false)
                    ->getStateUsing(function ($record): ?string {
                        $cedula = $record->cedula;
                        \Illuminate\Support\Facades\Log::info('Debug Cedula - ID Cliente: ' . $record->id_cliente . ', Cedula: ' . ($cedula ?? 'NULL'));
                        return $cedula;
                    }),
                 
                Tables\Columns\TextColumn::make('gestion.gestorDistritec.Nombre_gestor')
                    ->label('N.Auxiliar')
                    ->sortable()
                    ->searchable(),

                // ✅ NUEVO: Icono Chat que abre WIDGET (sin modal)
                IconColumn::make('chat')
                    ->label('Chat')
                    ->getStateUsing(fn (Cliente $record) => true)
                    ->icon('heroicon-o-chat-bubble-left')
                    ->color('primary')
                    ->tooltip('Abrir chat')
                    ->alignCenter()
                    ->size(IconColumnSize::Large)
                    ->sortable(false)
                    ->extraAttributes(fn (Cliente $record) => [
                        'class' => '!text-primary-600 hover:!text-primary-700 cursor-pointer',
                        'style' => 'min-width: 2.5rem;',
                        'data-client-id' => $record->id_cliente,
                    ])
                    ->action(function (Cliente $record, $livewire) {
                        $id   = (int) $record->id_cliente;
                        $chat = \App\Filament\Widgets\ChatWidget::class;

                        // 🔹 SOLO ESTO: setClientId ya:
                        //  - abre la ventana
                        //  - des-minimiza
                        //  - carga mensajes
                        //  - suscribe al canal
                        //  - marca como leídos
                        $livewire->dispatch('setClientId', clientId: $id)->to($chat);

                        // Si quieres asegurarte de que venga al frente:
                        // $livewire->dispatch('bringToFront')->to($chat);
                    }),

            //    Tables\Columns\TextColumn::make('hora')
            //        ->label('Hora'),

               
            TextColumn::make('gestion.estadoCredito.Estado_Credito')
                ->label('Estado Crédito')
                ->sortable()
                ->searchable()
                ->toggleable()
                ->html()
                ->formatStateUsing(function ($state) {
                    $raw = is_null($state) ? '' : trim((string) $state);
                    $upper = mb_strtoupper($raw);

                    // Estilos “pill” por estado
                    $base = 'display:inline-block;padding:2px 10px;border-radius:9999px;font-weight:600;font-size:12px;line-height:1;white-space:nowrap;border:1px solid;';
                    $styles = match ($upper) {
                        'APROBADO' => $base.'background:#dcfce7;color:#065f46;border-color:#86efac;',   // verde
                        'PENDIENTE'=> $base.'background:#fef9c3;color:#854d0e;border-color:#fde68a;',   // amarillo
                        'NO APROBADO' => $base.'background:#fee2e2;color:#7f1d1d;border-color:#fca5a5;', // rojo
                        default => $base.'background:#e5e7eb;color:#374151;border-color:#d1d5db;',      // gris por defecto
                    };

                    // Texto normalizado (mantén tus labels originales si difieren)
                    $label = $upper !== '' ? $upper : 'SIN ESTADO';

                    return new HtmlString("<span style=\"{$styles}\">{$label}</span>");
                }),

                IconColumn::make('ver_token')
                    ->label('Gestionar Token')
                    ->getStateUsing(fn ($record) => true)
                    ->icon('heroicon-o-key')
                    ->color('primary')
                    ->tooltip('Gestionar Token')
                    ->url(fn (Cliente $record) => GestionClienteResource::getUrl(
                        'token',
                        ['record' => $record->getKey()],
                    ))
                    ->openUrlInNewTab(false)
                    ->alignCenter()
                    ->size('lg'),

                TextColumn::make('token.authentication_token')
                    ->label('Estado Token')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        1 => 'Pendiente',
                        2 => 'Confirmado',
                        3 => 'Enviado SMS',
                        4 => 'Enviado Email',
                        5 => 'Enviado WhatsApp',
                        default => 'Sin generar',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipoCredito.Tipo')
                    ->label('Tipo de Crédito')
                    ->extraCellAttributes(function () use ($compactCell) { $a = $compactCell(); $a['class'] .= ' text-center'; return $a; })
                    ->extraHeaderAttributes(function () use ($compactHead) { $a = $compactHead(); $a['class'] .= ' text-center'; return $a; })
                    ->alignCenter()
                    ->sortable()
                    ->searchable(),


                

           //    Tables\Columns\BadgeColumn::make('persona.estado')
           //        ->label('Estado Crédito')
           //        ->colors([
           //            'success' => 'aprobado',
           //            'danger' => 'no aprobado',
           //        ])
           //        ->formatStateUsing(fn (string $state) => strtoupper($state)),

                Tables\Columns\TextColumn::make('persona.comentarios')
                    ->label('Comentarios'),

            ])
              ->filters([
                    // ✅ Filtro por Estado Doc.
                    SelectFilter::make('estado_doc')
                        ->label('Estado Doc.')
                        ->options([
                            'cargada'  => 'Documentación cargada',
                            'pendiente'=> 'Sin documentación',
                        ])
                        ->placeholder('Todos')
                        ->query(function (Builder $query, array $data) {
                            $val = $data['value'] ?? null;

                            if ($val === 'cargada') {
                                // contImagenes_ID_SI_NO == 1
                                $query->whereHas('gestion', function (Builder $q) {
                                    $q->where('contImagenes_ID_SI_NO', 1);
                                });
                            } elseif ($val === 'pendiente') {
                                // Sin documentación: distinto de 1, vacío, null, o sin relación
                                $query->where(function (Builder $q) {
                                    $q->doesntHave('gestion')
                                    ->orWhereHas('gestion', function (Builder $qq) {
                                        $qq->whereNull('contImagenes_ID_SI_NO')
                                            ->orWhere('contImagenes_ID_SI_NO', '<>', 1)
                                            ->orWhere('contImagenes_ID_SI_NO', ''); // string vacío
                                    });
                                });
                            }
                        })
                        ->indicateUsing(function (array $data): ?string {
                            return match ($data['value'] ?? null) {
                                'cargada'   => 'Estado Doc.: Cargada',
                                'pendiente' => 'Estado Doc.: Sin documentación',
                                default     => null,
                            };
                        }),
                ])
            ->actions([])
            ->headerActions([
                // ...
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGestionClientes::route('/'),
            'create' => Pages\CreateGestionCliente::route('/create'),
            'gestion-convenios' => Pages\GestionConvenios::route('/gestion-convenios'),
            'gestion-agentes' => Pages\GestionAgentes::route('/gestion-agentes'),
            'create-cliente-modal' => Pages\CreateClienteModal::route('/create-cliente-modal'),
            'firma'  =>  Pages\FirmaGestionCliente::route('/{record}/firma'),
            'token'  =>  Pages\TokenGestionCliente::route('/{record}/token'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tipo_dependencia'] = session('tipo_dependencia'); // +

        Log::info('clientesNombreCompleto:', $data['clientesNombreCompleto'] ?? []);
        if (!empty($data['clientesNombreCompleto']) && count($data['clientesNombreCompleto']) > 1) {
            $data['clientesNombreCompleto'] = [$data['clientesNombreCompleto'][0]];
        }
        return $data;
    }

    public static function getDepartamentoIdPorNombre($nombre)
    {
        Log::info('getDepartamentoIdPorNombre - Input Name:', ['name' => $nombre]);
        $id = ZDepartamentos::where('name_departamento', $nombre)->value('id');
        Log::info('getDepartamentoIdPorNombre - Result ID:', ['id' => $id]);
        return $id;
    }

    public static function getMunicipioIdPorNombre($nombre)
    {
        Log::info('getMunicipioIdPorNombre - Input Name:', ['name' => $nombre]);
        $id = ZMunicipios::where('name_municipio', $nombre)->value('id');
        Log::info('getMunicipioIdPorNombre - Result ID:', ['id' => $id]);
        return $id;
    }

    public static function mapDescripcionToTipoDocumento($descripcion)
    {
        return match (strtoupper($descripcion)) {
            'CÉDULA DE CIUDADANÍA' => 'CC',
            'TARJETA DE IDENTIDAD' => 'TI',
            'CÉDULA DE EXTRANJERÍA' => 'CE',
            'PASAPORTE' => 'PA',
            'REGISTRO CIVIL' => 'RC',
            'NIT' => 'NIT',
            default => null,
        };
    }

    public static function mapTipoDocumentoToCodigo($tipo)
    {
        return match ($tipo) {
            'CC' => '13',
            'TI' => '12',
            'CE' => '22',
            'PA' => '41',
            'RC' => '11',
            'NIT' => '31',
            default => '13',
        };
    }

public static function getEloquentQuery(): Builder
    {
        $u     = auth()->user();
        $super = config('filament-shield.super_admin.name', 'super_admin');

        return parent::getEloquentQuery()
            ->when(! $u?->hasAnyRole([$super, 'admin']), function (Builder $q) use ($u) {
                $q->where('user_id', $u->id);
            })
            ->with([
                'token',
                'gestion.gestorDistritec',
                'gestion.estadoCredito',
                'clientesNombreCompleto',
                'clientesContacto',
                'tipoDocumento',
            ]);
    }


  //  public static function getEloquentQuery(): Builder
  //  {
  //      $u     = auth()->user();
  //      $super = config('filament-shield.super_admin.name', 'super_admin');
//
  //      return parent::getEloquentQuery()
  //          ->when(! $u?->hasAnyRole([$super, 'admin']), function (Builder $q) use ($u) {
  //              $q->where('user_id', $u->id);
  //          })
  //          ->with([
  //              'token',
  //              'gestion.gestorDistritec',
  //              'gestion.estadoCredito',
  //              'clientesNombreCompleto',
  //              'clientesContacto',
  //              'tipoDocumento',
  //          ]);
  //  }

    protected function afterCreate(): void
    {
        $record = $this->record;
        if (!$record) return;

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

    }

    public static function getMunicipioIdPorCodigo($codigo)
    {
        return \App\Models\ZMunicipios::where('codigo_api', $codigo)->value('id');
    }

    public static function getWidgets(): array
    {
        return []; // nada aquí
    }

    public static function getDepartamentoIdPorCodigoCiudad($codigoCiudad)
    {
        $codigoDepartamento = substr($codigoCiudad, 0, 2);
        return \App\Models\ZDepartamentos::where('codigo_api', $codigoDepartamento)->value('id');
    }
}
