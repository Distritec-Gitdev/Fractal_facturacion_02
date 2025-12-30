<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SociosGestionResource\Pages;
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

use App\Models\ZEstadocredito;
use Illuminate\Support\Str;

use App\Events\EstadoCreditoUpdated;
use App\Events\GestorUpdated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;




use Closure;



class SociosGestionResource extends Resource
{

    /**
 * Devuelve el listado de IDs de sede permitidos para el usuario según su rol.
 */
/**
 * Retorna los IDs de sedes permitidos para el usuario dado.
 */
protected static function allowedSedeIdsFor(?\App\Models\User $u): array
{
    if (! $u) return [];

    $super = config('filament-shield.super_admin.name', 'super_admin');

    // Admin / super_admin: sin filtro
    if ($u->hasAnyRole([$super])) {
        return []; // no filtrar
    }

    // Rol SOCIO: sedes del socio por cédula
    if ($u->hasRole('socio')) {
        $cedula = $u->cedula ?? null;
        if (! $cedula) {
            return ['__none__'];
        }

        $socio = \App\Models\SocioDistritec::query()
            ->where('Cedula', $cedula)
            ->select(['ID_Socio'])
            ->first();

        if (! $socio) {
            return ['__none__'];
        }

        $ids = \App\Models\Sede::query()
            ->where('ID_Socio', $socio->ID_Socio)
            ->pluck('ID_Sede')
            ->all();

        return $ids ?: ['__none__'];
    }

    // Rol GESTOR CARTERA: usa ID_Socio = 17 y trae todas sus sedes
    if ($u->hasRole('gestor cartera')) {
        $idSocioCartera = 17; // <-- fijo, como pediste
        $ids = \App\Models\Sede::query()
            ->where('ID_Socio', $idSocioCartera)
            ->pluck('ID_Sede')
            ->all();

        return $ids ?: ['__none__'];
    }

    // Rol AUXILIAR_SOCIO: obtener el socio a partir de la cédula (asesor -> aa_prin -> sede -> socio)
    // y luego listar todas las sedes de ese socio (igual que en "socio")
    if ($u->hasRole('auxiliar_socio')) {
        $cedula = $u->cedula ?? null;
        if (! $cedula) {
            return ['__none__'];
        }

        // 1) asesores: por cédula -> ID_Asesor
        $idAsesor = \App\Models\Asesor::query()
            ->where('Cedula', $cedula)
            ->value('ID_Asesor');

        if (! $idAsesor) {
            return ['__none__'];
        }

        // 2) aa_prin: por ID_Asesor -> ID_Sede (toma el registro más reciente del asesor)
        $idSede = \App\Models\AaPrin::query()
            ->where('ID_Asesor', $idAsesor)
            ->latest('ID_Inf_trab')
            ->value('ID_Sede');

        if (! $idSede) {
            return ['__none__'];
        }

        // 3) sedes: por ID_Sede -> ID_Socio
        $idSocio = \App\Models\Sede::query()
            ->where('ID_Sede', $idSede)
            ->value('ID_Socio');

        if (! $idSocio) {
            return ['__none__'];
        }

        // 4) mismas sedes que un SOCIO (todas las sedes del ID_Socio)
        $ids = \App\Models\Sede::query()
            ->where('ID_Socio', $idSocio)
            ->pluck('ID_Sede')
            ->all();

        return $ids ?: ['__none__'];
    }

    // Otros roles: no ver nada
    return ['__none__'];
}


    protected static ?string $model = Cliente::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Gestión Clientes';
    protected static ?string $modelLabel = 'Socios Gestión';
    protected static ?string $pluralModelLabel = 'Socios Gestión';

