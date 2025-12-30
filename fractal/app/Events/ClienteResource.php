<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use App\Models\ZMarca;
use App\Models\ZModelo;
use App\Models\ZGarantia;
use App\Models\ZPago;
use App\Models\ZParentescoPersonal;
use App\Models\ZTiempoConocerloNum;
use App\Models\ZTiempoConocerlo;
use App\Models\ZPlataformaCredito;
use App\Models\ZComision;
use App\Models\InfTrab;
use App\Models\ZPdvAgenteDistritec;
use App\Models\ZDepartamentos;
use App\Models\ZMunicipios;
use App\Models\EstadoCredito;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn\IconColumnSize;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Button;  // importa el Button de Forms

use Filament\Tables\Columns\SelectColumn;
use App\Models\GestorDistritec;

use App\Models\Imagenes;

use App\Events\ClienteUpdated;

use App\Filament\Widgets\ChatWidget; // ✅ correcto (NO Resources)


use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Model;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

use App\Services\RecompraServiceValSimilar; 

use Closure;
use Illuminate\Support\Carbon;
Use App\Models\Zcuotas;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Log;
use App\Models\ZEstadocredito;
use Illuminate\Support\Str;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use App\Models\Agentes;
use App\Models\Sede;

use App\Events\EstadoCreditoUpdated;
use Illuminate\Support\Facades\Auth;





class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Gestión Clientes';
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';

 

protected static function canAll(string $permission): bool
{
    $u = auth()->user();
    if (! $u) return false;

    $super = config('filament-shield.super_admin.name', 'super_admin');

    // Siempre permitir a super_admin / admin
    if ($u->hasAnyRole([$super, 'admin'])) {
        return true;
    }

    // Bypass sólo si llega por URL firmada de token y es el mismo usuario
    if (
        request()->hasValidSignature() &&
        request()->query('via') === 'token' &&
        (int) request()->query('uid') === (int) $u->id
    ) {
        return true;
    }

    // 👉 Usa EL permiso que recibes
    return $u->can($permission);
}

// Muestra el recurso en el menú si tiene permiso o es admin/super_admin
public static function shouldRegisterNavigation(): bool
{
    $u = auth()->user();
    $super = config('filament-shield.super_admin.name', 'super_admin');

    if ($u?->hasAnyRole([$super, 'admin'])) {
        return true;
    }

    // Permiso de listado de Shield
    return $u?->can('view_any_clientes') ?? false;
}

public static function canViewAny(): bool
{
    return static::canAll('view_any_clientes');
}

public static function canView(Model $record): bool
{
    $u = auth()->user();
    $super = config('filament-shield.super_admin.name', 'super_admin');

    if ($u?->hasAnyRole([$super, 'admin'])) return true;
    if (static::canViaToken($record)) return true;

    // tu regla normal
    return $u?->can('view_clientes') ?? false;
}
public static function canCreate(): bool
{
    return static::canAll('create_clientes');
}

public static function canEdit(Model $record): bool
{
    $u = auth()->user();
    $super = config('filament-shield.super_admin.name', 'super_admin');

    if ($u?->hasAnyRole([$super])) return true;
    if (static::canViaToken($record)) return true;

    // tu regla normal
    return $u?->can('update_clientes') ?? false;
}

public static function canUpdate(Model $record): bool
{
    return static::canEdit($record);
}

public static function canDelete(Model $record): bool
{
    return static::canAll('delete_clientes');
}

public static function canDeleteAny(): bool
{
    return static::canAll('delete_clientes');
}

protected static function canViaToken(Model $record): bool
{
    $u = auth()->user();
    if (! $u) return false;

    // 1) Primera entrada con URL firmada: permitimos y emitimos un "ticket" de sesión por 10 min
    if (
        request()->hasValidSignature() &&
        request()->query('via') === 'token' &&
        (int) request()->query('uid') === (int) $u->id
    ) {
        session()->put("allow_edit_client.{$record->getKey()}", now()->addMinutes(10)->timestamp);
        return true;
    }

    // 2) Requests subsecuentes (POST Livewire, redirects): validamos el "ticket"
    $ts = session("allow_edit_client.{$record->getKey()}");
    return $ts && $ts >= now()->timestamp;
}


private static function resolveCurrentClientId(TextInput $component, Get $get): ?int
{
    // 1) estado del form
    $fromState = $get('id_cliente') ?? $get('ID_Cliente') ?? null;
    if (is_numeric($fromState)) return (int) $fromState;

    // 2) distintos contextos de Filament/Livewire
    $lw = $component->getContainer()->getLivewire();

    // a) Page/Edit
    $id = data_get($lw, 'record.id_cliente') ?? data_get($lw, 'record.id') ?? null;
    if (is_numeric($id)) return (int) $id;

    // b) Table actions (View/Edit)
    $mounted = data_get($lw, 'mountedTableActionRecord');
    $id = data_get($mounted, 'id_cliente') ?? data_get($mounted, 'id') ?? null;
    if (is_numeric($id)) return (int) $id;

    // c) Relation managers
    $owner = method_exists($lw, 'getOwnerRecord') ? $lw->getOwnerRecord() : null;
    $id = data_get($owner, 'id_cliente') ?? data_get($owner, 'id') ?? null;
    if (is_numeric($id)) return (int) $id;

    return null;
}

private static function countOwnersForPhone(string $digits, ?int $currentId): int
{
    static $cache = [];
    $key = $digits . '|' . ($currentId ?? 'null');
    if (array_key_exists($key, $cache)) return $cache[$key];

    // Tablas/columnas donde existe el teléfono y la columna del dueño
    $fuentes = [
        ['clientes_contacto',    'tel',             'id_cliente'],
        ['clientes_contacto',    'tel_alternativo', 'id_cliente'],
        ['referencia_personal1', 'Celular_rf1',     'ID_Cliente'],
        ['referencia_personal2', 'Celular_rf2',     'ID_Cliente'],
    ];

    $owners = [];
    foreach ($fuentes as [$tabla, $col, $ownCol]) {
        $q = DB::table($tabla)
            ->select($ownCol)
            // Normaliza a solo dígitos en SQL (MySQL 8+)
            ->whereRaw("REGEXP_REPLACE(CAST($col AS CHAR), '[^[:digit:]]+', '') = ?", [$digits])
            ->when(!is_null($currentId), fn ($q) => $q->where($ownCol, '!=', $currentId))
            ->distinct();

        foreach ($q->pluck($ownCol) as $ownerId) {
            if ($ownerId !== null) $owners[$ownerId] = true; // set
        }
    }

    return $cache[$key] = count($owners);
}