   // IMPORTANTE: define el slug que usarán los permisos de Shield
protected static ?string $slug = 'socios_gestion';

/** Helper para componer los nombres de permiso de Shield */
protected static function perm(string $action): string
{
    // genera: view_any_socios_gestion, view_socios_gestion, create_socios_gestion, ...
    return "{$action}_" . (static::$slug ?? 'socios_gestion');
}

protected static function canAll(string $permission): bool
{
    $u = auth()->user();
    if (! $u) return false;

    $super = config('filament-shield.super_admin.name', 'super_admin');

    // Solo super_admin / admin saltan permisos explícitos
    if ($u->hasAnyRole([$super])) {
        return true;
    }

    // Bypass SOLO si llega por URL firmada "token"
    if (
        request()->hasValidSignature() &&
        request()->query('via') === 'token' &&
        (int) request()->query('uid') === (int) $u->id
    ) {
        return true;
    }

    // ✅ Usa EL permiso exacto
    return $u->can($permission);
}

// ✅ El menú aparece solo si REALMENTE tiene el permiso de listado
public static function shouldRegisterNavigation(): bool
{
   $u = auth()->user();
    $super = config('filament-shield.super_admin.name', 'super_admin');

    if ($u?->hasAnyRole([$super, 'admin', 'socio', 'auxiliar_socio'])) {
        return true;
    }

    // Permiso de listado de Shield
    return $u?->can('view_any_clientes') ?? false;
}

public static function canViewAny(): bool
{
    return static::canAll(static::perm('view_any'));
}

public static function canCreate(): bool
{
    return static::canAll(static::perm('create'));
}

public static function canView(Model $record): bool
{
    // vía token firmada, permitimos temporalmente
    if (static::canViaToken($record)) return true;

    return static::canAll(static::perm('view'));
}

public static function canEdit(Model $record): bool
{
    if (static::canViaToken($record)) return true;

    return static::canAll(static::perm('update'));
}

public static function canUpdate(Model $record): bool
{
    return static::canEdit($record);
}

public static function canDelete(Model $record): bool
{
    return static::canAll(static::perm('delete'));
}

public static function canDeleteAny(): bool
{
    // ✅ usar delete_any_*
    return static::canAll(static::perm('delete_any'));
}

protected static function canViaToken(Model $record): bool
{
    $u = auth()->user();
    if (! $u) return false;

    // 1) Primera entrada con URL firmada: ticket de 10 min
    if (
        request()->hasValidSignature() &&
        request()->query('via') === 'token' &&
        (int) request()->query('uid') === (int) $u->id
    ) {
        session()->put("allow_edit_client.{$record->getKey()}", now()->addMinutes(10)->timestamp);
        return true;
    }

    // 2) Subsecuentes: valida ticket
    $ts = session("allow_edit_client.{$record->getKey()}");
    return $ts && $ts >= now()->timestamp;
}


    public static function form(Form $form): Form
{
    return $form
        ->schema([
                Forms\Components\TextInput::make('cedula')
                ->label('Cédula')
                ->required()
                ->numeric()
                ->mask('9999999999') // Máscara visual
                ->maxLength(10) // Protección extra a nivel de atributo
                ->extraAttributes([
                    'class' => 'text-lg font-bold',
                    'oninput' => <<<JS
                        const raw = this.value;
                        const digitsOnly = raw.replace(/\\D/g, '');
                        if (digitsOnly.length > 10) {
                            let digitCount = 0;
                            let result = '';
                            for (let char of raw) {
                                if (/\\d/.test(char)) digitCount++;
                                if (digitCount > 10) break;
                                result += char;
                            }
                            this.value = result;
                        }
                    JS,
                ])
                ->rule('regex:/^[\d\s]+$/') // Valida solo números y espacios
                ->validationMessages([
                    'max_length' => 'Máximo 10 dígitos permitidos.',
                    'regex' => 'Solo se permiten números y espacios.',
                ]),

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
                     ->label('Departamento Expedicion Documento') 
                     ->required()
                     ->options(
                         // Obtener todas las marcas de z_marca
                         ZDepartamentos::all()->pluck('name_departamento', 'id')
                     )
                     ->searchable()
                     ->preload(),

                     Select::make('id_municipio')
                     ->label('Municipio Expedicion Documento') 
                     ->required()
                     ->options(
                         // Obtener todas las marcas de z_marca
                         ZMunicipios::all()->pluck('name_municipio', 'id')
                     )
                     ->searchable()
                     ->preload(),

            
            
            // Campo oculto para ID_Cliente_Nombre (si es necesario)
            Forms\Components\Hidden::make('ID_Cliente_Nombre'),
            
            // Sección para nombres y apellidos (Relación HasOne)
            Forms\Components\Section::make('Nombres y Apellidos')
                 ->relationship('clientesNombreCompleto') // Especificar explícitamente la relación HasOne
                 ->columns(4) // Usar 4 columnas como en la creación si es posible, o ajustar según diseño
                 ->schema([
                     Forms\Components\TextInput::make('Primer_nombre_cliente')
                         ->label('Primer Nombre')
                         ->required()
                         ->extraInputAttributes([
                             'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                             'onblur'  => "this.value = this.value.toUpperCase();",
                         ])
                         ->rule('alpha')
                         ->validationMessages([
                             'alpha' => 'Solo se permiten letras en el nombre.',
                             'required' => 'El primer nombre es obligatorio.',
                         ]),

                     Forms\Components\TextInput::make('Segundo_nombre_cliente')
                         ->label('Segundo Nombre')
                         ->nullable()
                         ->extraInputAttributes([
                             'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                             'onblur'  => "this.value = this.value.toUpperCase();",
                         ])
                         ->rule('alpha')
                         ->validationMessages([
                             'alpha' => 'Solo se permiten letras en el nombre.',
                         ]),

                     Forms\Components\TextInput::make('Primer_apellido_cliente')
                         ->label('Primer Apellido')
                         ->required()
                          ->extraInputAttributes([
                             'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                             'onblur'  => "this.value = this.value.toUpperCase();",
                         ])
                         ->rule('alpha')
                         ->validationMessages([
                             'alpha' => 'Solo se permiten letras en el apellido.',
                             'required' => 'El primer apellido es obligatorio.',
                         ]),

                     Forms\Components\TextInput::make('Segundo_apellido_cliente')
                         ->label('Segundo Apellido')
                         ->nullable()
                         ->extraInputAttributes([
                             'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                             'onblur'  => "this.value = this.value.toUpperCase();",
                         ])
                         ->rule('alpha')
                         ->validationMessages([
                             'alpha' => 'Solo se permiten letras en el apellido.',
                         ]),
                 ]),

             // Sección para nombres y apellidos (relación)
             Forms\Components\Section::make('Contactos')
             ->relationship('ClientesContacto') // 👈 Indica la relación
             ->columns(3)
             ->schema([
                 Forms\Components\TextInput::make('correo')
                     ->label('Correo electrónico')
                     ->required(),
                     
                 Forms\Components\TextInput::make('tel')
                 ->label('Teléfono')
                 ->required()
                 ->maxLength(14) // Hasta 10 dígitos + espacios
                 ->extraInputAttributes([
                     // Mientras escribe, limita a 10 dígitos sin espacios
                     'oninput' => "this.value = this.value.replace(/\\D/g, '').slice(0,10);",
                     // Al perder foco, formatea con espacios: 123 456 7890
                     'onblur' => "let d=this.value.replace(/\\D/g,'').slice(0,10); let f=''; if(d.length>0) f=d.substr(0,3); if(d.length>3) f+=' '+d.substr(3,3); if(d.length>6) f+=' '+d.substr(6); this.value=f;",
                 ])
                 ->rule('regex:/^[\d ]{0,14}$/') // Solo dígitos y espacios
                 ->validationMessages([
                     'regex' => 'Solo se permiten números y espacios.',
                     'max_length' => 'Máximo 10 dígitos permitidos.',
                 ]),
                     
                 Forms\Components\TextInput::make('tel_alternativo')
                     ->label('Teléfono Alterno')
                     ->required()
                     ->maxLength(14) // Hasta 10 dígitos + espacios
                     ->extraInputAttributes([
                         // Mientras escribe, limita a 10 dígitos sin espacios
                         'oninput' => "this.value = this.value.replace(/\\D/g, '').slice(0,10);",
                         // Al perder foco, formatea con espacios: 123 456 7890
                         'onblur' => "let d=this.value.replace(/\\D/g,'').slice(0,10); let f=''; if(d.length>0) f=d.substr(0,3); if(d.length>3) f+=' '+d.substr(3,3); if(d.length>6) f+=' '+d.substr(6); this.value=f;",
                     ])
                     ->rule('regex:/^[\d ]{0,14}$/') // Solo dígitos y espacios
                     ->validationMessages([
                         'regex' => 'Solo se permiten números y espacios.',
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
                            Forms\Components\TextInput::make('imei')
                               ->label('IMEI')
                               ->required()
                               ->rule('regex:/^[A-Za-z0-9]+$/') // Solo caracteres alfanuméricos
                               ->extraInputAttributes([
                                   // Elimina caracteres no alfanuméricos al escribir
                                   'oninput' => "this.value = this.value.replace(/[^A-Za-z0-9]/g, '');",
                                   // Previene pegar caracteres especiales
                                   'onpaste' => "event.preventDefault();",
                                   // Patrón HTML para bloqueo adicional en navegadores
                                   'pattern' => '[A-Za-z0-9]+'
                               ])
                                ->afterStateUpdated(function (\Filament\Forms\Get $get, ?string $state) {
                                    $svc = app(RecompraServiceValSimilar::class);
                                    $resultado = $svc->procesarRecompraPorValorSimilar(2, $state, 'dispositivos_comprados', 'imei', 'El imei '. $state .' del dispositivo ya se encuentra registrado en otro lugar.', 'valor_exacto',['pattern' => 'contains', 'normalize' => true]);
                                
                                })

                               ->validationMessages([
                                   'regex' => 'No se permiten caracteres especiales.',
                               ]),
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
                            ->displayFormat('d/m/Y') // Formato de visualización (opcional)
                            ->native(false)
                            ->format('Y-m-d'),
                         
                          Select::make('idpago')
                              ->label('Periodo de Pago')
                              ->required()
                              ->options(
                                  // Obtener todas las marcas de z_marca
                                  ZPago::all()->pluck('periodo_pago', 'idpago')
                              )
                              ->searchable()
                              ->preload(),

                           
                            Forms\Components\TextInput::make('cuota_inicial')
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
                           ]),
                           Forms\Components\TextInput::make('num_cuotas'),
                           Forms\Components\TextInput::make('valor_cuotas')    ->label('Cuota Inicial')
                           ->required()
                           ->extraInputAttributes([
                               // Formatea con punto cada 3 dígitos en oninput y onblur
                               'oninput' => "let v=this.value.replace(/\D/g,''); let parts=[]; while(v.length>3){ parts.unshift(v.slice(-3)); v=v.slice(0,-3);} if(v) parts.unshift(v); this.value=parts.join('.');",
                               'onblur'  => "let v=this.value.replace(/\D/g,''); let parts=[]; while(v.length>3){ parts.unshift(v.slice(-3)); v=v.slice(0,-3);} if(v) parts.unshift(v); this.value=parts.join('.');",
                           ])
                           ->rule('numeric') // Valida que solo haya números
                           ->validationMessages([
                               'numeric' => 'Debe ser un número válido.',
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
                        ->required()
                        ->extraInputAttributes([
                            'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                            'onblur'  => "this.value = this.value.toUpperCase();",
                        ]),
                   //   ->rule('alpha') // Solo letras
                   //   ->validationMessages([
                   //       'alpha' => 'Solo se permiten letras en el nombre.',
                   //   ]),
                        Forms\Components\TextInput::make('Segundo_Nombre_rf1')
                        ->extraInputAttributes([
                            'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                            'onblur'  => "this.value = this.value.toUpperCase();",
                        ]),
                   //     ->rule('alpha') // Solo letras
                   //     ->validationMessages([
                   //         'alpha' => 'Solo se permiten letras en el nombre.',
                   //     ]),
                        Forms\Components\TextInput::make('Primer_Apellido_rf1')
                        ->required()
                        ->extraInputAttributes([
                            'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                            'onblur'  => "this.value = this.value.toUpperCase();",
                        ]),
                       // ->rule('alpha'), // Solo letras
                    //   ->validationMessages([
                    //       'alpha' => 'Solo se permiten letras en el nombre.',
                    //   ]),
                        Forms\Components\TextInput::make('Segundo_Apellido_rf1')
                        ->extraInputAttributes([
                            'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                            'onblur'  => "this.value = this.value.toUpperCase();",
                        ]),
                    //  ->rule('alpha') // Solo letras
                    //  ->validationMessages([
                    //      'alpha' => 'Solo se permiten letras en el nombre.',
                    //  ]),
                        Forms\Components\TextInput::make('Celular_rf1')
                        ->maxLength(14) // Hasta 10 dígitos + espacios
                        ->extraInputAttributes([
                            // Mientras escribe, limita a 10 dígitos sin espacios
                            'oninput' => "this.value = this.value.replace(/\\D/g, '').slice(0,10);",
                            // Al perder foco, formatea con espacios: 123 456 7890
                            'onblur' => "let d=this.value.replace(/\\D/g,'').slice(0,10); let f=''; if(d.length>0) f=d.substr(0,3); if(d.length>3) f+=' '+d.substr(3,3); if(d.length>6) f+=' '+d.substr(6); this.value=f;",
                        ])
                        ->rule('regex:/^[\d ]{0,14}$/') // Solo dígitos y espacios
                        ->validationMessages([
                            'regex' => 'Solo se permiten números y espacios.',
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
                        ->required()
                        ->extraInputAttributes([
                            'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                            'onblur'  => "this.value = this.value.toUpperCase();",
                        ]),
                  //    ->rule('alpha') // Solo letras
                  //    ->validationMessages([
                  //        'alpha' => 'Solo se permiten letras en el nombre.',
                  //    ]),
                        Forms\Components\TextInput::make('Segundo_Nombre_rf2')
                        ->extraInputAttributes([
                            'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                            'onblur'  => "this.value = this.value.toUpperCase();",
                        ]),
                 //     ->rule('alpha') // Solo letras
                 //     ->validationMessages([
                 //         'alpha' => 'Solo se permiten letras en el nombre.',
                 //     ]),
                        Forms\Components\TextInput::make('Primer_Apellido_rf2')
                        ->required()
                        ->extraInputAttributes([
                            'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                            'onblur'  => "this.value = this.value.toUpperCase();",
                        ]),
                 //      ->rule('alpha') // Solo letras
                 //      ->validationMessages([
                 //          'alpha' => 'Solo se permiten letras en el nombre.',
                 //      ]),
                        Forms\Components\TextInput::make('Segundo_Apellido_rf2')
                        ->extraInputAttributes([
                            'oninput' => "this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();",
                            'onblur'  => "this.value = this.value.toUpperCase();",
                        ]),
               //       ->rule('alpha') // Solo letras
               //       ->validationMessages([
               //           'alpha' => 'Solo se permiten letras en el nombre.',
               //       ]),
                        Forms\Components\TextInput::make('Celular_rf2')
                        ->maxLength(14) // Hasta 10 dígitos + espacios
                        ->extraInputAttributes([
                            // Mientras escribe, limita a 10 dígitos sin espacios
                            'oninput' => "this.value = this.value.replace(/\\D/g, '').slice(0,10);",
                            // Al perder foco, formatea con espacios: 123 456 7890
                            'onblur' => "let d=this.value.replace(/\\D/g,'').slice(0,10); let f=''; if(d.length>0) f=d.substr(0,3); if(d.length>3) f+=' '+d.substr(3,3); if(d.length>6) f+=' '+d.substr(6); this.value=f;",
                        ])
                        ->rule('regex:/^[\d ]{0,14}$/') // Solo dígitos y espacios
                        ->validationMessages([
                            'regex' => 'Solo se permiten números y espacios.',
                            'max_length' => 'Máximo 10 dígitos permitidos.',
                        ]),

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
            ->relationship('detallesCliente')
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

                        $q = ZPlataformaCredito::query()
                            ->whereNotNull('plataforma')
                            ->where('plataforma', '<>', '')
                            ->orderBy('plataforma');

                        if (is_array($allowed)) {
                            $q->whereIn('plataforma', $allowed);
                        }

                        // Evita labels nulos y fuerza string
                        return $q->pluck('plataforma', 'idplataforma')
                            ->map(fn ($label) => (string) $label)
                            ->toArray();
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
                    ->options(function (): array {
                        return ZComision::query()
                            ->whereNotNull('comision')
                            ->where('comision', '<>', '')
                            ->pluck('comision', 'id')
                            ->map(fn ($label) => (string) $label)
                            ->toArray();
                    })
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
                    ->options(function (): array {
                        return InfTrab::query()
                            ->select('Codigo_vendedor')
                            ->whereNotNull('Codigo_vendedor')
                            ->where('Codigo_vendedor', '<>', '')
                            ->orderBy('Codigo_vendedor')
                            ->pluck('Codigo_vendedor', 'Codigo_vendedor')
                            ->map(fn ($label) => (string) $label)
                            ->toArray();
                    })
                    ->searchable()
                    ->reactive()
                    ->required()
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
                    ->options(function () {
                        return ZPdvAgenteDistritec::query()
                            ->whereNotNull('PDV_agente')
                            ->where('PDV_agente', '<>', '')
                            ->orderBy('PDV_agente')
                            ->pluck('PDV_agente', 'ID_PDV_agente')
                            ->map(fn ($label) => (string) $label)
                            ->toArray();
                    })
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

            ])
            ->columns(3)
            ->disableItemCreation() // 👈 Opcional: Bloquear nuevos elementos
            ->disableItemDeletion(), // 👈 Opcional: Bloquear eliminación (sin coma extra)
    ]), 
                       
                       
                      // Sección para dispositivos comprados (relación)
                       Section::make('Estado del Crédito')
                           ->relationship('gestion')       // 👈  relación singular
                           ->schema([
                             // Select::make('ID_Estado_cr')
                             //     ->label('Estado del Crédito')
                             //     // Le indicas que use la relación BelongsTo estadoCredito:
                             //     ->relationship('estadoCredito', 'Estado_Credito')
                             //     //->required()
                             //     ->searchable()
                             //     ->preload(),

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
    public static function table(Table $table): Table
    {
        \Illuminate\Support\Facades\Log::info('Filament ClienteResource Table Debug - Method table() entered.');

        // Helpers de estilo reutilizables
        $compactCell = fn () => ['class' => 'py-1 px-2 text-[11px] whitespace-nowrap text-center'];
        $compactHead = fn () => ['class' => 'py-1 px-2 text-[10px] uppercase tracking-wide text-center'];

        return $table
            ->defaultSort('id_cliente', 'desc')

            ->columns([
                // 1) ID — centrado
                Tables\Columns\TextColumn::make('id_cliente')
                    ->label('ID')
                    ->extraCellAttributes($compactCell())
                    ->extraHeaderAttributes($compactHead())
                    ->alignCenter()
                    ->sortable()
                    ->searchable(),

                // 2) Fecha (Fecha + Hora con salto de línea) — centrado
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->getStateUsing(function ($record) {
                        $rawFecha = $record->fecha_registro ?? $record->created_at ?? null;
                        $rawHora  = $record->hora ?? null;

                        try {
                            $dt = $rawFecha
                                ? (\Carbon\Carbon::parse($rawFecha)->timezone(config('app.timezone')))
                                : null;
                        } catch (\Throwable $e) {
                            $dt = null;
                        }

                        $fecha = $dt ? $dt->format('d/m/Y') : '—';

                        if (!empty($rawHora)) {
                            try {
                                $hora = \Carbon\Carbon::parse($rawHora)->format('H:i');
                            } catch (\Throwable $e) {
                                $hora = (string) $rawHora;
                            }
                        } elseif ($dt) {
                            $hora = $dt->format('H:i');
                        } else {
                            $hora = '—';
                        }

                        return new \Illuminate\Support\HtmlString(e($fecha) . '<br>' . e($hora));
                    })
                    ->html()
                    ->extraCellAttributes($compactCell())
                    ->extraHeaderAttributes($compactHead())
                    ->alignCenter()
                    ->sortable(false)
                    ->searchable(false),

                // 3) Nombre — centrado (dos líneas)
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->getStateUsing(function ($record) {
                        $row = DB::table('clientes_nombre_completo')
                            ->select([
                                'Primer_nombre_cliente',
                                'Segundo_nombre_cliente',
                                'Primer_apellido_cliente',
                                'Segundo_apellido_cliente',
                            ])
                            ->where('ID_Cliente', $record->id_cliente)
                            ->first();

                        if (!$row) {
                            return new \Illuminate\Support\HtmlString('N/A');
                        }

                        $nombres = trim(collect([
                            $row->Primer_nombre_cliente ?? null,
                            $row->Segundo_nombre_cliente ?? null,
                        ])->filter()->implode(' '));

                        $apellidos = trim(collect([
                            $row->Primer_apellido_cliente ?? null,
                            $row->Segundo_apellido_cliente ?? null,
                        ])->filter()->implode(' '));

                        return new \Illuminate\Support\HtmlString(e($nombres) . '<br>' . e($apellidos));
                    })
                    ->html()
                    ->tooltip(fn ($state) => trim(preg_replace('/\s+/', ' ', strip_tags((string) $state))))
                    ->extraCellAttributes($compactCell())
                    ->extraHeaderAttributes($compactHead())
                    ->alignCenter()
                    ->sortable(false)
                    ->searchable(false),

                // 4) Tipo Documento — solo siglas (centrado)
                Tables\Columns\TextColumn::make('tipoDocumento.desc_identificacion')
                    ->label('Tipo Documento')
                    ->getStateUsing(function ($record) {
                        $tipoDocId = $record->ID_Identificacion_Cliente;
                        $tipoDocumento = \App\Models\TipoDocumento::find($tipoDocId);
                        if (!$tipoDocumento) return 'N/A';

                        $siglas = $tipoDocumento->siglas ?? $tipoDocumento->sigla ?? null;
                        if (!$siglas) {
                            $desc  = (string) ($tipoDocumento->desc_identificacion ?? '');
                            $words = preg_split('/\s+/', trim($desc), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                            $siglas = strtoupper(collect($words)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
                        }

                        $descUpper = strtoupper((string) ($tipoDocumento->desc_identificacion ?? ''));
                        if (strtoupper($siglas) === 'P' || str_contains($descUpper, 'PASAPORTE')) {
                            $siglas = 'PPT';
                        }

                        return $siglas ?: 'N/A';
                    })
                    ->extraCellAttributes($compactCell())
                    ->extraHeaderAttributes($compactHead())
                    ->alignCenter()
                    ->sortable(false)
                    ->searchable(false),

                // 5) Cédula — centrado
                Tables\Columns\TextColumn::make('cedula')
                    ->label('Cédula')
                    ->extraCellAttributes($compactCell())
                    ->extraHeaderAttributes($compactHead())
                    ->alignCenter()
                    ->searchable(),

                // 6) Tipo de Crédito — centrado
                Tables\Columns\TextColumn::make('tipoCredito.Tipo')
                    ->label('Tipo de Crédito')
                    ->extraCellAttributes($compactCell())
                    ->extraHeaderAttributes($compactHead())
                    ->alignCenter()
                    ->sortable()
                    ->searchable(),

                // 7) Sede — centrado
                Tables\Columns\TextColumn::make('detallesCliente.sede.Name_Sede')
                    ->label('Sede')
                    ->extraCellAttributes($compactCell())
                    ->extraHeaderAttributes($compactHead())
                    ->alignCenter(),

              // // 8) Nombre Auxiliar — centrado
              // Tables\Columns\SelectColumn::make('gestion.ID_Gestor')
              //     ->label('Nombre Auxiliar')
              //     ->options(fn () => GestorDistritec::pluck('Nombre_gestor', 'ID_Gestor')->toArray())
              //     ->getStateUsing(fn ($record) => $record->gestion?->ID_Gestor)
              //     ->updateStateUsing(function ($record, $state) {
              //         if (! auth()->user()?->hasRole('gestor cartera')) {
              //             return $record->gestion?->ID_Gestor;
              //         }
              //         $record->gestion()->updateOrCreate(
              //             ['id_cliente' => $record->id_cliente],
              //             ['ID_Gestor'  => $state],
              //         );
              //         $record->refresh();
              //         event(new ClienteUpdated($record));
              //         return $state;
              //     })
              //     ->disabled(fn ($record, $state) => filled($state))
              //     ->searchable()
              //     ->sortable()
              //     ->extraCellAttributes($compactCell())
              //     ->extraHeaderAttributes($compactHead())
              //     ->alignCenter()
              //     ->visible(fn () => auth()->user()?->hasRole('gestor cartera')),

              // // 9) Estado — centrado
              // Tables\Columns\SelectColumn::make('gestion.ID_Estado_cr')
              //     ->label('Estado')
              //     ->options(fn () => ZEstadocredito::pluck('Estado_Credito', 'ID_Estado_cr')->toArray())
              //     ->getStateUsing(fn ($record) => $record->gestion?->ID_Estado_cr)
              //     ->updateStateUsing(function ($record, $state) {
              //             $now = now();

              //             // ¿Ya existe gestión para este cliente?
              //             $gestion = $record->gestion()->first();

              //             // Siempre actualizamos el estado y la marca de "updated"
              //             $attrs = [
              //                 'ID_Estado_cr'          => $state,
              //                 'Estado_cr_updated_at'  => $now,
              //             ];

              //             // Solo la PRIMERA vez que se seleccione el estado, fijamos "created"
              //             if (! $gestion || is_null($gestion->Estado_cr_created_at)) {
              //                 $attrs['Estado_cr_created_at'] = $now;
              //             }

              //             // Crea/actualiza sin tocar created_at/updated_at de la tabla
              //             $record->gestion()->updateOrCreate(
              //                 ['id_cliente' => $record->id_cliente],
              //                 $attrs,
              //             );

              //             // Mantén el comportamiento existente
              //             $record->refresh();
              //             event(new ClienteUpdated($record));
              //             return $state;
              //         })
              //     ->disabled(function ($record, $state) {
              //         $id = $state ?? $record->gestion?->ID_Estado_cr;
              //         if (blank($id)) return false;
              //         $texto = optional(ZEstadocredito::find($id))->Estado_Credito;
              //         $esPendiente = ((int)$id === 1) || (is_string($texto) && \Illuminate\Support\Str::lower($texto) === 'pendiente');
              //         return ! $esPendiente;
              //     })
              //     ->searchable()
              //     ->sortable()
              //     ->extraCellAttributes($compactCell())
              //     ->extraHeaderAttributes($compactHead())
              //     ->alignCenter(),

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
                        // 1) Guardar/crear gestión con el gestor
                        $record->gestion()->updateOrCreate(
                            ['id_cliente' => $record->id_cliente],
                            ['ID_Gestor'  => $state],
                        );

                        // 2) Cargar la relación con el gestor para sacar el nombre
                        $record->load('gestion.gestorDistritec');

                        $gestorNombre = optional($record->gestion?->gestorDistritec)->Nombre_gestor;

                        \Log::debug(' [SelectColumn Gestor] Disparando GestorUpdated', [
                            'cliente_id'    => $record->id_cliente,
                            'gestor_id'     => $state,
                            'gestor_nombre' => $gestorNombre,
                            'user_id'       => auth()->id(),
                        ]);

                        // 3) Evento ligero, SIN mandar todo el modelo gigante
                        event(new GestorUpdated(
                            clienteId: (int) $record->id_cliente,
                            gestorId: $state ? (int) $state : null,
                            gestorNombre: $gestorNombre ? (string) $gestorNombre : null,
                            userId: auth()->id() ? (int) auth()->id() : null,
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

                        // Desactivamos loader global para este select
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

                    Log::debug(' [SelectColumn Estado] INICIO updateStateUsing', [
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

                    Log::debug(' [SelectColumn Estado] Gestion guardada/updateOrCreate', [
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

                    Log::debug(' [SelectColumn Estado] EstadoCredito cargado', [
                        'cliente_id'   => $record->id_cliente ?? null,
                        'estado_id'    => $state,
                        'estado_texto' => $texto,
                    ]);

                    //  Aquí antes lanzabas ClienteUpdated($record) => lo quitamos
                    //    Tampoco hacemos $record->refresh() para no reventar nada.

                    try {
                        event(new EstadoCreditoUpdated(
                            clienteId: (int) $record->id_cliente,
                            estadoId: $state ? (int) $state : null,
                            estadoTexto: $texto,
                            userId: Auth::id(),
                        ));

                        Log::debug(' [SelectColumn Estado] Evento EstadoCreditoUpdated disparado', [
                            'cliente_id'   => $record->id_cliente ?? null,
                            'estado_id'    => $state,
                            'estado_texto' => $texto,
                            'user_id'      => Auth::id(),
                        ]);
                    } catch (\Throwable $e) {
                        Log::error(' [SelectColumn Estado] Error al disparar EstadoCreditoUpdated', [
                            'cliente_id' => $record->id_cliente ?? null,
                            'estado_id'  => $state,
                            'exception'  => $e->getMessage(),
                            'trace'      => $e->getTraceAsString(),
                        ]);
                    }

                    Log::debug(' [SelectColumn Estado] FIN updateStateUsing', [
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

                // 10) Chat — centrado
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
                        'class' => '!text-primary-600 hover:!text-primary-700 cursor-pointer',
                        'style' => 'min-width: 2.5rem;',
                        'data-client-id' => $record->id_cliente,
                    ])
                    ->extraCellAttributes($compactCell())
                    ->extraHeaderAttributes($compactHead())
                    ->action(function (Cliente $record, $livewire) {
                        $id = (int) $record->id_cliente;
                        $chat = \App\Filament\Widgets\ChatWidget::class;
                        $livewire->dispatch('setClientId', clientId: $id)->to($chat);
                        $livewire->dispatch('openChat')->to($chat);
                    }),

                // 11) Documentación — centrado
                \Filament\Tables\Columns\IconColumn::make('documentacion')
                    ->label('Documentación')
                    ->getStateUsing(fn (Cliente $record) => true)
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->tooltip('Ver/Editar Documentos')
                    ->alignCenter()
                    ->size(\Filament\Tables\Columns\IconColumn\IconColumnSize::Large)
                    ->sortable(false)
                    ->extraCellAttributes($compactCell())
                    ->extraHeaderAttributes($compactHead())
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

                // 12) Comentarios — centrado + tooltip con texto COMPLETO
                Tables\Columns\BadgeColumn::make('gestion.comentarios')
                    ->label('Comentarios')
                    ->limit(30) // en celda truncado
                    ->tooltip(fn ($state) => (string) strip_tags((string) $state)) // en hover, completo
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
                    ->alignCenter()
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
            ])

            ->filters([
                // ========= Filtros específicos primero =========

                // Teléfonos (antes "Número")
                Tables\Filters\Filter::make('telefono')
                    ->label('Teléfonos')
                    ->form([
                        Forms\Components\TextInput::make('numero')
                            ->label('Telefonos')
                            ->placeholder('Mínimo 7 dígitos'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $num = preg_replace('/\D+/', '', (string)($data['numero'] ?? ''));
                        if (strlen($num) < 7) return $query;

                        $tbl = $query->getModel()->getTable();

                        // Unión de todas las fuentes en una sola tabla derivada
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

                        if (empty($parts)) return $query;

                        $union = implode(" UNION ALL ", $parts);

                        // Un único EXISTS con prefijo
                        return $query->whereExists(function ($s) use ($tbl, $union, $num) {
                            $s->select(DB::raw(1))
                            ->from(DB::raw("({$union}) AS phones"))
                            ->whereColumn('phones.ID_Cliente', "{$tbl}.id_cliente")
                            ->whereRaw('phones.num LIKE ?', [$num.'%']);
                        });
                    }),

                // IMEI exacto
                Tables\Filters\Filter::make('exactos')
                    ->form([
                        Forms\Components\TextInput::make('imei_exacto')->label('IMEI exacto'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        $tbl = $query->getModel()->getTable();

                        if (!empty($data['imei_exacto'])) {
                            $imei = preg_replace('/\D+/', '', $data['imei_exacto']);
                            if ($imei !== '') {
                                $query->whereHas('dispositivoscomprados', fn ($w) =>
                                    $w->whereRaw("REGEXP_REPLACE(COALESCE(imei,''), '[^0-9]', '') = ?", [$imei])
                                );
                            }
                        }
                        return $query;
                    }),

                // Filtro por fecha (como en el primer snippet)
                Tables\Filters\Filter::make('fecha')
                    ->form([Forms\Components\DatePicker::make('fecha')])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        if (!empty($data['fecha'])) {
                            $query->whereDate('created_at', $data['fecha']);
                        }
                        return $query;
                    }),

                // Select por relación: Gestor
                Tables\Filters\SelectFilter::make('gestor')
                    ->relationship('gestion.gestorDistritec', 'Nombre_gestor')
                    ->searchable()
                    ->preload(),

                // Nombre Completo (tokens en cualquier parte)
                Tables\Filters\Filter::make('nombre_completo')
                    ->label('Nombre Completo')
                    ->form([
                        Forms\Components\TextInput::make('nombre_completo')
                            ->label('Nombre Completo')
                            ->placeholder('Ingrese el nombre completo'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['nombre_completo'])) {
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

                // Select por relación: Estado
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
            ]);
    }





 public static function getEloquentQuery(): Builder
{
    $query   = parent::getEloquentQuery();
    $u       = auth()->user();
    $allowed = static::allowedSedeIdsFor($u);

    if ($allowed === []) {
        return $query; // admin/super_admin: sin filtro
    }

    if (empty($allowed) || (count($allowed) === 1 && $allowed[0] === '__none__')) {
        return $query->whereRaw('1=0'); // nada
    }

    // Solo el filtro, sin eager loading “lean”
    return $query->whereHas('detallesCliente', function ($q) use ($allowed) {
        $q->whereIn('idsede', $allowed);
    });
}


    

    public static function getPages(): array
    {
        return [
            // Mantener la página de índice (listado) para poder seleccionar un cliente a editar
            'index' => Pages\ListSociosGestion::route('/'),

            // Mantener la página de edición
            'edit' => Pages\EditSociosGestion::route('/{record}/edit'),
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