private static function countCedula(string $digits, ?int $currentId): array
{
    static $cache = []; // key => [total, otros]
    $key = $digits.'|'.($currentId ?? 'null');
    if (isset($cache[$key])) return $cache[$key];

    $baseQuery = DB::table('clientes')
        // MySQL 8+: normalizar a solo dígitos en SQL
        ->whereRaw("REGEXP_REPLACE(CAST(cedula AS CHAR), '[^0-9]+', '') = ?", [$digits]);

    $total = (clone $baseQuery)->count();

    $otros = !is_null($currentId)
        ? (clone $baseQuery)->where('id_cliente', '!=', $currentId)->count()
        : $total; // si no sabemos el ID, "otros" = total (conservador)

    return $cache[$key] = [$total, $otros];
}




    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('cedula')
                    ->label('Cédula')
                    ->required()
                    ->maxLength(10)
                    ->live(debounce: 500)
                    ->dehydrateStateUsing(fn ($state) => preg_replace('/\D+/', '', (string) $state))

                    ->extraAttributes(function (TextInput $component, Get $get) {
                        $digits = preg_replace('/\D+/', '', (string) $get('cedula'));
                        $ready  = strlen($digits) === 10;

                        // ID actual (funciona en Page, ViewAction o RelationManager)
                        $lw = $component->getContainer()->getLivewire();
                        $currentId = data_get($lw, 'record.id_cliente')
                            ?? data_get($lw, 'record.id')
                            ?? data_get($lw, 'mountedTableActionRecord.id_cliente')
                            ?? data_get($lw, 'mountedTableActionRecord.id')
                            ?? (method_exists($lw, 'getOwnerRecord') ? data_get($lw->getOwnerRecord(), 'id_cliente') : null)
                            ?? null;

                        $otros = 0;
                        if ($ready) {
                            $base = DB::table('clientes')
                                ->whereRaw("REGEXP_REPLACE(CAST(cedula AS CHAR), '[^0-9]+', '') = ?", [$digits]);

                            $otros = is_null($currentId)
                                ? $base->count()
                                : $base->where('id_cliente', '!=', $currentId)->count();
                        }

                        $dupByOthers = $otros >= 1;
                        $baseClass = 'group/ced rounded-lg';

                        return [
                            'class' => $dupByOthers
                                ? $baseClass.' ced-dup !ring-2 !ring-orange-500 dark:!ring-orange-400 focus-within:!ring-orange-600 !ring-offset-0 focus-within:!ring-offset-0'
                                : $baseClass,
                            'style' => $dupByOthers ? '--tw-ring-color:#f97316;' : null,
                        ];
                    })

                    ->extraInputAttributes(function (TextInput $component, Get $get) {
                        $digits = preg_replace('/\D+/', '', (string) $get('cedula'));
                        $ready  = strlen($digits) === 10;

                        $lw = $component->getContainer()->getLivewire();
                        $currentId = data_get($lw, 'record.id_cliente')
                            ?? data_get($lw, 'record.id')
                            ?? data_get($lw, 'mountedTableActionRecord.id_cliente')
                            ?? data_get($lw, 'mountedTableActionRecord.id')
                            ?? (method_exists($lw, 'getOwnerRecord') ? data_get($lw->getOwnerRecord(), 'id_cliente') : null)
                            ?? null;

                        $otros = 0;
                        if ($ready) {
                            $base = DB::table('clientes')
                                ->whereRaw("REGEXP_REPLACE(CAST(cedula AS CHAR), '[^0-9]+', '') = ?", [$digits]);

                            $otros = is_null($currentId)
                                ? $base->count()
                                : $base->where('id_cliente', '!=', $currentId)->count();
                        }

                        $dupByOthers = $otros >= 1;

                        $classes = [
                            'rounded-lg',
                            'border !border-gray-300 dark:!border-gray-700',
                            'transition-colors',
                        ];

                        if ($dupByOthers) {
                            $classes = array_merge($classes, [
                                '!bg-orange-100','!text-orange-900','placeholder:!text-orange-700',
                                'dark:!bg-orange-900/60','dark:!text-orange-100','dark:placeholder:!text-orange-300',
                                '!border-2','!border-orange-500','dark:!border-orange-400','!shadow-sm',
                            ]);
                        }

                        return [
                            'oninput' => "this.value=this.value.replace(/\\D/g,'').slice(0,10);",
                            'pattern' => '[0-9]+',
                            'aria-invalid' => $dupByOthers ? 'true' : 'false',
                            'title'        => $dupByOthers ? 'Cédula ya usada en otro registro' : null,
                            'class'        => implode(' ', $classes),
                        ];
                    })

                    // 👇 hint sin $component
                    ->hint(function (Get $get) {
                        $digits = preg_replace('/\\D+/', '', (string) $get('cedula'));
                        if (strlen($digits) !== 10) return null;

                        $total = DB::table('clientes')
                            ->whereRaw("REGEXP_REPLACE(CAST(cedula AS CHAR), '[^0-9]+', '') = ?", [$digits])
                            ->count();

                        return $total >= 2 ? "Esta cédula aparece {$total} veces en el sistema." : null;
                    })
                    ->hintIcon(null)
                    ->hintColor('warning')

                    ->rule('regex:/^[0-9]{0,10}$/')
                    ->validationMessages([
                        'max_length' => 'Máximo 10 dígitos permitidos.',
                        'regex'      => 'Solo se permiten números.',
                    ])

                    // 👇 afterStateUpdated sin $component
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get) {
                        $digits = preg_replace('/\\D+/', '', (string) $state);
                        if (strlen($digits) !== 10) return;

                        $currentId = $get('id_cliente') ?? $get('ID_Cliente') ?? null;

                        $base = DB::table('clientes')
                            ->whereRaw("REGEXP_REPLACE(CAST(cedula AS CHAR), '[^0-9]+', '') = ?", [$digits]);

                        $otros = is_null($currentId)
                            ? $base->count()
                            : $base->where('id_cliente', '!=', $currentId)->count();

                        if ($otros >= 1) {
                            Notification::make()
                                ->title('Cédula duplicada')
                                ->body("La cédula {$digits} ya existe en otro registro.")
                                ->warning()
                                ->send();
                        }
                    }),



                DatePicker::make('fecha_nac')
                    ->label('Fecha de Nacimiento')
                    ->required()
                    ->displayFormat('d/m/Y') // Formato de visualización (opcional)
                    ->native(false)
                    ->format('Y-m-d')
                    ->rules([
                        'date',
                        'before:today', // Solo fechas pasadas
                    ])
                    ->placeholder('Selecciona una fecha'),
                
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


                
                    // Campo oculto para ID_Cliente_Nombre (si es necesario)
                    Forms\Components\Hidden::make('ID_Cliente_Nombre'),
                    
                    // Sección para nombres y apellidos (Relación HasOne)
                    Forms\Components\Section::make('Nombres y Apellidos')
                        ->relationship('clientesNombreCompleto') // Especificar explícitamente la relación HasOne
                        ->columns(4) // Usar 4 columnas como en la creación si es posible, o ajustar según diseño
                        ->schema([
                            Forms\Components\TextInput::make('Primer_nombre_cliente')
                                ->label('Primer Nombre')
                                ->required(),
                            //   ->extraInputAttributes([
                            //       'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                            //       'onblur'  => "this.value = this.value.toUpperCase();",
                            //   ])
                            //   ->rule('alpha')
                            //   ->validationMessages([
                            //       'alpha' => 'Solo se permiten letras en el nombre.',
                            //       'required' => 'El primer nombre es obligatorio.',
                            //   ]),

                            Forms\Components\TextInput::make('Segundo_nombre_cliente')
                                ->label('Segundo Nombre')
                                ->nullable(),
                            // ->extraInputAttributes([
                            //     'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                            //     'onblur'  => "this.value = this.value.toUpperCase();",
                            // ])
                            // ->rule('alpha')
                            // ->validationMessages([
                            //     'alpha' => 'Solo se permiten letras en el nombre.',
                            // ]),

                            Forms\Components\TextInput::make('Primer_apellido_cliente')
                                ->label('Primer Apellido')
                                ->required(),
                            //   ->extraInputAttributes([
                            //      'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                            //      'onblur'  => "this.value = this.value.toUpperCase();",
                            //  ])
                            //  ->rule('alpha')
                            //  ->validationMessages([
                            //      'alpha' => 'Solo se permiten letras en el apellido.',
                            //      'required' => 'El primer apellido es obligatorio.',
                            //  ]),

                            Forms\Components\TextInput::make('Segundo_apellido_cliente')
                                ->label('Segundo Apellido')
                                ->nullable(),
                            //  ->extraInputAttributes([
                            //      'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                            //      'onblur'  => "this.value = this.value.toUpperCase();",
                            //  ])
                            //  ->rule('alpha')
                            //  ->validationMessages([
                            //      'alpha' => 'Solo se permiten letras en el apellido.',
                            //  ]),
                        ]),

                // Sección para nombres y apellidos (relación)
                Forms\Components\Section::make('Contactos')
                ->relationship('ClientesContacto') // 👈 Indica la relación
                ->columns(3)
                ->schema([
                    DatePicker::make('fecha_expedicion')
                        ->label('Fecha de Expedición')
                        ->format('Y-m-d')
                        ->displayFormat('d/m/Y')
                        //->required()
                        ->maxDate(Carbon::yesterday())   // solo fechas anteriores a hoy
                        ->rule('before:today'),          // valida también en servidor
                    Forms\Components\TextInput::make('correo')
                        ->label('Correo electrónico')
                        ->required(),
                        
                 TextInput::make('tel')
                    ->label('Teléfono')
                    ->required()
                    ->maxLength(14) // visible: 10 dígitos + 2 espacios; margen a 14
                    ->live(debounce: 500)

                    // (opcional) guardar solo dígitos al deshidratar
                    ->dehydrateStateUsing(fn ($state) => preg_replace('/\D+/', '', (string) $state))

                    // WRAPPER: ring naranja si el número existe en ≥ 1 dueño distinto
                    ->extraAttributes(function (TextInput $component, Get $get) {
                        $digits = preg_replace('/\D+/', '', (string) $get('tel'));
                        $ready  = strlen($digits) >= 7;

                        $currentId = self::resolveCurrentClientId($component, $get);
                        $duenos    = $ready ? self::countOwnersForPhone($digits, $currentId) : 0;
                        $many      = $duenos >= 1;

                        $base = 'group/tel rounded-lg';
                        return [
                            'class' => $many
                                ? $base . ' tel-dup !ring-2 !ring-orange-500 dark:!ring-orange-400 focus-within:!ring-orange-600 !ring-offset-0 focus-within:!ring-offset-0'
                                : $base,
                            'style' => $many ? '--tw-ring-color:#f97316;' : null,
                        ];
                    })

                    // INPUT: mismas clases/colores si hay duplicado (≥ 1 dueño distinto)
                    ->extraInputAttributes(function (TextInput $component, Get $get) {
                        $digits = preg_replace('/\D+/', '', (string) $get('tel'));
                        $ready  = strlen($digits) >= 7;

                        $currentId = self::resolveCurrentClientId($component, $get);
                        $duenos    = $ready ? self::countOwnersForPhone($digits, $currentId) : 0;
                        $many      = $duenos >= 1;

                        $classes = [
                            'rounded-lg',
                            'border !border-gray-300 dark:!border-gray-700',
                            'transition-colors',
                        ];

                        if ($many) {
                            $classes = array_merge($classes, [
                                '!bg-orange-100',
                                '!text-orange-900',
                                'placeholder:!text-orange-700',
                                'dark:!bg-orange-900/60',
                                'dark:!text-orange-100',
                                'dark:placeholder:!text-orange-300',
                                '!border-2',
                                '!border-orange-500',
                                'dark:!border-orange-400',
                                '!shadow-sm',
                            ]);
                        }

                        return [
                            // Entrada: solo dígitos (máx 10). Blur: "XXX XXX XXXX"
                            'oninput' => "this.value=this.value.replace(/\\D/g,'').slice(0,10);",
                            'onblur'  => "let d=this.value.replace(/\\D/g,'').slice(0,10); let f=d; if(d.length>=7){f=d.substr(0,3)+' '+d.substr(3,3)+' '+d.substr(6);} this.value=f;",
                            'pattern' => '[0-9 ]+',
                            'aria-invalid' => $many ? 'true' : 'false',
                            'title' => $many ? 'Teléfono repetido en otro registro' : null,
                            'class' => implode(' ', $classes),
                        ];
                    })

                    // Validación de formato visible (JS ya limita a 10 dígitos)
                    ->rule('regex:/^[\\d ]{0,14}$/')

                    // Hint: solo si está duplicado (≥ 1 dueño distinto)
                    ->hint(function (Get $get, TextInput $component) {
                        $digits = preg_replace('/\\D+/', '', (string) $get('tel'));
                        if ($digits === '' || strlen($digits) < 7) return null;

                        $currentId = self::resolveCurrentClientId($component, $get);
                        $duenos    = self::countOwnersForPhone($digits, $currentId);

                        return $duenos >= 1 ? 'Valor repetido' : null;
                    })
                    ->hintIcon(null)
                    ->hintColor('warning')
                    ->validationMessages([
                        'regex'      => 'Solo se permiten números y espacios.',
                        'max_length' => 'Máximo 10 dígitos permitidos.',
                    ]),




            
                    TextInput::make('tel_alternativo')
                        ->label('Teléfono Alterno')
                        ->required()
                        ->maxLength(14)              // 10 dígitos + 2 espacios visibles; margen a 14
                        ->live(debounce: 500)        // un poco más largo para reducir renders

                        // WRAPPER: ring naranja si el número existe en ≥ 1 OTRO dueño
                        ->extraAttributes(function (TextInput $component, Get $get) {
                            $digits = preg_replace('/\D+/', '', (string) $get('tel_alternativo'));
                            $ready  = strlen($digits) >= 7;

                            $currentId = self::resolveCurrentClientId($component, $get);          // 👈 helper
                            $owners    = $ready ? self::countOwnersForPhone($digits, $currentId)  // 👈 helper (memoiza)
                                                : 0;
                            $dup = $owners >= 1;

                            $base = 'group/telalt rounded-lg';
                            return [
                                'class' => $dup
                                    ? $base.' telalt-dup !ring-2 !ring-orange-500 dark:!ring-orange-400 focus-within:!ring-orange-600 !ring-offset-0 focus-within:!ring-offset-0'
                                    : $base,
                                'style' => $dup ? '--tw-ring-color:#f97316;' : null,
                            ];
                        })

                        // INPUT: mismas clases/colores si hay duplicado (≥ 1 otro dueño)
                        ->extraInputAttributes(function (TextInput $component, Get $get) {
                            $digits = preg_replace('/\D+/', '', (string) $get('tel_alternativo'));
                            $ready  = strlen($digits) >= 7;

                            $currentId = self::resolveCurrentClientId($component, $get);
                            $owners    = $ready ? self::countOwnersForPhone($digits, $currentId) : 0;
                            $dup       = $owners >= 1;

                            $classes = [
                                'rounded-lg',
                                'border !border-gray-300 dark:!border-gray-700',
                                'transition-colors',
                            ];

                            if ($dup) {
                                $classes = array_merge($classes, [
                                    '!bg-orange-100',
                                    '!text-orange-900',
                                    'placeholder:!text-orange-700',
                                    'dark:!bg-orange-900/60',
                                    'dark:!text-orange-100',
                                    'dark:placeholder:!text-orange-300',
                                    '!border-2',
                                    '!border-orange-500',
                                    'dark:!border-orange-400',
                                    '!shadow-sm',
                                ]);
                            }

                            return [
                                // Entrada: solo dígitos (máx 10). Blur: “XXX XXX XXXX”
                                'oninput' => "this.value=this.value.replace(/\\D/g,'').slice(0,10);",
                                'onblur'  => "let d=this.value.replace(/\\D/g,'').slice(0,10); let f=d; if(d.length>=7){f=d.substr(0,3)+' '+d.substr(3,3)+' '+d.substr(6);} this.value=f;",
                                'pattern' => '[0-9 ]+',
                                'aria-invalid' => $dup ? 'true' : 'false',
                                'title' => $dup ? 'Teléfono alterno repetido en otro registro' : null,
                                'class' => implode(' ', $classes),
                            ];
                        })

                        // Validación de formato visible (el JS ya limita a 10 dígitos)
                        ->rule('regex:/^[\\d ]{0,14}$/')

                        // HINT: solo si está duplicado (≥ 1 otro dueño)
                        ->hint(function (Get $get, TextInput $component) {
                            $digits = preg_replace('/\\D+/', '', (string) $get('tel_alternativo'));
                            if ($digits === '' || strlen($digits) < 7) return null;

                            $currentId = self::resolveCurrentClientId($component, $get);
                            $owners    = self::countOwnersForPhone($digits, $currentId);

                            return $owners >= 1 ? 'Valor repetido' : null;
                        })
                        ->hintIcon(null)
                        ->hintColor('warning')
                        ->validationMessages([
                            'regex'      => 'Solo se permiten números y espacios.',
                            'max_length' => 'Máximo 10 dígitos permitidos.',
                        ]),





                    Forms\Components\TextInput::make('direccion')
                        ->label('Dirección')
                        ->required(),

                    Select::make('residencia_id_departamento')
                        ->label('Departamento Residencia') 
                        ->required()
                        ->options(
                            // Obtener todas las marcas de z_marca
                            ZDepartamentos::all()->pluck('name_departamento', 'id')
                        )
                        ->searchable()
                        ->preload(),

                    Select::make('residencia_id_municipio')
                        ->label('Municipio Residencia') 
                        ->required()
                        ->options(
                            // Obtener todas las marcas de z_marca
                            ZMunicipios::all()->pluck('name_municipio', 'id')
                        )
                        ->searchable()
                        ->preload(),

            
                        
                    
                ]),

                // Sección para dispositivos comprados (relación)
                Forms\Components\Section::make('Dispositivos Comprados')
                ->schema([
                    Repeater::make('dispositivosComprados') // 👈 Nombre de la relación hasMany
                        ->relationship()
                        ->schema([
                            Select::make('id_marca')
                                ->label('Marca del Dispositivo')
                                ->required()
                                ->options(
                                    // Obtener todas las marcas de z_marca
                                    ZMarca::all()->pluck('name_marca', 'idmarca')
                                )
                                ->searchable()
                                ->preload()
                                ->hidden(function ($record) {
                                        $cliente = $record instanceof \App\Models\Cliente
                                            ? $record
                                            : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));
                                        $tipo = (int)($cliente?->ID_Tipo_credito ?? 0);
                                        return $tipo !== 1;
                                    })
                                    ->dehydrated(function ($record) {
                                        $cliente = $record instanceof \App\Models\Cliente
                                            ? $record
                                            : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));
                                        $tipo = (int)($cliente?->ID_Tipo_credito ?? 0);
                                        return $tipo === 1;
                                    }),


                            Select::make('idmodelo')
                                ->label('Modelo del Dispositivo')
                                ->required()
                                ->options(
                                    // Obtener todos los modelos
                                    ZModelo::all()->pluck('name_modelo', 'idmodelo')
                                )
                                ->searchable()
                                ->preload()
                                ->hidden(function ($record) {
                                        $cliente = $record instanceof \App\Models\Cliente
                                            ? $record
                                            : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));
                                        $tipo = (int)($cliente?->ID_Tipo_credito ?? 0);
                                        return $tipo !== 1;
                                    })
                                    ->dehydrated(function ($record) {
                                        $cliente = $record instanceof \App\Models\Cliente
                                            ? $record
                                            : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));
                                        $tipo = (int)($cliente?->ID_Tipo_credito ?? 0);
                                        return $tipo === 1;
                                    }),

                            Forms\Components\TextInput::make('producto_convenio')
                                    ->label('Producto')
                                    ->required()
                                    ->hidden(function ($record) {
                                        // Resolver el Cliente dueño del form (aun si estamos dentro de otra relación)
                                        $cliente = $record instanceof \App\Models\Cliente
                                            ? $record
                                            : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));

                                        $tipo = (int)($cliente?->ID_Tipo_credito ?? 0);
                                        // Ocultar si NO es convenio (2) ni agente (3)
                                        return !in_array($tipo, [2, 3], true); // true = oculto
                                    })
                                    ->dehydrated(function ($record) {
                                        $cliente = $record instanceof \App\Models\Cliente
                                            ? $record
                                            : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));

                                        $tipo = (int)($cliente?->ID_Tipo_credito ?? 0);
                                        // Solo enviar/guardar cuando sea convenio (2) o agente (3)
                                        return in_array($tipo, [2, 3], true);
                                    }),

                                

                            Select::make('idgarantia')
                                ->label('Garantía')
                                ->required()
                                ->options(
                                    // Obtener todos los modelos
                                    ZGarantia::all()->pluck('garantia', 'idgarantia')
                                )
                                ->searchable()
                                ->preload(),


            

                                
                                TextInput::make('imei')
                                    ->label('IMEI')
                                    ->required()
                                    ->maxLength(15)
                                    // Solo dispara el ciclo cuando el input pierde el foco, no en cada tecla
                                    ->live(onBlur: true)

                                    // WRAPPER: ring naranja si hay >= 2 coincidencias
                                    ->extraAttributes(function (TextInput $component, Get $get) {
                                        $base  = 'group/imei rounded-lg';
                                        $count = (int) $get('imei_dup_count');

                                        if ($count > 1) {
                                            return [
                                                'class' => $base . ' imei-dup !ring-2 !ring-orange-500 dark:!ring-orange-400 focus-within:!ring-orange-600 !ring-offset-0 focus-within:!ring-offset-0',
                                                'style' => '--tw-ring-color:#f97316;',
                                            ];
                                        }

                                        return ['class' => $base];
                                    })

                                    // INPUT: fondo/borde naranjas si hay >= 2
                                    ->extraInputAttributes(function (Get $get) {
                                        $count    = (int) $get('imei_dup_count');
                                        $dup2plus = $count > 1;

                                        $classes = [
                                            'rounded-lg',
                                            'border !border-gray-300 dark:!border-gray-700',
                                            'transition-colors',
                                        ];

                                        if ($dup2plus) {
                                            $classes = array_merge($classes, [
                                                // Claro
                                                '!bg-orange-100',
                                                '!text-orange-900',
                                                'placeholder:!text-orange-700',
                                                // Oscuro
                                                'dark:!bg-orange-900/60',
                                                'dark:!text-orange-100',
                                                'dark:placeholder:!text-orange-300',
                                                // Borde visible
                                                '!border-2',
                                                '!border-orange-500',
                                                'focus:!border-orange-500',
                                                'dark:!border-orange-400',
                                                'dark:focus:!border-orange-400',
                                                // Sombra
                                                '!shadow-sm',
                                            ]);
                                        }

                                        return [
                                            'oninput' => "this.value = this.value.replace(/[^A-Za-z0-9]/g,'').slice(0,15);",
                                            'onblur'  => "this.value = this.value.toUpperCase();",
                                            'pattern' => '[A-Za-z0-9]+',
                                            'aria-invalid' => $dup2plus ? 'true' : 'false',
                                            'title'        => $dup2plus ? 'IMEI repetido múltiples veces' : null,
                                            'class'        => implode(' ', $classes),
                                        ];
                                    })

                                    // Hint solo si hay >= 2
                                    ->hint(function (Get $get) {
                                        $count = (int) $get('imei_dup_count');
                                        return $count > 1
                                            ? "Este IMEI aparece {$count} veces en el sistema."
                                            : null;
                                    })
                                    ->hintIcon(null)
                                    ->hintColor('warning') // naranja

                                    // Regla de formato
                                    ->rule('regex:/^[A-Za-z0-9]{0,15}$/')
                                    ->validationMessages([
                                        'regex'      => 'Solo se permiten caracteres alfanuméricos (A–Z, 0–9).',
                                        'max_length' => 'Máximo 15 caracteres permitidos para IMEI.',
                                    ])

                                    // Aquí SÍ se consulta la base, pero solo cuando cambia el valor (y con blur)
                                    ->afterStateUpdated(function (
                                        ?string $state,
                                        \Filament\Forms\Set $set,
                                        \Filament\Forms\Get $get,
                                        TextInput $component
                                    ) {
                                        $raw  = (string) $state;
                                        $imei = strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $raw));

                                        if ($imei === '') {
                                            $set('imei_dup_count', 0);
                                            return;
                                        }

                                        $livewire   = $component->getContainer()->getLivewire();
                                        $record     = data_get($livewire, 'record');
                                        $excludeCol = null;
                                        $excludeVal = null;

                                        if (is_object($record)) {
                                            if (isset($record->id_dispositivo)) {
                                                $excludeCol = 'id_dispositivo';
                                                $excludeVal = $record->id_dispositivo;
                                            } elseif (isset($record->id)) {
                                                $excludeCol = 'id';
                                                $excludeVal = $record->id;
                                            }
                                        }

                                        $count = DB::table('dispositivos_comprados')
                                            ->when($excludeCol, fn ($q) => $q->where($excludeCol, '!=', $excludeVal))
                                            ->whereRaw("LOWER(REPLACE(REPLACE(imei, ' ', ''), '-', '')) = ?", [$imei])
                                            ->count();

                                        // Guardamos el count en el estado para reutilizarlo
                                        $set('imei_dup_count', $count);

                                        if ($count > 1) {
                                            Notification::make()
                                                ->title('IMEI repetido múltiples veces')
                                                ->body('El IMEI ' . strtoupper($imei) . " aparece {$count} veces en el sistema.")
                                                ->warning()
                                                ->send();
                                        }
                                    })


                                
                                ])
                                    ->columns(3)
                                    ->disableItemCreation() // 👈 Opcional: Bloquear nuevos elementos
                                    ->disableItemDeletion() ,// 👈 Opcional: Bloquear eliminación
                                ]),


                // Sección para dispositivos comprados (relación)
                Forms\Components\Section::make('Informacón de pago')
                ->schema([
                    Repeater::make('dispositivosPago') // 👈 Nombre de la relación hasMany
                        ->relationship()
                        ->schema([
                            DatePicker::make('fecha_pc')
                                ->label('Fecha Primer Cuota')
                                ->required()
                                ->displayFormat('d/m/Y')
                                ->native(false)
                                ->format('Y-m-d'),
                                //->minDate(now()),
                            
                            Select::make('idpago')
                                ->label('Periodo de Pago')
                                ->required()
                                ->options(
                                    // Obtener todas las marcas de z_marca
                                    ZPago::all()->pluck('periodo_pago', 'idpago')
                                )
                                ->searchable()
                                ->preload(),

                            
                
                           /*Forms\Components\TextInput::make('cuota_inicial')
                                ->label('Cuota Inicial')
                                ->required()
                                ->extraInputAttributes([
                                    // Formatea con punto cada 3 dígitos en oninput y onblur
                                    'oninput' => "let v=this.value.replace(/\D/g,''); let parts=[]; while(v.length>3){ parts.unshift(v.slice(-3)); v=v.slice(0,-3);} if(v) parts.unshift(v); this.value=parts.join('.');",
                                    'onblur'  => "let v=this.value.replace(/\D/g,''); let parts=[]; while(v.length>3){ parts.unshift(v.slice(-3)); v=v.slice(0,-3);} if(v) parts.unshift(v); this.value=parts.join('.');",
                                ])
                                ->rule('numeric') // Valida que solo haya números
                                ->validationMessages([
                                    'numeric' => 'Debe ser un número válido.',
                                ]),*/


                            Select::make('num_cuotas')
                                ->label('Número de Cuotas')
                                ->required()
                                ->options(Zcuotas::all()->pluck('num_cuotas', 'idcuotas'))
                                ->searchable(),
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

                                                                            

                            //Select::make('idcuotas')
                                //->label('Número de Cuotas')
                                //->required()
                                // ->options(Zcuotas::all()->pluck('num_cuotas', 'idcuotas'))
                                //->searchable(),
                                                        
                                                    
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
                                                            
                            
                                
                        ])
                        ->columns(3)
                        ->disableItemCreation() // 👈 Opcional: Bloquear nuevos elementos
                        ->disableItemDeletion() ,// 👈 Opcional: Bloquear eliminación
                ]),

                // Sección para dispositivos comprados (relación)
                Forms\Components\Section::make('Referencias Personales')
                    ->hidden(function ($record) {
                        $cliente = $record instanceof \App\Models\Cliente
                            ? $record
                            : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));
                        return (int)($cliente?->ID_Tipo_credito ?? 0) === 2;
                    })

                ->schema([
                    Repeater::make('referenciasPersonales1') // 👈 Nombre de la relación hasMany
                        ->relationship()
                        ->schema([
                            Forms\Components\TextInput::make('Primer_Nombre_rf1')
                            ->required(),
                            /* ->extraInputAttributes([
                                'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                                'onblur'  => "this.value = this.value.toUpperCase();",
                            ])
                            ->rule('alpha') // Solo letras
                            ->validationMessages([
                                'alpha' => 'Solo se permiten letras en el nombre.',
                            ]),*/
                            Forms\Components\TextInput::make('Segundo_Nombre_rf1'),

                            /*->extraInputAttributes([
                                'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                                'onblur'  => "this.value = this.value.toUpperCase();",
                            ])
                            ->rule('alpha') // Solo letras
                            ->validationMessages([
                                'alpha' => 'Solo se permiten letras en el nombre.',
                            ]),*/

                            Forms\Components\TextInput::make('Primer_Apellido_rf1')
                            ->required(),
                            /*->extraInputAttributes([
                                'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                                'onblur'  => "this.value = this.value.toUpperCase();",
                            ])
                            ->rule('alpha') // Solo letras
                            ->validationMessages([
                                'alpha' => 'Solo se permiten letras en el nombre.',
                            ]),*/
                            Forms\Components\TextInput::make('Segundo_Apellido_rf1'),

                            /*->extraInputAttributes([
                                'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                                'onblur'  => "this.value = this.value.toUpperCase();",
                            ])
                            ->rule('alpha') // Solo letras
                            ->validationMessages([
                                'alpha' => 'Solo se permiten letras en el nombre.',
                            ]),*/

                            TextInput::make('Celular_rf1')
                                ->label('Celular ref. 1')
                                ->maxLength(14)
                                ->live(debounce: 500) // un poco más largo para reducir renders
                                ->extraAttributes(function (TextInput $component, Get $get) {
                                    $digits = preg_replace('/\D+/', '', (string) $get('Celular_rf1'));
                                    $ready  = strlen($digits) >= 7;

                                    $currentId = self::resolveCurrentClientId($component, $get);
                                    $duenos    = $ready ? self::countOwnersForPhone($digits, $currentId) : 0;
                                    $many      = $duenos >= 1;

                                    $base = 'group/celrf1 rounded-lg';
                                    return [
                                        'class' => $many
                                            ? $base.' celrf1-dup !ring-2 !ring-orange-500 dark:!ring-orange-400 focus-within:!ring-orange-600 !ring-offset-0 focus-within:!ring-offset-0'
                                            : $base,
                                        'style' => $many ? '--tw-ring-color:#f97316;' : null,
                                    ];
                                })
                                ->extraInputAttributes(function (TextInput $component, Get $get) {
                                    $digits = preg_replace('/\D+/', '', (string) $get('Celular_rf1'));
                                    $ready  = strlen($digits) >= 7;

                                    $currentId = self::resolveCurrentClientId($component, $get);
                                    $duenos    = $ready ? self::countOwnersForPhone($digits, $currentId) : 0;
                                    $many      = $duenos >= 1;

                                    $classes = [
                                        'rounded-lg',
                                        'border !border-gray-300 dark:!border-gray-700',
                                        'transition-colors',
                                    ];
                                    if ($many) {
                                        $classes = array_merge($classes, [
                                            '!bg-orange-100','!text-orange-900','placeholder:!text-orange-700',
                                            'dark:!bg-orange-900/60','dark:!text-orange-100','dark:placeholder:!text-orange-300',
                                            '!border-2','!border-orange-500','dark:!border-orange-400','!shadow-sm',
                                        ]);
                                    }

                                    return [
                                        'oninput' => "this.value=this.value.replace(/\\D/g,'').slice(0,10);",
                                        'onblur'  => "let d=this.value.replace(/\\D/g,'').slice(0,10); let f=d; if(d.length>=7){f=d.substr(0,3)+' '+d.substr(3,3)+' '+d.substr(6);} this.value=f;",
                                        'pattern' => '[0-9 ]+',
                                        'aria-invalid' => $many ? 'true' : 'false',
                                        'title' => $many ? 'Celular ref. 1 repetido en otro registro' : null,
                                        'class' => implode(' ', $classes),
                                    ];
                                })
                                ->rule('regex:/^[\\d ]{0,14}$/')
                                ->hint(function (Get $get, TextInput $component) {
                                    $digits = preg_replace('/\\D+/', '', (string) $get('Celular_rf1'));
                                    if ($digits === '' || strlen($digits) < 7) return null;

                                    $currentId = self::resolveCurrentClientId($component, $get);
                                    $duenos    = self::countOwnersForPhone($digits, $currentId);

                                    return $duenos >= 1 ? 'Valor repetido' : null;
                                })
                                ->hintIcon(null)
                                ->hintColor('warning')
                                ->validationMessages([
                                    'regex'      => 'Solo se permiten números y espacios.',
                                    'max_length' => 'Máximo 10 dígitos permitidos.',
                                ]),



                            Select::make('Parentesco_rf1')
                                ->label('parentesco')
                                //->required()
                                ->options(
                                    // Obtener todas las marcas de z_marca
                                    ZParentescoPersonal::all()->pluck('parentesco', 'idparentesco')
                                ),
                            Select::make('idtiempo_conocerlonum')
                                ->label('Tiempo de Conocerlo')
                                //->required()
                                ->options(
                                    // Obtener todas las marcas de z_marca
                                    ZTiempoConocerloNum::all()->pluck('numeros', 'idtiempo_conocerlonum')
                                ),
                            Select::make('idtiempoconocerlo')
                                ->label('Tiempo de Conocerlo')
                                //->required()
                                ->options(
                                    // Obtener todas las marcas de z_marca
                                    ZTiempoConocerlo::all()->pluck('tiempo', 'idtiempoconocerlo')
                                )

                        ])
                        ->columns(3)
                        ->disableItemCreation() // 👈 Opcional: Bloquear nuevos elementos
                        ->disableItemDeletion(), // 👈 Opcional: Bloquear eliminación
        


                        Repeater::make('referenciasPersonales2') // 👈 Nombre de la relación hasMany
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('Primer_Nombre_rf2')
                                    ->required(),
                                    /*->extraInputAttributes([
                                        'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                                        'onblur'  => "this.value = this.value.toUpperCase();",
                                    ])
                                    ->rule('alpha') // Solo letras
                                    ->validationMessages([
                                        'alpha' => 'Solo se permiten letras en el nombre.',
                                    ]),*/
                                Forms\Components\TextInput::make('Segundo_Nombre_rf2'),
                                    /*->extraInputAttributes([
                                        'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                                        'onblur'  => "this.value = this.value.toUpperCase();",
                                    ])
                                    ->rule('alpha') // Solo letras
                                    ->validationMessages([
                                        'alpha' => 'Solo se permiten letras en el nombre.',
                                    ]),*/
                                Forms\Components\TextInput::make('Primer_Apellido_rf2')
                                    ->required(),
                                    /*->extraInputAttributes([
                                        'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                                        'onblur'  => "this.value = this.value.toUpperCase();",
                                    ])
                                    ->rule('alpha') // Solo letras
                                    ->validationMessages([
                                        'alpha' => 'Solo se permiten letras en el nombre.',
                                    ]),*/
                                Forms\Components\TextInput::make('Segundo_Apellido_rf2'),
                                    /*->extraInputAttributes([
                                        'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                                        'onblur'  => "this.value = this.value.toUpperCase();",
                                    ])
                                    ->rule('alpha') // Solo letras
                                    ->validationMessages([
                                        'alpha' => 'Solo se permiten letras en el nombre.',
                                    ]),*/
            

                                TextInput::make('Celular_rf2')
                                    ->label('Celular ref. 2')
                                    ->maxLength(14)             // visible: "XXX XXX XXXX" → 12; margen a 14
                                    ->live(debounce: 500)       // menos renders
                                    // (opcional) guardar solo dígitos
                                    ->dehydrateStateUsing(fn ($state) => preg_replace('/\D+/', '', (string) $state))

                                    // WRAPPER: pinta si hay ≥ 1 OTRO dueño con el mismo número
                                    ->extraAttributes(function (TextInput $component, Get $get) {
                                        $digits = preg_replace('/\D+/', '', (string) $get('Celular_rf2'));
                                        $ready  = strlen($digits) >= 7;

                                        $currentId = self::resolveCurrentClientId($component, $get);
                                        $owners    = $ready ? self::countOwnersForPhone($digits, $currentId) : 0;
                                        $dup       = $owners >= 1;

                                        $base = 'group/rf2 rounded-lg';
                                        return [
                                            'class' => $dup
                                                ? $base . ' rf2-dup !ring-2 !ring-orange-500 dark:!ring-orange-400 focus-within:!ring-orange-600 !ring-offset-0 focus-within:!ring-offset-0'
                                                : $base,
                                            'style' => $dup ? '--tw-ring-color:#f97316;' : null,
                                        ];
                                    })

                                    // INPUT: mismos estilos naranjas si hay ≥ 1 otro dueño
                                    ->extraInputAttributes(function (TextInput $component, Get $get) {
                                        $digits = preg_replace('/\D+/', '', (string) $get('Celular_rf2'));
                                        $ready  = strlen($digits) >= 7;

                                        $currentId = self::resolveCurrentClientId($component, $get);
                                        $owners    = $ready ? self::countOwnersForPhone($digits, $currentId) : 0;
                                        $dup       = $owners >= 1;

                                        $classes = [
                                            'rounded-lg',
                                            'border !border-gray-300 dark:!border-gray-700',
                                            'transition-colors',
                                        ];

                                        if ($dup) {
                                            $classes = array_merge($classes, [
                                                '!bg-orange-100',
                                                '!text-orange-900',
                                                'placeholder:!text-orange-700',
                                                'dark:!bg-orange-900/60',
                                                'dark:!text-orange-100',
                                                'dark:placeholder:!text-orange-300',
                                                '!border-2',
                                                '!border-orange-500',
                                                'dark:!border-orange-400',
                                                '!shadow-sm',
                                            ]);
                                        }

                                        return [
                                            // Entrada: solo dígitos (máx 10). Blur: "XXX XXX XXXX"
                                            'oninput' => "this.value=this.value.replace(/\\D/g,'').slice(0,10);",
                                            'onblur'  => "let d=this.value.replace(/\\D/g,'').slice(0,10); let f=d; if(d.length>=7){f=d.substr(0,3)+' '+d.substr(3,3)+' '+d.substr(6);} this.value=f;",
                                            'pattern' => '[0-9 ]+',
                                            'aria-invalid' => $dup ? 'true' : 'false',
                                            'title' => $dup ? 'Celular repetido en otro dueño' : null,
                                            'class' => implode(' ', $classes),
                                        ];
                                    })

                                    // Validación de formato visible
                                    ->rule('regex:/^[\\d ]{0,14}$/')
                                    ->validationMessages([
                                        'regex'      => 'Solo se permiten números y espacios.',
                                        'max_length' => 'Máximo 10 dígitos permitidos.',
                                    ])

                                    // HINT: muestra mensaje si hay ≥ 1 otro dueño
                                    ->hint(function (Get $get, TextInput $component) {
                                        $digits = preg_replace('/\\D+/', '', (string) $get('Celular_rf2'));
                                        if ($digits === '' || strlen($digits) < 7) return null;

                                        $currentId = self::resolveCurrentClientId($component, $get);
                                        $owners    = self::countOwnersForPhone($digits, $currentId);

                                        return $owners >= 1 ? 'Valor repetido' : null;
                                    })
                                    ->hintIcon(null)
                                    ->hintColor('warning'),








                                Select::make('Parentesco_rf2')
                                    ->label('parentesco')
                                    //->required()
                                    ->options(
                                        // Obtener todas las marcas de z_marca
                                        ZParentescoPersonal::all()->pluck('parentesco', 'idparentesco')
                                    ),
                                Select::make('idtiempo_conocerlonum')
                                    ->label('Tiempo de Conocerlo')
                                    //->required()
                                    ->options(
                                        // Obtener todas las marcas de z_marca
                                        ZTiempoConocerloNum::all()->pluck('numeros', 'idtiempo_conocerlonum')
                                    ),
                                Select::make('idtiempoconocerlo')
                                    ->label('Tiempo de Conocerlo')
                                    //->required()
                                    ->options(
                                        // Obtener todas las marcas de z_marca
                                        ZTiempoConocerlo::all()->pluck('tiempo', 'idtiempoconocerlo')
                                    )

                                
                            ])
                            ->columns(3)
                            ->disableItemCreation() // 👈 Opcional: Bloquear nuevos elementos
                            ->disableItemDeletion(), // 👈 Opcional: Bloquear eliminación
                ]),


                // Sección para dispositivos comprados (relación)
                Forms\Components\Section::make('Detalles cliente')
                    ->schema([
                        Repeater::make('detallesCliente') // 👈 Nombre de la relación hasMany
                            ->relationship()
                            ->schema([
                                Select::make('idplataforma')
                                ->label('Plataforma')
                                ->placeholder('Seleccione la plataforma')
                                ->required()
                                ->options(function ($record): array {
                                    // Resuelve el cliente desde el record actual (edición) o su relación
                                    $cliente = $record instanceof \App\Models\Cliente
                                        ? $record
                                        : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));

                                    $tipo = (int) ($cliente?->ID_Tipo_credito ?? 0);

                                    // Mapas de plataformas permitidas por tipo de crédito
                                    $allowMap = [
                                        1 => [
                                            'PAYJOY','CELYA','CREDIMINUTO','KREDIYA','BRILLA',
                                            'ALOCREDIT','ALCANOS','METROGAS','GASES DEL ORIENTE','VANTI',
                                        ],
                                        2 => [
                                            'CELYA','BANCO DE BOGOTA','TEAVALO','SISTECREDITO','ADDI',
                                            'SUFI','BRILLA','ALCANOS','METROGAS','GASES DEL ORIENTE','VANTI',
                                        ],
                                    ];

                                    $allowed = $allowMap[$tipo] ?? null;

                                    $q = ZPlataformaCredito::query()->orderBy('plataforma');

                                    if (is_array($allowed)) {
                                        $q->whereIn('plataforma', $allowed);
                                    }
                                    // Si $allowed es null (tipo distinto a 1/2), no se filtra y se muestran todas

                                    return $q->pluck('plataforma', 'idplataforma')->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->hidden(function ($record): bool {
                                    $cliente = $record instanceof \App\Models\Cliente
                                        ? $record
                                        : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));
                                    return (int) ($cliente?->ID_Tipo_credito ?? 0) === 3;
                                })
                                ->dehydrated(function ($record): bool {
                                    $cliente = $record instanceof \App\Models\Cliente
                                        ? $record
                                        : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));
                                    return (int) ($cliente?->ID_Tipo_credito ?? 0) !== 3;
                                }),


                                Select::make('idcomision')
                                ->label('Comisión')
                                ->required()
                                ->options(
                                    // Obtener todas las marcas de z_marca
                                    ZComision::all()->pluck('comision', 'id')
                                )
                                ->searchable()
                                ->preload()
                                ->hidden(function ($record) {
                                            $cliente = $record instanceof \App\Models\Cliente
                                                ? $record
                                                : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));
                                            return (int)($cliente?->ID_Tipo_credito ?? 0) === 3;
                                        })
                                        ->dehydrated(function ($record) {
                                            $cliente = $record instanceof \App\Models\Cliente
                                                ? $record
                                                : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));
                                            return (int)($cliente?->ID_Tipo_credito ?? 0) !== 3;
                                        }),


                                Select::make('codigo_asesor')
                                ->label('Código Asesor')
                                ->options(
                                    InfTrab::pluck('Codigo_vendedor', 'Codigo_vendedor')
                                )
                                ->searchable()
                                ->reactive()
                                //->required()
                                ->visible(fn () => ! auth()->user()?->hasAnyRole('asesor_agente'))
                                ->disabled()    
                                    ->dehydrated() 
                                ->hidden(function ($record) {
                                        $cliente = $record instanceof \App\Models\Cliente
                                            ? $record
                                            : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));
                                        return (int)($cliente?->ID_Tipo_credito ?? 0) === 3;
                                    })
                                    ->dehydrated(function ($record) {
                                        $cliente = $record instanceof \App\Models\Cliente
                                            ? $record
                                            : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));
                                        return (int)($cliente?->ID_Tipo_credito ?? 0) !== 3;
                                    })

                                ->default(function ($record) {
                                    return $record?->codigo_asesor; // Carga el valor almacenado
                                })
                                ->afterStateHydrated(function ($state, callable $set, $record) {
                                    // Carga inicial de datos si hay un código almacenado
                                    if ($state && $record) {
                                        $infTrab = InfTrab::where('Codigo_vendedor', $state)
                                            ->with('aaPrin.asesor', 'aaPrin.sede') // Carga eager
                                            ->first();
                    
                                        if ($infTrab && $infTrab->aaPrin) {
                                            $set('ID_Asesor', $infTrab->aaPrin->ID_Asesor);
                                            $set('idsede', $infTrab->aaPrin->ID_Sede);
                                            $set('nombre_asesor', $infTrab->aaPrin->asesor->Nombre ?? '');
                                            $set('nombre_sede', $infTrab->aaPrin->sede->Name_Sede ?? '');
                                        }
                                    }
                                })
                                ->afterStateUpdated(function ($state, callable $set) {
                                    // Actualiza campos al cambiar el código
                                    $infTrab = InfTrab::where('Codigo_vendedor', $state)
                                        ->with('aaPrin.asesor', 'aaPrin.sede')
                                        ->first();
                    
                                    if ($infTrab && $infTrab->aaPrin) {
                                        $set('ID_Asesor', $infTrab->aaPrin->ID_Asesor);
                                        $set('idsede', $infTrab->aaPrin->ID_Sede);
                                        $set('nombre_asesor', $infTrab->aaPrin->asesor->Nombre ?? 'N/A');
                                        $set('nombre_sede', $infTrab->aaPrin->sede->Name_Sede ?? 'N/A');
                                    } else {
                                        // Limpiar campos si no hay datos
                                        $set('ID_Asesor', null);
                                        $set('idsede', null);
                                        $set('nombre_asesor', '');
                                        $set('nombre_sede', '');
                                    }
            }),

            // Campos ocultos para guardar IDs
            Forms\Components\TextInput::make('ID_Asesor')
                ->hidden()
                ->dehydrated(), // Asegura que se guarde en la BD
    
                Forms\Components\TextInput::make('idsede')
                ->hidden()
                ->dehydrated(),
    
            // Campos de solo lectura para mostrar datos
            Forms\Components\TextInput::make('nombre_asesor')
                ->label('Nombre Asesor')
                ->disabled()
                ->dehydrated(false), // No se guarda en la BD
    
                Forms\Components\TextInput::make('nombre_sede')
                ->label('Sede')
                ->disabled()
                ->dehydrated(false),

                Select::make('ID_PDV_agente')
                ->label('Punto de venta agente')
                //->required()
                ->options(
                    // Obtener todas las marcas de z_marca
                    ZPdvAgenteDistritec::all()->pluck('PDV_agente', 'ID_PDV_agente')
                )
                ->searchable()
                ->required()
                ->preload()
                ->hidden(function ($record) {
                            $cliente = $record instanceof \App\Models\Cliente
                                ? $record
                                : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));
                            $tipo = (int)($cliente?->ID_Tipo_credito ?? 0);
                            return $tipo !== 3;
                        })
                        ->dehydrated(function ($record) {
                            $cliente = $record instanceof \App\Models\Cliente
                                ? $record
                                : (method_exists($record, 'cliente') ? $record->cliente : \App\Models\Cliente::find($record->id_cliente ?? null));
                            $tipo = (int)($cliente?->ID_Tipo_credito ?? 0);
                            return $tipo === 3;
                        }),

                        Section::make('Detalles Agentes')
                         ->schema([
                          // Campos de solo lectura para mostrar datos
                         //  select::make('ID_PDV_agente')
                         //      ->label('Punto de venta')
                         //      ->disabled()
                         //       ->options(
                         //              // Obtener todas las marcas de z_marca
                         //              Agentes::all()->pluck('nombre_pv', 'id_tercero')
                         //          )
                         //      ->dehydrated(false), // No se guarda en la BD

                         //  select::make('ID_PDV_agente')
                         //      ->label('Nombre agente')
                         //      ->disabled()
                         //       ->options(
                         //              // Obtener todas las marcas de z_marca
                         //              Agentes::all()->pluck('nombre_tercero', 'id_tercero')
                         //          )
                         //      ->dehydrated(false), // No se guarda en la BD

                         //  select::make('idsede')
                         //      ->label('sede agente')
                         //      ->disabled()
                         //       ->options(
                         //              // Obtener todas las marcas de z_marca
                         //              Sede::all()->pluck('Name_Sede', 'ID_Sede')
                         //          )
                         //      ->dehydrated(false), // No se guarda en la BD


                            Placeholder::make('ID_PDV_agente_pv')
                                ->label('Punto de venta')
                                ->content(fn ($record) => Agentes::find($record?->ID_PDV_agente)?->nombre_pv ?? '-')
                                ->hint('Solo lectura')
                                ->extraAttributes([
                                    'class' => 'bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm'
                                ]),

                            Placeholder::make('ID_PDV_agente_nombre')
                                ->label('Nombre agente')
                                ->content(fn ($record) => Agentes::find($record?->ID_PDV_agente)?->nombre_tercero ?? '-')
                                ->hint('Solo lectura')
                                ->extraAttributes([
                                    'class' => 'bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm'
                                ]),

                            Placeholder::make('ID_PDV_agente_pv')
                                ->label('Sede agente')
                                ->content(function ($record) {
                                    // Buscar el agente usando el ID guardado en el registro
                                    $agent = Agentes::find($record?->ID_PDV_agente);

                                    // Si no existe agente, devolver '-'
                                    if (! $agent) {
                                        return '-';
                                    }

                                    // Buscar la sede usando el id_sede del agente y devolver su nombre
                                    return Sede::find($agent->id_sede)?->Name_Sede ?? '-';
                                })
                                ->hint('Solo lectura')
                                ->extraAttributes([
                                    'class' => 'bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm'
                                ]),

                                 ])
                                 ->columns(3), 


                        ])
                        ->columns(3)
                        ->disableItemCreation() // 👈 Opcional: Bloquear nuevos elementos
                        ->disableItemDeletion() ,// 👈 Opcional: Bloquear eliminación
                                ]),  
                
                
                Section::make('Estado del Crédito')
                        ->relationship('gestion')
                        // Oculta toda la sección si el usuario es "asesor comercial" o "asesor_agente"
                        ->visible(fn () => ! auth()->user()?->hasAnyRole(['asesor comercial', 'asesor_agente']))
                        ->schema([
                        //Select::make('ID_Estado_cr')
                        //    ->label('Estado del Crédito')
                        //    ->relationship('estadoCredito', 'Estado_Credito')
                        //    ->searchable()
                        //    ->preload()
                        //    // Si en algún momento decides mostrarla, que no pueda tocarla
                        //    ->disabled(fn () => auth()->user()?->hasAnyRole(['asesor comercial', 'asesor_agente']))
                        //    // Seguridad: no enviar cambios si es "asesor comercial" o "asesor_agente"
                        //    ->dehydrated(fn () => ! auth()->user()?->hasAnyRole(['asesor comercial', 'asesor_agente'])),


                                \Filament\Forms\Components\Textarea::make('comentarios')
                                ->label('Comentarios')
                                ->rows(4)
                                ->maxLength(65535)
                                ->columnSpanFull()
                                // Misma lógica de seguridad
                                ->disabled(fn () => auth()->user()?->hasAnyRole(['asesor comercial', 'asesor_agente']))
                                ->dehydrated(fn () => ! auth()->user()?->hasAnyRole(['asesor comercial', 'asesor_agente'])),
                        ]),

                    ]);       

                        
    }
    
    /** Cache simple por request (evita recalcular por fila varias veces) */
    protected static array $__dupCache = [];
    protected static array $__recompraCache = [];

    /** Normaliza: solo dígitos (teléfonos) */
    protected static function sqlDigitsOnly(string $col): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                    REPLACE({$col}, ' ', ''), '-', ''), '.', ''), '/', ''), '\\\\', ''), '_', ''), '(', ''), ')', ''), '+', ''), ',', '')";
    }

    /** Normaliza: A–Z/0–9 (IMEI) */
    protected static function sqlAlnumOnly(string $col): string
    {
        return "LOWER(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$col}, ' ', ''), '-', ''), '.', ''), '/', ''), '\\\\', ''), '_', ''), ',', '')
        )";
    }

    /** Normaliza: lower + trim (emails, cédula si la tratas como string) */
    protected static function sqlLowerTrim(string $col): string
    {
        return "LOWER(TRIM({$col}))";
    }

    /** Fuentes para DUPLICADOS *sin* incluir cédula (tel/correo/imei) */
    protected static function duplicateSourcesNoCedula(): array
    {
        return [
            // TELÉFONOS
            ['clientes_contacto',    'tel',              ['id_cliente','ID_Cliente'], 'digits'],
            ['clientes_contacto',    'tel_alternativo',  ['id_cliente','ID_Cliente'], 'digits'],
            ['referencia_personal1', 'Celular_rf1',      ['ID_Cliente'],              'digits'],
            ['referencia_personal2', 'Celular_rf2',      ['ID_Cliente'],              'digits'],

            // EMAILS
            ['clientes_contacto',    'correo',           ['id_cliente','ID_Cliente'], 'lower'],

            // IMEI
            ['dispositivos_comprados','imei',            ['id_cliente','ID_Cliente'], 'alnum'],
        ];
    }

    /** Recompra real: detalles_cliente.tiene_recompra=1 y cédula en otro cliente */
    protected static function isRecompraPorCedula(Cliente $record): bool
    {
        $id  = (int) $record->id_cliente;

        if (isset(static::$__recompraCache[$id])) {
            return static::$__recompraCache[$id];
        }

        $ced = (string) ($record->cedula ?? '');
        if ($ced === '') {
            return static::$__recompraCache[$id] = false;
        }

        $tieneFlag = DB::table('detalles_cliente')
            ->where('id_cliente', $id)
            ->where('tiene_recompra', 1)
            ->exists();

        if (! $tieneFlag) {
            return static::$__recompraCache[$id] = false;
        }

        $existsOtro = DB::table('clientes')
            ->where('cedula', $ced)
            ->where('id_cliente', '!=', $id)
            ->limit(1)
            ->exists();

        return static::$__recompraCache[$id] = $existsOtro;
    }

    /** Obtiene valores normalizados del cliente (DISTINCT) para una fuente */
    protected static function clientNormalizedValues(
        string $table, string $col, array $fkCols, int $id, string $normType, int $limit = 200
    ): array {
        $normCol = match ($normType) {
            'digits' => static::sqlDigitsOnly($col),
            'alnum'  => static::sqlAlnumOnly($col),
            default  => static::sqlLowerTrim($col),
        };

        $vals = DB::table($table)
            ->selectRaw("DISTINCT {$normCol} AS v")
            ->where(function ($q) use ($fkCols, $id) {
                foreach ((array) $fkCols as $fk) {
                    $q->orWhere($fk, $id);
                }
            })
            ->whereNotNull($col)
            ->limit($limit)
            ->pluck('v')
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->values()
            ->all();

        return $vals;
    }

    /** ¿Existe en otros registros (≠ id) alguno de esos valores normalizados? */
    protected static function existsInOthers(
        string $table, string $col, array $fkCols, string $normType, array $values, int $id
    ): bool {
        if (empty($values)) return false;

        $normCol = match ($normType) {
            'digits' => static::sqlDigitsOnly($col),
            'alnum'  => static::sqlAlnumOnly($col),
            default  => static::sqlLowerTrim($col),
        };

        // Consultamos por bloques para no crear IN gigantes (y cortamos al primer match)
        foreach (array_chunk($values, 50) as $chunk) {
            $exists = DB::table($table)
                ->whereIn(DB::raw($normCol), $chunk)
                ->where(function ($q) use ($fkCols, $id) {
                    foreach ((array) $fkCols as $fk) {
                        $q->orWhere($fk, '!=', $id);
                    }
                })
                ->whereNotNull($col)
                ->limit(1)
                ->exists();

            if ($exists) return true;
        }

        return false;
    }

    /** Desglose de duplicados (sin cédula) por categoría — con cache por request */
    protected static function duplicateSignalsNoCedula(Cliente $record): array
    {
        $id = (int) $record->id_cliente;
        if (isset(static::$__dupCache[$id])) {
            return static::$__dupCache[$id];
        }

        $sig  = [];
        $srcs = static::duplicateSourcesNoCedula();

        $labels = [
            'clientes_contacto.tel'              => 'Teléfono(s)',
            'clientes_contacto.tel_alternativo'  => 'Teléfono(s)',
            'referencia_personal1.Celular_rf1'   => 'Teléfono(s)',
            'referencia_personal2.Celular_rf2'   => 'Teléfono(s)',
            'clientes_contacto.correo'           => 'Correo(s)',
            'dispositivos_comprados.imei'        => 'IMEI(s)',
        ];

        foreach ($srcs as [$table, $col, $fkCols, $normType]) {
            // 1) valores del cliente (normalizados)
            $vals = static::clientNormalizedValues($table, $col, $fkCols, $id, $normType);

            if (empty($vals)) continue;

            // 2) ¿existe en otros?
            if (static::existsInOthers($table, $col, $fkCols, $normType, $vals, $id)) {
                $key = $labels["{$table}.{$col}"] ?? "{$table}.{$col}";
                // sumamos 1 por categoría (presencia), no nº de clientes
                $sig[$key] = ($sig[$key] ?? 0) + 1;
            }

            // Tip: si solo te interesa "≥2 categorías", puedes cortar aquí:
            // if (array_sum($sig) >= 2) break;
        }

        return static::$__dupCache[$id] = $sig;
    }

    /** ¿El total de categorías duplicadas (tel/correo/imei) es ≥ 2? */
    protected static function hasDupes2PlusNoCedula(Cliente $record): bool
    {
        return array_sum(static::duplicateSignalsNoCedula($record)) >= 2;
    }

    // Cache por request para meta de duplicados
    protected static array $__dupMetaCache = [];

    /**
     * Calcula y cachea una sola vez por fila:
     * - has2: bool => hay ≥ 2 categorías duplicadas (tel/correo/imei)
     * - tooltip: string|null => texto ya formateado para el tooltip
     */
    protected static function dupMeta(object $record): array
    {
        $id = (int) $record->id_cliente;

        if (isset(static::$__dupMetaCache[$id])) {
            return static::$__dupMetaCache[$id];
        }

        // Tu lógica existente (re-usa tus helpers actuales)
        $sig   = static::duplicateSignalsNoCedula($record);
        $has2  = array_sum($sig) >= 2;

        $tooltip = null;
        if (!empty($sig)) {
            $lines = [];
            foreach ($sig as $k => $v) {
                $lines[] = "{$k}: {$v}";
            }
            $tooltip = "Datos repetidos\n" . implode("\n", $lines);
        }

        return static::$__dupMetaCache[$id] = [
            'has2'    => $has2,
            'tooltip' => $tooltip,
        ];
    }


public static function table(Table $table): Table
{
    //\Illuminate\Support\Facades\Log::info('Filament ClienteResource Table Debug - Method table() entered.');

    // Helpers para compactar celdas/encabezados
    $compactCell = fn () => ['class' => 'py-1 px-2 text-[11px] whitespace-nowrap'];
    $compactHead = fn () => ['class' => 'py-1 px-2 text-[10px] uppercase tracking-wide'];

    return $table
        ->defaultSort('id_cliente', 'desc')
        ->recordClasses(fn () => 'text-xs')
        ->searchDebounce(350)
        ->defaultPaginationPageOption(5)
        ->paginationPageOptions([5, 8, 12])

        ->columns([
            // 1) Icono RECOMPRA
            \Filament\Tables\Columns\IconColumn::make('recompra')
                ->label('')
                ->getStateUsing(fn ($record) => static::isRecompraPorCedula($record))
                ->icon(fn (bool $state) => $state ? 'heroicon-m-arrow-path' : null)
                ->color(fn (bool $state) => $state ? 'success' : null)
                ->tooltip(fn (bool $state) => $state ? 'Recompra: cédula ya registrada y validada' : null)
                ->alignCenter()
                ->extraCellAttributes(function () use ($compactCell) {
                    $a = $compactCell();
                    $a['class'] .= ' text-center';
                    return $a;
                })
                ->extraHeaderAttributes(function () use ($compactHead) {
                    $a = $compactHead();
                    $a['class'] .= ' text-center';
                    return $a;
                })
                ->sortable(false),

            // 2) ID Cliente
            Tables\Columns\TextColumn::make('id_cliente')
                ->label('ID Cliente')
                ->searchable()
                ->sortable(),

            // 3) Fecha
            Tables\Columns\TextColumn::make('created_at')
                ->label('Fecha')
                ->formatStateUsing(function ($state) {
                    if (! $state) {
                        return new \Illuminate\Support\HtmlString('—');
                    }

                    $dt = $state instanceof \Carbon\CarbonInterface
                        ? $state->copy()
                        : \Carbon\Carbon::parse($state);

                    $dt = $dt->timezone(config('app.timezone'));

                    return new \Illuminate\Support\HtmlString(
                        e($dt->format('d/m/Y')) . '<br>' . e($dt->format('H:i'))
                    );
                })
                ->html()
                ->extraCellAttributes(function () use ($compactCell) {
                    $a = $compactCell();
                    $a['class'] .= ' text-center';
                    return $a;
                })
                ->extraHeaderAttributes(function () use ($compactHead) {
                    $a = $compactHead();
                    $a['class'] .= ' text-center';
                    return $a;
                })
                ->alignCenter()
                ->sortable()
                ->searchable(false),

            // 4) Nombre (dos líneas) — usando la relación clientesNombreCompleto (sin DB::table)
            Tables\Columns\TextColumn::make('nombre')
                ->label('Nombre')
                ->getStateUsing(function ($record) {
                    $row = $record->clientesNombreCompleto; // relación eager loaded

                    if (! $row) {
                        return new \Illuminate\Support\HtmlString('N/A');
                    }

                    $nombres = trim(
                        collect([
                            $row->Primer_nombre_cliente ?? null,
                            $row->Segundo_nombre_cliente ?? null,
                        ])->filter()->implode(' ')
                    );

                    $apellidos = trim(
                        collect([
                            $row->Primer_apellido_cliente ?? null,
                            $row->Segundo_apellido_cliente ?? null,
                        ])->filter()->implode(' ')
                    );

                    return new \Illuminate\Support\HtmlString(
                        e($nombres) . '<br>' . e($apellidos)
                    );
                })
                ->html()
                ->tooltip(fn ($state) => trim(preg_replace('/\s+/', ' ', strip_tags((string) $state))))
                ->extraCellAttributes(function () use ($compactCell) {
                    $a = $compactCell();
                    $a['class'] .= ' text-center';
                    return $a;
                })
                ->extraHeaderAttributes(function () use ($compactHead) {
                    $a = $compactHead();
                    $a['class'] .= ' text-center';
                    return $a;
                })
                ->alignCenter()
                ->toggleable()
                ->sortable(false)
                ->searchable(false),

            // 5) Tipo Doc. — usando relación tipoDocumento (sin ::find por fila)
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

            // 6) Cédula
            Tables\Columns\TextColumn::make('cedula')
                ->label('Cédula')
                ->extraCellAttributes(function () use ($compactCell) {
                    $a = $compactCell();
                    $a['class'] .= ' text-center';
                    return $a;
                })
                ->extraHeaderAttributes(function () use ($compactHead) {
                    $a = $compactHead();
                    $a['class'] .= ' text-center';
                    return $a;
                })
                ->alignCenter()
                ->searchable(),

            // 7) Tipo de Crédito
            Tables\Columns\TextColumn::make('tipoCredito.Tipo')
                ->label('Tipo de Crédito')
                ->extraCellAttributes(function () use ($compactCell) {
                    $a = $compactCell();
                    $a['class'] .= ' text-center';
                    return $a;
                })
                ->extraHeaderAttributes(function () use ($compactHead) {
                    $a = $compactHead();
                    $a['class'] .= ' text-center';
                    return $a;
                })
                ->alignCenter()
                ->sortable()
                ->searchable(),

            // 8) Sede
            Tables\Columns\TextColumn::make('detallesCliente.sede.Name_Sede')
                ->label('Sede')
                ->extraCellAttributes(function () use ($compactCell) {
                    $a = $compactCell();
                    $a['class'] .= ' text-center';
                    return $a;
                })
                ->extraHeaderAttributes(function () use ($compactHead) {
                    $a = $compactHead();
                    $a['class'] .= ' text-center';
                    return $a;
                })
                ->alignCenter(),

            // 9) Nombre Auxiliar
            Tables\Columns\SelectColumn::make('gestion.ID_Gestor')
                ->label('Nombre Auxiliar')
                ->options(
                    GestorDistritec::where('estado', 'ACTIVO')
                        ->pluck('Nombre_gestor', 'ID_Gestor')
                        ->toArray()
                )
                ->getStateUsing(fn ($record) => $record->gestion?->ID_Gestor)
                ->updateStateUsing(function ($record, $state) {
                    $record->gestion()->updateOrCreate(
                        ['id_cliente' => $record->id_cliente],
                        ['ID_Gestor'  => $state],
                    );

                    $record->refresh();
                    event(new ClienteUpdated(
        $record->only([
            'id_cliente',
            'Nombre_Cliente',
            'Cedula',
            // lo que necesite el frontend
        ])
    ));

                    return $state;
                })
                ->disabled(fn ($record, $state) =>
                    auth()->user()?->hasRole('administrativo') || filled($state)
                )
                ->searchable()
                ->extraCellAttributes(function () use ($compactCell) {
                    $a = $compactCell();
                    $a['class'] .= ' fi-ta-select fi-col-select text-center';

                     // AQUÍ desactivamos el loader global para este select
                    $a['x-data'] = '{}';
                    $a['x-on:change.capture'] = "
                        if (window.AppLoader) {
                            window.AppLoader.suppress(8000); 
                        }
                        var el = document.getElementById('app-global-loader');
                        if (el) el.classList.remove('is-visible');
                    ";

                    return $a;
                })
                ->extraHeaderAttributes(function () use ($compactHead) {
                    $a = $compactHead();
                    $a['class'] .= ' fi-col-select text-center';
                    return $a;
                })
                ->alignCenter()
                ->sortable(),

                   Tables\Columns\SelectColumn::make('gestion.ID_Estado_cr')
    ->label('Estado')
    ->options(fn () =>
        ZEstadocredito::pluck('Estado_Credito', 'ID_Estado_cr')->toArray()
    )
    ->getStateUsing(fn ($record) => $record->gestion?->ID_Estado_cr)
    ->updateStateUsing(function ($record, $state) {
        $now = now();

        Log::debug('🟡 [SelectColumn Estado] INICIO updateStateUsing', [
            'cliente_id' => $record->id_cliente ?? null,
            'nuevo_estado_id' => $state,
        ]);

        $gestion = $record->gestion()->first();

        $attrs = [
            'ID_Estado_cr'         => $state,
            'Estado_cr_updated_at' => $now,
        ];

        if (! $gestion || is_null($gestion->Estado_cr_created_at)) {
            $attrs['Estado_cr_created_at'] = $now;
        }

        // Guardamos/actualizamos la gestión
        $record->gestion()->updateOrCreate(
            ['id_cliente' => $record->id_cliente],
            $attrs,
        );

        Log::debug('🟡 [SelectColumn Estado] Gestion guardada/updateOrCreate', [
            'cliente_id' => $record->id_cliente ?? null,
            'attrs'      => $attrs,
        ]);

        // Cargamos SOLO la relación necesaria para obtener el texto
        $record->load([
            'gestion.estadoCredito' => function ($q) {
                $q->select('ID_Estado_cr', 'Estado_Credito');
            },
        ]);

        $texto = optional($record->gestion?->estadoCredito)->Estado_Credito;

        Log::debug('🟡 [SelectColumn Estado] EstadoCredito cargado', [
            'cliente_id'   => $record->id_cliente ?? null,
            'estado_id'    => $state,
            'estado_texto' => $texto,
        ]);

        // 🔴 Aquí antes lanzabas ClienteUpdated($record) => lo quitamos
        //    Tampoco hacemos $record->refresh() para no reventar nada.

        try {
            event(new EstadoCreditoUpdated(
                clienteId: (int) $record->id_cliente,
                estadoId: $state ? (int) $state : null,
                estadoTexto: $texto,
                userId: Auth::id(),
            ));

            Log::debug('✅ [SelectColumn Estado] Evento EstadoCreditoUpdated disparado', [
                'cliente_id'   => $record->id_cliente ?? null,
                'estado_id'    => $state,
                'estado_texto' => $texto,
                'user_id'      => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ [SelectColumn Estado] Error al disparar EstadoCreditoUpdated', [
                'cliente_id' => $record->id_cliente ?? null,
                'estado_id'  => $state,
                'exception'  => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
        }

        Log::debug('🟡 [SelectColumn Estado] FIN updateStateUsing', [
            'cliente_id' => $record->id_cliente ?? null,
            'return'     => $state,
        ]);

        return $state;
    })
    ->disabled(function () {
        return auth()->user()?->hasRole('administrativo');
    })
    ->searchable()
    ->extraCellAttributes(function ($record, $state) use ($compactCell) {
        $a = $compactCell();
        $a['class'] = ($a['class'] ?? '') . ' fi-col-select fi-col-estado text-center';

        $a['data-cliente-id'] = $record->id_cliente;

        $a['x-data'] = '{}';
        $a['x-on:change.capture'] = "
            if (window.AppLoader) {
                window.AppLoader.suppress(10000); 
            }
            var el = document.getElementById('app-global-loader');
            if (el) el.classList.remove('is-visible');
        ";

        $texto = \Illuminate\Support\Str::lower(
            $record?->gestion?->estadoCredito?->Estado_Credito ?? ''
        );

        $styles = [
            'aprobado'    => 'color:#1E7D32; font-weight:600;',
            'no aprobado' => 'color:#B71C1C; font-weight:600;',
        ];

        if (isset($styles[$texto])) {
            $a['style'] = ($a['style'] ?? '') . $styles[$texto];
        }

        return $a;
    })
    ->extraHeaderAttributes(function () use ($compactHead) {
        $a = $compactHead();
        $a['class'] = ($a['class'] ?? '') . ' fi-col-select fi-col-estado text-center';
        return $a;
    })
    ->alignCenter()
    ->sortable(),


            // 11) Chat (oculto para rol administrativo)
            \Filament\Tables\Columns\IconColumn::make('chat')
                ->label('Chat')
                ->getStateUsing(fn (Cliente $record) => true)
                ->icon('heroicon-o-chat-bubble-left')
                ->color('primary')
                ->tooltip('Abrir chat')
                ->alignCenter()
                ->size(\Filament\Tables\Columns\IconColumn\IconColumnSize::Large)
                ->sortable(false)
                ->extraAttributes(fn (Cliente $record) => [
                    'class'          => '!text-primary-600 hover:!text-primary-700 cursor-pointer',
                    'style'          => 'min-width: 2.25rem;',
                    'data-client-id' => (int) $record->id_cliente,
                ])
                ->extraCellAttributes(function () use ($compactCell) {
                    $a = $compactCell();
                    $a['class'] .= ' col-icon-big text-center';
                    return $a;
                })
                ->extraHeaderAttributes(function () use ($compactHead) {
                    $a = $compactHead();
                    $a['class'] .= ' col-icon-big text-center';
                    return $a;
                })
                ->action(function (Cliente $record, $livewire) {
                    $chat = \App\Filament\Widgets\ChatWidget::class;

                    // Abre + carga + subscribe (sin doble evento)
                    $livewire->dispatch('setClientId', clientId: (int) $record->id_cliente)->to($chat);

                    // Opcional: asegura que quede “encima”
                    $livewire->dispatch('bringToFront')->to($chat);
                })
                ->visible(function () {
                    // Ocultar la columna para el rol "administrativo"
                    return ! auth()->user()?->hasRole('administrativo');
                }),
            // 12) Documentación
            \Filament\Tables\Columns\IconColumn::make('documentacion')
                ->label('Documentación')
                ->toggleable(isToggledHiddenByDefault: true)
                ->getStateUsing(fn (Cliente $record) => true)
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->tooltip('Ver/Editar Documentos')
                ->alignCenter()
                ->size(\Filament\Tables\Columns\IconColumn\IconColumnSize::Large)
                ->sortable(false)
                ->extraCellAttributes(function () use ($compactCell) {
                    $a = $compactCell();
                    $a['class'] .= ' col-icon-big text-center';
                    return $a;
                })
                ->extraHeaderAttributes(function () use ($compactHead) {
                    $a = $compactHead();
                    $a['class'] .= ' col-icon-big text-center';
                    return $a;
                })
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

            // 13) Comentarios
            Tables\Columns\BadgeColumn::make('gestion.comentarios')
                ->label('Comentarios')
                ->limit(30)
                ->tooltip(fn ($state) => strip_tags((string) $state))
                ->toggleable(isToggledHiddenByDefault: true)
                ->extraCellAttributes(function () use ($compactCell) {
                    $a = $compactCell();
                    $a['class'] .= ' col-comentarios';
                    return $a;
                })
                ->extraHeaderAttributes(function () use ($compactHead) {
                    $a = $compactHead();
                    $a['class'] .= ' col-comentarios';
                    return $a;
                })
                ->searchable()
                ->sortable()
                ->toggleable(),

            // ========= BUSCADOR INTEGRAL OCULTO =========
            Tables\Columns\TextColumn::make('buscar_todo')
                ->label('🔎 (oculto)')
                ->toggleable(false)
                ->visible(false)
                ->searchable(query: function (Builder $query, string $search): Builder {
                    $raw = trim((string) $search);
                    if ($raw === '' || mb_strlen($raw) < 2) {
                        return $query;
                    }

                    $tbl        = $query->getModel()->getTable();
                    $digits     = preg_replace('/\D+/', '', $raw);
                    $hasAt      = str_contains($raw, '@');
                    $onlyDigits = $digits !== '' && $digits === preg_replace('/\s+/', '', $raw);

                    return $query->where(function (Builder $q) use ($raw, $digits, $hasAt, $onlyDigits, $tbl) {

                        // 1) CÉDULA — prefijo normalizado (2+)
                        if ($digits && strlen($digits) >= 2) {
                            $q->orWhereRaw(
                                "REGEXP_REPLACE(COALESCE($tbl.cedula,''), '[^0-9]', '') LIKE ?",
                                [$digits . '%']
                            );
                        }

                        // 2 y 3) TELÉFONOS — prefijo (7+) con UNION ALL
                        if ($digits && strlen($digits) >= 7) {
                            $parts = [];

                            if (\Illuminate\Support\Facades\Schema::hasTable('clientes_contacto')) {
                                $parts[] = "
                                    SELECT cc.ID_Cliente AS ID_Cliente,
                                        REGEXP_REPLACE(COALESCE(cc.tel,''), '[^0-9]', '') AS num
                                    FROM clientes_contacto cc
                                ";
                                $parts[] = "
                                    SELECT cc.ID_Cliente AS ID_Cliente,
                                        REGEXP_REPLACE(COALESCE(cc.tel_alternativo,''), '[^0-9]', '') AS num
                                    FROM clientes_contacto cc
                                ";
                            }
                            if (\Illuminate\Support\Facades\Schema::hasTable('referencia_personal1')) {
                                $parts[] = "
                                    SELECT r1.ID_Cliente AS ID_Cliente,
                                        REGEXP_REPLACE(COALESCE(r1.Celular_rf1,''), '[^0-9]', '') AS num
                                    FROM referencia_personal1 r1
                                ";
                            }
                            if (\Illuminate\Support\Facades\Schema::hasTable('referencia_personal2')) {
                                $parts[] = "
                                    SELECT r2.ID_Cliente AS ID_Cliente,
                                        REGEXP_REPLACE(COALESCE(r2.Celular_rf2,''), '[^0-9]', '') AS num
                                    FROM referencia_personal2 r2
                                ";
                            }

                            if (! empty($parts)) {
                                $union = implode(" UNION ALL ", $parts);
                                $q->orWhereExists(function ($s) use ($tbl, $union, $digits) {
                                    $s->select(DB::raw(1))
                                        ->from(DB::raw("({$union}) AS phones"))
                                        ->whereColumn('phones.ID_Cliente', "$tbl.id_cliente")
                                        ->whereRaw('phones.num LIKE ?', [$digits . '%']);
                                });
                            }
                        }

                        // 4) IMEI — exacto (14–16) o prefijo (8–13)
                        if ($digits && strlen($digits) >= 8) {
                            $q->orWhereHas('dispositivoscomprados', function (Builder $w) use ($digits) {
                                if (strlen($digits) >= 14 && strlen($digits) <= 16) {
                                    $w->whereRaw(
                                        "REGEXP_REPLACE(COALESCE(imei,''), '[^0-9]', '') = ?",
                                        [$digits]
                                    );
                                } else {
                                    $w->whereRaw(
                                        "REGEXP_REPLACE(COALESCE(imei,''), '[^0-9]', '') LIKE ?",
                                        [$digits . '%']
                                    );
                                }
                            });
                        }

                        // 5) CORREO
                        if ($hasAt) {
                            $q->orWhereExists(function ($s) use ($raw, $tbl) {
                                $s->select(DB::raw(1))
                                    ->from('clientes_contacto as cc')
                                    ->whereColumn('cc.ID_Cliente', "$tbl.id_cliente")
                                    ->where(function ($ww) use ($raw) {
                                        $ww->where('cc.correo', $raw)
                                            ->orWhere('cc.correo', 'like', $raw . '%');
                                    });
                            });
                        } elseif (! $onlyDigits && mb_strlen($raw) >= 3) {
                            $q->orWhereExists(function ($s) use ($raw, $tbl) {
                                $s->select(DB::raw(1))
                                    ->from('clientes_contacto as cc')
                                    ->whereColumn('cc.ID_Cliente', "$tbl.id_cliente")
                                    ->where('cc.correo', 'like', $raw . '%');
                            });
                        }

                        // 6) NOMBRE
                        if (! $onlyDigits) {
                            $term = trim(preg_replace('/\s+/', ' ', $raw));
                            if ($term !== '' && mb_strlen($term) >= 2) {
                                $tokens = array_values(array_filter(preg_split('/\s+/', $term)));

                                $q->orWhereExists(function ($sub) use ($tokens, $tbl) {
                                    $sub->select(DB::raw(1))
                                        ->from('clientes_nombre_completo as cnc')
                                        ->whereColumn('cnc.ID_Cliente', "$tbl.id_cliente")
                                        ->when(count($tokens) === 1, function ($qq) use ($tokens) {
                                            $t = $tokens[0] . '%';
                                            $qq->where(function ($w) use ($t) {
                                                $w->where('cnc.Primer_nombre_cliente', 'like', $t)
                                                    ->orWhere('cnc.Primer_apellido_cliente', 'like', $t);
                                            });
                                        })
                                        ->when(count($tokens) >= 2, function ($qq) use ($tokens) {
                                            $t1 = $tokens[0] . '%';
                                            $t2 = $tokens[1] . '%';

                                            $qq->where(function ($w) use ($t1, $t2) {
                                                $w->where('cnc.Primer_nombre_cliente', 'like', $t1)
                                                    ->where('cnc.Primer_apellido_cliente', 'like', $t2);
                                            })->orWhere(function ($w) use ($t1, $t2) {
                                                $w->where('cnc.Primer_apellido_cliente', 'like', $t1)
                                                    ->where('cnc.Primer_nombre_cliente', 'like', $t2);
                                            });

                                            if (count($tokens) >= 3) {
                                                $full = implode(' ', $tokens);
                                                $qq->orWhereRaw(
                                                    "TRIM(CONCAT_WS(' ', COALESCE(cnc.Primer_nombre_cliente,''), COALESCE(cnc.Segundo_nombre_cliente,''), COALESCE(cnc.Primer_apellido_cliente,''), COALESCE(cnc.Segundo_apellido_cliente,''))) = ?",
                                                    [$full]
                                                );
                                            }
                                        });
                                });
                            }
                        }
                    });
                }),
        ])

        ->filters([
            // Teléfonos
            Tables\Filters\Filter::make('telefono')
                ->label('Teléfonos')
                ->form([
                    Forms\Components\TextInput::make('numero')
                        ->label('Telefonos')
                        ->placeholder('Mínimo 7 dígitos'),
                ])
                ->query(function (Builder $query, array $data) {
                    $num = preg_replace('/\D+/', '', (string) ($data['numero'] ?? ''));
                    if (strlen($num) < 7) {
                        return $query;
                    }

                    $tbl = $query->getModel()->getTable();

                    $parts = [];

                    if (\Illuminate\Support\Facades\Schema::hasTable('clientes_contacto')) {
                        $parts[] = "
                            SELECT cc.ID_Cliente AS ID_Cliente,
                                REGEXP_REPLACE(COALESCE(cc.tel,''), '[^0-9]', '') AS num
                            FROM clientes_contacto cc
                        ";
                        $parts[] = "
                            SELECT cc.ID_Cliente AS ID_Cliente,
                                REGEXP_REPLACE(COALESCE(cc.tel_alternativo,''), '[^0-9]', '') AS num
                            FROM clientes_contacto cc
                        ";
                    }
                    if (\Illuminate\Support\Facades\Schema::hasTable('referencia_personal1')) {
                        $parts[] = "
                            SELECT r1.ID_Cliente AS ID_Cliente,
                                REGEXP_REPLACE(COALESCE(r1.Celular_rf1,''), '[^0-9]', '') AS num
                            FROM referencia_personal1 r1
                        ";
                    }
                    if (\Illuminate\Support\Facades\Schema::hasTable('referencia_personal2')) {
                        $parts[] = "
                            SELECT r2.ID_Cliente AS ID_Cliente,
                                REGEXP_REPLACE(COALESCE(r2.Celular_rf2,''), '[^0-9]', '') AS num
                            FROM referencia_personal2 r2
                        ";
                    }

                    if (empty($parts)) {
                        return $query;
                    }

                    $union = implode(" UNION ALL ", $parts);

                    return $query->whereExists(function ($s) use ($tbl, $union, $num) {
                        $s->select(DB::raw(1))
                            ->from(DB::raw("({$union}) AS phones"))
                            ->whereColumn('phones.ID_Cliente', "{$tbl}.id_cliente")
                            ->whereRaw('phones.num LIKE ?', [$num . '%']);
                    });
                }),

            // IMEI exacto
            Tables\Filters\Filter::make('exactos')
                ->form([
                    Forms\Components\TextInput::make('imei_exacto')->label('IMEI exacto'),
                ])
                ->query(function (Builder $query, array $data) {
                    $tbl = $query->getModel()->getTable();

                    if (! empty($data['imei_exacto'])) {
                        $imei = preg_replace('/\D+/', '', $data['imei_exacto']);
                        if ($imei !== '') {
                            $query->whereHas('dispositivoscomprados', fn ($w) =>
                                $w->whereRaw(
                                    "REGEXP_REPLACE(COALESCE(imei,''), '[^0-9]', '') = ?",
                                    [$imei]
                                )
                            );
                        }
                    }

                    return $query;
                }),

            // Filtro por fecha
            Tables\Filters\Filter::make('fecha')
                ->form([
                    Forms\Components\DatePicker::make('fecha'),
                ])
                ->query(function (Builder $query, array $data) {
                    if (! empty($data['fecha'])) {
                        $query->whereDate('created_at', $data['fecha']);
                    }
                    return $query;
                }),

            // Selects por relación
            Tables\Filters\SelectFilter::make('gestor')
                ->relationship('gestion.gestorDistritec', 'Nombre_gestor')
                ->searchable()
                ->preload(),

            Tables\Filters\Filter::make('nombre_completo')
                ->label('Nombre Completo')
                ->form([
                    Forms\Components\TextInput::make('nombre_completo')
                        ->label('Nombre Completo')
                        ->placeholder('Ingrese el nombre completo'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if (! empty($data['nombre_completo'])) {
                        $nombreCompleto = trim($data['nombre_completo']);
                        $tokens = preg_split('/\s+/', $nombreCompleto, -1, PREG_SPLIT_NO_EMPTY);

                        return $query->whereHas('clientesNombreCompleto', function ($q) use ($tokens) {
                            foreach ($tokens as $token) {
                                $q->where(function ($subQuery) use ($token) {
                                    $subQuery->where('Primer_nombre_cliente', 'like', '%' . $token . '%')
                                        ->orWhere('Segundo_nombre_cliente', 'like', '%' . $token . '%')
                                        ->orWhere('Primer_apellido_cliente', 'like', '%' . $token . '%')
                                        ->orWhere('Segundo_apellido_cliente', 'like', '%' . $token . '%');
                                });
                            }
                        });
                    }

                    return $query;
                }),

            Tables\Filters\SelectFilter::make('estado')
                ->relationship('gestion.estadoCredito', 'Estado_Credito')
                ->searchable()
                ->preload(),
        ])

        ->actions([
            Tables\Actions\ViewAction::make()
                ->label('Ver')
                ->icon('heroicon-m-eye')
                ->color('primary')
                ->button(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])

        ->headerActions([])

        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ])

        // Mantiene la búsqueda al cambiar de página
        ->persistSearchInSession();
}

public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with([
            'token',
            'gestion.gestorDistritec',
            'gestion.estadoCredito',
            'clientesNombreCompleto',
            'ClientesContacto',
            'tipoDocumento',              // 👈 para columna Tipo Doc.
            'detallesCliente.sede',       // 👈 para columna Sede
            'dispositivoscomprados',      // 👈 para filtros/busquedas por IMEI
        ]);
}

    

    public static function getPages(): array
    {
        return [
            // Mantener la página de índice (listado) para poder seleccionar un cliente a editar
            'index' => Pages\ListClientes::route('/'),

            // Mantener la página de edición
            'edit' => Pages\EditCliente::route('/{record}/edit'),
        ];
    }

    protected static function mutateFormDataBeforeFill(array $data): array
    {
        // Recuperar el cliente con la relación cargada para asegurar disponibilidad de la data
        // Usamos find($data['id']) si 'id' es la clave primaria en el array $data que Filament pasa
        // Si tu clave primaria es 'id_cliente' en $data, usa $data['id_cliente']
        // Basado en logs anteriores donde usaste $data['id_cliente'], usaré esa clave.
        $cliente = \App\Models\Cliente::where('id_cliente', $data['id_cliente'])->with('clientesNombreCompleto')->first();

        if ($cliente && $cliente->clientesNombreCompleto) {
            // Si la relación está cargada y tiene data, fusionar sus atributos en el array principal
            // Esto fuerza a que la data esté disponible en $data['clientesNombreCompleto']
            $data['clientesNombreCompleto'] = $cliente->clientesNombreCompleto->toArray();
        } else {
             // Si no hay data en la relación, inicializar con valores vacíos para evitar errores
              $data['clientesNombreCompleto'] = [
                 'Primer_nombre_cliente' => null,
                 'Segundo_nombre_cliente' => null,
                 'Primer_apellido_cliente' => null,
                 'Segundo_apellido_cliente' => null,
              ];
        }

        // Puedes añadir un log aquí si quieres verificar el contenido final de $data antes de que llene el formulario
        // \Illuminate\Support\Facades\Log::info('Final data before form fill', $data);

        return $data;
    }

    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        // Implementa la lógica para mutar los datos antes de crear un nuevo registro
        return $data;
    }
}