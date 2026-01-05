<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConsignacionResource\Pages;
use App\Models\Consignacion;
use App\Models\ClienteConsignacion;
use App\Models\Producto;
use App\Models\ZBodegaFacturacion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Actions\Action;

use Filament\Forms\Get as FormGet;
use Filament\Forms\Set as FormSet;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;  // ← Agrega esta línea
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;

class ConsignacionResource extends Resource
{
    protected static ?string $model = Consignacion::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    
    protected static ?string $navigationLabel = 'Consignaciones';
    
    protected static ?string $navigationGroup = 'Consignaciones';
    
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ============================================
                // SECCIÓN 1: INFORMACIÓN DEL ASESOR
                // ============================================
                Forms\Components\Section::make('Información del Asesor')
                    ->description('Datos del vendedor o asesor responsable')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([

                                Forms\Components\Select::make('codigo_asesor')
                                    ->label('Código Asesor')
                                    ->options(\App\Models\InfTrab::pluck('Codigo_vendedor', 'Codigo_vendedor'))
                                    ->searchable()
                                    ->reactive()
                                    ->required()
                                    ->disabled()
                                    ->required(fn () => ! auth()->user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                    ->hidden(fn ()   =>   auth()->user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                    ->dehydrated(fn () => ! auth()->user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                    ->default(function () {
                                        $user   = auth()->user();
                                        $cedula = $user?->cedula;

                                        \Log::info('[Facturacion] default(codigo_asesor)', [
                                            'user_id' => $user?->id,
                                            'cedula'  => $cedula,
                                            'roles'   => $user?->getRoleNames()->toArray(),
                                        ]);

                                        if (! $cedula) {
                                            \Log::warning('[Facturacion] default(codigo_asesor) - usuario sin cédula');
                                            return null;
                                        }

                                        if ($user?->hasRole('socio')) {
                                            $socio = \App\Models\SocioDistritec::where('Cedula', $cedula)->first();
                                            \Log::info('[Facturacion] default(codigo_asesor) - socio detectado', [
                                                'socio' => $socio,
                                            ]);
                                            return $socio?->soc_cod_vendedor;
                                        }

                                        $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                        \Log::info('[Facturacion] default(codigo_asesor) - asesor buscado', [
                                            'asesor' => $asesor,
                                        ]);
                                        if (! $asesor) return null;

                                        $aaPrin = \App\Models\AaPrin::where('ID_Asesor', $asesor->ID_Asesor)
                                            ->where('ID_Estado', '!=', 3)
                                            ->orderByDesc('ID_Inf_trab')
                                            ->first();

                                        \Log::info('[Facturacion] default(codigo_asesor) - aaPrin', [
                                            'aaPrin' => $aaPrin,
                                        ]);

                                        if (! $aaPrin) return null;

                                        $infTrab = \App\Models\InfTrab::find($aaPrin->ID_Inf_trab);
                                        \Log::info('[Facturacion] default(codigo_asesor) - infTrab', [
                                            'infTrab' => $infTrab,
                                        ]);

                                        return $infTrab?->Codigo_vendedor;
                                    })
                                    ->extraAttributes(['class' => 'text-lg font-bold']),

                                Forms\Components\TextInput::make('nombre_asesor')
                                    ->label('Nombre del Asesor')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default(function () {
                                        $user = auth()->user();
                                        return $user->name ?? 'N/A';
                                    }),


                                Forms\Components\TextInput::make('sede_nombre')
                                    ->label('Sede (Nombre)')
                                    ->disabled()
                                    ->hidden(fn ()   => auth()->user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                    ->dehydrated(fn () => ! auth()->user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                    ->default(function () {
                                        $user   = auth()->user();
                                        $cedula = $user?->cedula;

                                        // Para SOCIO, la sede se define desde idsede_select
                                        if ($user?->hasRole('socio')) {
                                            \Log::info('[Facturacion] default(sede_nombre) - socio, sede se elige manualmente');
                                            return null;
                                        }

                                        if (! $cedula) {
                                            \Log::warning('[Facturacion] default(sede_nombre) - usuario sin cédula');
                                            return null;
                                        }

                                        $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                        if (! $asesor) {
                                            \Log::warning('[Facturacion] default(sede_nombre) - asesor no encontrado');
                                            return null;
                                        }

                                        $aaPrin = \App\Models\AaPrin::where('ID_Asesor', $asesor->ID_Asesor)
                                            ->where('ID_Estado', '!=', 3)
                                            ->orderByDesc('ID_Inf_trab')
                                            ->with('sede')
                                            ->first();

                                        \Log::info('[Facturacion] default(sede_nombre) - aaPrin asesor', [
                                            'aaPrin' => $aaPrin,
                                        ]);

                                        return $aaPrin && $aaPrin->sede
                                            ? mb_strtoupper($aaPrin->sede->Name_Sede ?? '', 'UTF-8')
                                            : null;
                                    })
                                    ->extraAttributes(['class' => 'text-lg font-bold']),
                            ]),

                        Forms\Components\Hidden::make('vendedor_id')
                            ->default(function () {
                                $user = auth()->user();
                                $cedula = $user->cedula ?? null;
                                $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                return $asesor?->ID_Asesor;
                            })
                            ->required(),
                    ])
                    ->columns(1)
                    ->collapsible(),

                // ============================================
                // SECCIÓN 2: INFORMACIÓN DEL CLIENTE
                // ============================================
                Forms\Components\Section::make('Información del Cliente')
                    ->description('Ingrese la cédula del cliente')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('busqueda_tercero')
                                    ->label('Buscar tercero (cédula o nombre)Ingrese al menos 2 caracteres ')
                                    //->helperText('Ingrese al menos 2 caracteres (cédula o nombre) y presione "Buscar".')
                                    ->extraAttributes([
                                        'class' => 'w-full text-sm md:text-base',
                                        // ENTER aquí dispara SOLO la lupa principal
                                        'x-on:keydown.enter.prevent' => '$refs.buscarTerceroBtn && $refs.buscarTerceroBtn.click()',
                                    ])
                                    ->live(false)
                                    ->afterStateHydrated(function ($state, \Filament\Forms\Set $set) {
                                        // Si el campo está vacío pero la URL trae ?cedula=, la ponemos como valor
                                        if (blank($state) && request()->has('cedula')) {
                                            $cedula = trim((string) request()->query('cedula'));
                                            if ($cedula !== '') {
                                                $set('busqueda_tercero', $cedula);
                                            }
                                        }
                                    })
                                    ->columnSpan([
                                        'default' => 8, // en móvil ocupa todo el ancho
                                        'md'      => 4, // desde md ocupa 5/8
                                        'lg'      => 4,
                                    ])
                                    ->suffixActions([
                                        //  Buscar tercero
                                        Action::make('buscarTercero')
                                            ->label('Buscar')
                                            ->icon('heroicon-o-magnifying-glass')
                                            ->color('primary')
                                            ->extraAttributes([
                                                'x-ref' => 'buscarTerceroBtn',
                                            ])
                                            ->action(function (FormGet $get, FormSet $set) {
                                                $query = trim((string) ($get('busqueda_tercero') ?? ''));

                                                // Si el campo está vacío pero viene ?cedula= en la URL, usarla
                                                if ($query === '' && request()->has('cedula')) {
                                                    $queryFromRequest = trim((string) request()->query('cedula'));

                                                    if ($queryFromRequest !== '') {
                                                        $query = $queryFromRequest;
                                                        // rellenamos el input para que el usuario vea la cédula
                                                        $set('busqueda_tercero', $queryFromRequest);
                                                    }
                                                }

                                                if (mb_strlen($query) < 2) {
                                                    Notification::make()
                                                        ->title('Búsqueda demasiado corta')
                                                        ->body('Debe ingresar al menos 2 caracteres (cédula o nombre) para buscar.')
                                                        ->warning()
                                                        ->send();
                                                    return;
                                                }


                                                // Limpiar caché y estado previo
                                                $oldToken = $get('resultados_busqueda_token');
                                                if ($oldToken) {
                                                    Cache::forget("facturacion_terceros_{$oldToken}");
                                                }

                                                // Reset antes de nueva búsqueda
                                                $set('mostrar_modal_resultados', false);
                                                $set('mostrar_modal_crear_tercero', false);
                                                $set('resultados_busqueda_token', null);
                                                $set('resultados_busqueda', []);
                                                $set('pagina_resultados', 1);
                                                $set('filtro_cedula', '');
                                                $set('filtro_nombre', '');
                                                $set('filtro_telefono', '');

                                                // Limpiar datos del cliente seleccionado
                                                $set('cedula', null);
                                                $set('tipo_identificacion_cliente', null);
                                                $set('nombre_cliente', null);
                                                $set('nombre1_cliente', null);
                                                $set('nombre2_cliente', null);
                                                $set('apellido1_cliente', null);
                                                $set('apellido2_cliente', null);
                                                $set('telefono', null);
                                                $set('direccion', null);
                                                $set('email_cliente', null);
                                                $set('ciudad_cliente', null);
                                                $set('zona_cliente', null);
                                                $set('nombre_comercial_cliente', null);
                                                $set('barrio_cliente', null);
                                                $set('mostrar_detalle_tercero', false);

                                                try {
                                                    Log::info('[Facturacion] Iniciando búsqueda de tercero', ['query' => $query]);
                                                    
                                                    $response = Http::acceptJson()
                                                        ->timeout(30) // Agregar timeout
                                                        ->post(route('api.terceros.buscar'), [
                                                            'query' => $query,
                                                        ]);

                                                    Log::info('[Facturacion] Respuesta recibida', [
                                                        'status' => $response->status(),
                                                        'headers' => $response->headers(),
                                                    ]);

                                                    if (! $response->ok()) {
                                                        Log::warning('[Facturacion] buscarTercero - respuesta no OK', [
                                                            'status' => $response->status(),
                                                            'body'   => $response->body(),
                                                        ]);

                                                        Notification::make()
                                                            ->title('Error al consultar terceros')
                                                            ->body('No fue posible consultar la información. Intente nuevamente.')
                                                            ->danger()
                                                            ->send();
                                                        return;
                                                    }

                                                    $body = $response->json();
                                                    Log::info('[Facturacion] buscarTercero - respuesta parseada', ['body' => $body]);

                                                    if (! ($body['success'] ?? false)) {
                                                        Log::info('[Facturacion] Búsqueda sin éxito', ['message' => $body['message'] ?? 'Sin mensaje']);
                                                        
                                                        Notification::make()
                                                            ->title('Sin resultados')
                                                            ->body($body['message'] ?? 'No se encontraron terceros con los criterios de búsqueda.')
                                                            ->warning()
                                                            ->send();

                                                        // Mostrar modal vacío con mensaje sin resultados
                                                        $set('resultados_busqueda_token', null);
                                                        $set('resultados_busqueda', []);
                                                        $set('pagina_resultados', 1);
                                                        $set('mostrar_modal_resultados', true);
                                                        $set('mostrar_modal_crear_tercero', false);
                                                        $set('mostrar_detalle_tercero', false);
                                                        return;
                                                    }

                                                    $data = $body['data'] ?? [];
                                                    Log::info('[Facturacion] Data extraída', ['count' => is_array($data) ? count($data) : 'not-array']);

                                                    if (! is_array($data)) {
                                                        $data = [$data];
                                                    }

                                                    // ============================
                                                    // MAPEO UNIFORME de datos
                                                    // ============================
                                                    Log::info('[Facturacion] Iniciando mapeo de resultados');
                                                    
                                                    $clientesOriginales = collect($data)->map(function ($item) {
                                                        try {
                                                            $arr = is_array($item) ? $item : (array) $item;

                                                            $cedula = $arr['cedula']
                                                                ?? $arr['nit']
                                                                ?? $arr['codigoInterno']
                                                                ?? null;

                                                            $razonSocial = $arr['descripcion']
                                                                ?? $arr['nombreComercial']
                                                                ?? $arr['ID_Cliente_Nombre']
                                                                ?? null;

                                                            $nombreGenerico = $arr['nombre']
                                                                ?? $arr['nombre_completo']
                                                                ?? $razonSocial;

                                                            $telefono = $arr['telefono']
                                                                ?? $arr['tel']
                                                                ?? $arr['telefono1']
                                                                ?? $arr['celular']
                                                                ?? null;

                                                            $direccion = $arr['direccion'] ?? null;

                                                            $email = $arr['email']
                                                                ?? $arr['emailFacturacionElectronica']
                                                                ?? null;

                                                            $ciudad = $arr['descripcionCiudad'] ?? null;
                                                            $zona   = $arr['descripcionZona'] ?? null;

                                                            $tipoIdDesc = $arr['descTipoIdentificacionTrib'] ?? null;

                                                            $nombre1   = $arr['nombre1']   ?? null;
                                                            $nombre2   = $arr['nombre2']   ?? null;
                                                            $apellido1 = $arr['apellido1'] ?? null;
                                                            $apellido2 = $arr['apellido2'] ?? null;

                                                            $nombreComercial = $arr['nombreComercial'] ?? null;
                                                            $barrio          = $arr['barrio'] ?? null;

                                                            return [
                                                                'cedula'              => $cedula ? (string) $cedula : null,
                                                                'nombre'              => $nombreGenerico,
                                                                'telefono'            => $telefono ? (string) $telefono : null,
                                                                'direccion'           => $direccion,
                                                                'email'               => $email,
                                                                'ciudad'              => $ciudad,
                                                                'zona'                => $zona,
                                                                'tipo_identificacion' => $tipoIdDesc,
                                                                'razon_social'        => $razonSocial,
                                                                'nombre1'             => $nombre1,
                                                                'nombre2'             => $nombre2,
                                                                'apellido1'           => $apellido1,
                                                                'apellido2'           => $apellido2,
                                                                'nombre_comercial'    => $nombreComercial,
                                                                'barrio'              => $barrio,
                                                            ];
                                                        } catch (\Throwable $e) {
                                                            Log::error('[Facturacion] Error mapeando item individual', [
                                                                'item' => $item,
                                                                'error' => $e->getMessage(),
                                                            ]);
                                                            throw $e; // Re-lanzar para que el catch principal lo capture
                                                        }
                                                    })->toArray();

                                                    Log::info('[Facturacion] Mapeo completado', ['total' => count($clientesOriginales)]);

                                                    $totalEncontrados = count($clientesOriginales);

                                                    if ($totalEncontrados === 0) {
                                                        Notification::make()
                                                            ->title('Sin resultados')
                                                            ->body('No se encontraron terceros con los criterios de búsqueda.')
                                                            ->warning()
                                                            ->send();

                                                        $set('resultados_busqueda_token', null);
                                                        $set('resultados_busqueda', []);
                                                        $set('pagina_resultados', 1);
                                                        $set('mostrar_modal_resultados', true);
                                                        $set('mostrar_modal_crear_tercero', false);
                                                        $set('mostrar_detalle_tercero', false);
                                                        return;
                                                    }

                                                    // Guardar TODOS los resultados en caché (solo 5 min)
                                                    Log::info('[Facturacion] Guardando en caché');
                                                    $token = (string) Str::uuid();
                                                    Cache::put(
                                                        "facturacion_terceros_{$token}",
                                                        $clientesOriginales,
                                                        now()->addMinutes(5)
                                                    );

                                                    $set('resultados_busqueda_token', $token);
                                                    Log::info('[Facturacion] Token generado', ['token' => $token]);

                                                    // Paginación inicial
                                                    $perPage = (int) ($get('per_page_resultados') ?? 5);
                                                    if (! in_array($perPage, [5, 10, 20])) {
                                                        $perPage = 5;
                                                    }

                                                    Log::info('[Facturacion] Aplicando paginación', ['perPage' => $perPage]);
                                                    $set('pagina_resultados', 1);
                                                    $slice = array_slice($clientesOriginales, 0, $perPage);
                                                    $set('resultados_busqueda', $slice);

                                                    // Mostrar modal inline de resultados
                                                    $set('mostrar_modal_resultados', true);
                                                    $set('mostrar_modal_crear_tercero', false);
                                                    $set('mostrar_detalle_tercero', false);

                                                    Log::info('[Facturacion] Búsqueda completada exitosamente');

                                                    Notification::make()
                                                        ->title('Resultados cargados')
                                                        ->body('Se encontraron ' . $totalEncontrados . ' coincidencias.')
                                                        ->success()
                                                        ->send();
                                                        
                                                } catch (\Throwable $e) {
                                                    Log::error('[Facturacion] Error en buscarTercero', [
                                                        'exception' => get_class($e),
                                                        'message'   => $e->getMessage(),
                                                        'file'      => $e->getFile(),
                                                        'line'      => $e->getLine(),
                                                        'trace'     => $e->getTraceAsString(),
                                                    ]);

                                                    Notification::make()
                                                        ->title('Error al consultar terceros')
                                                        ->body('Ocurrió un error: ' . $e->getMessage())
                                                        ->danger()
                                                        ->send();
                                                }
                                            }),

                                        ])

                                        ->columnSpan(5),   // ← ocupa 9 de 12 columnas (ajustable)

                                        // ⬇️ NUEVO: botón Crear tercero separado, alineado a la derecha
                                        \Filament\Forms\Components\Actions::make([

                                    ])

                                    ->columnSpan([
                                        'default' => 8, // móvil: debajo, toda la fila
                                        'md'      => 4, // md+: 2/8, siempre a la derecha
                                        'lg'      => 4,
                                    ])
                                    ->extraAttributes([
                                        // móvil: solo margen arriba normal
                                        // md+: alinear al final a la derecha, en la misma línea visual que el input
                                        'class' => 'flex w-full justify-end mt-2 md:mt-0 md:items-end',
                                    ]),

                                Section::make()
                                    ->visible(fn (FormGet $get) => (bool) $get('mostrar_modal_resultados'))
                                    ->extraAttributes([
                                        'class' => 'w-full mt-4',
                                    ])
                                    ->schema([
                                        Group::make([
                                            // Cabecera (limpiar filtros + cerrar)
                                            Grid::make(1)
                                                ->schema([
                                                    Actions::make([
                                                        Action::make('limpiarFiltros')
                                                            ->label('Limpiar filtros')
                                                            ->icon('heroicon-o-arrow-path-rounded-square')
                                                            ->color('secondary')
                                                            ->extraAttributes([
                                                                'class' => 'text-xs mr-2',
                                                            ])
                                                            ->visible(function (FormGet $get) {
                                                                $token = $get('resultados_busqueda_token');
                                                                if (! $token) {
                                                                    return false;
                                                                }
                                                                $base = Cache::get("facturacion_terceros_{$token}", []);
                                                                return is_array($base) && count($base) > 0;
                                                            })
                                                            ->action(function (FormGet $get, FormSet $set) {
                                                                $set('filtro_cedula', '');
                                                                $set('filtro_nombre', '');
                                                                $set('filtro_telefono', '');
                                                                self::aplicarFiltrosYPaginar($get, $set);
                                                            }),

                                                        Action::make('cerrarModalResultados')
                                                            ->label('')
                                                            ->icon('heroicon-o-x-mark')
                                                            ->color('secondary')
                                                            ->extraAttributes([
                                                                'class' => 'ml-auto text-gray-700 hover:text-gray-900 dark:text-gray-200 dark:hover:text-white',
                                                            ])
                                                            ->action(function (FormSet $set) {
                                                                $set('mostrar_modal_resultados', false);
                                                            }),
                                                    ])->alignment('right'),
                                                ]),

                                            // Filtros por columna
                                            Grid::make([
                                                'default' => 6,
                                                'sm'      => 6,
                                                'md'      => 6,
                                            ])
                                                ->visible(function (FormGet $get) {
                                                    $token = $get('resultados_busqueda_token');
                                                    if (! $token) {
                                                        return false;
                                                    }
                                                    $base = Cache::get("facturacion_terceros_{$token}", []);
                                                    return is_array($base) && count($base) > 0;
                                                })
                                                ->schema([
                                                    TextInput::make('filtro_dummy_sel')
                                                        ->label('Seleccionar')
                                                        ->disabled()
                                                        ->extraAttributes([
                                                            'class' => 'text-xs text-gray-600 dark:text-gray-300 bg-transparent border-none text-center',
                                                            'style' => 'text-align:center;',
                                                        ])
                                                        ->dehydrated(false)
                                                        ->columnSpan(1),

                                                    TextInput::make('filtro_cedula')
                                                        ->label('Cédula')
                                                        ->extraAttributes([
                                                            'class' =>
                                                                'text-xs text-gray-900 dark:text-gray-100 text-center font-medium ' .
                                                                'dark:bg-gray-900 dark:border-gray-700 dark:placeholder-gray-500',
                                                            'style' => 'text-align:center;',
                                                            'x-on:keydown.enter.prevent' => '$refs.filtrarCedulaBtn && $refs.filtrarCedulaBtn.click()',
                                                        ])
                                                        ->suffixAction(
                                                            Action::make('filtrarCedula')
                                                                ->icon('heroicon-o-magnifying-glass')
                                                                ->color('primary')
                                                                ->extraAttributes([
                                                                    'class' => 'text-xs',
                                                                    'x-ref' => 'filtrarCedulaBtn',
                                                                ])
                                                                ->action(function (FormGet $get, FormSet $set) {
                                                                    $valor = trim((string) ($get('filtro_cedula') ?? ''));
                                                                    $set('filtro_cedula', $valor);
                                                                    self::aplicarFiltrosYPaginar($get, $set);
                                                                })
                                                        )
                                                        ->dehydrated(false)
                                                        ->columnSpan(1),

                                                    TextInput::make('filtro_nombre')
                                                        ->label('Nombre')
                                                        ->extraAttributes([
                                                            'class' =>
                                                                'text-xs text-gray-900 dark:text-gray-100 text-center font-medium ' .
                                                                'dark:bg-gray-900 dark:border-gray-700 dark:placeholder-gray-500',
                                                            'style' => 'text-align:center;',
                                                            'x-on:keydown.enter.prevent' => '$refs.filtrarNombreBtn && $refs.filtrarNombreBtn.click()',
                                                        ])
                                                        ->suffixAction(
                                                            Action::make('filtrarNombre')
                                                                ->icon('heroicon-o-magnifying-glass')
                                                                ->color('primary')
                                                                ->extraAttributes([
                                                                    'class' => 'text-xs',
                                                                    'x-ref' => 'filtrarNombreBtn',
                                                                ])
                                                                ->action(function (FormGet $get, FormSet $set) {
                                                                    $valor = trim((string) ($get('filtro_nombre') ?? ''));
                                                                    $set('filtro_nombre', $valor);
                                                                    self::aplicarFiltrosYPaginar($get, $set);
                                                                })
                                                        )
                                                        ->dehydrated(false)
                                                        ->columnSpan(3),

                                                    TextInput::make('filtro_telefono')
                                                        ->label('Teléfono')
                                                        ->extraAttributes([
                                                            'class' =>
                                                                'text-xs text-gray-900 dark:text-gray-100 text-center font-medium ' .
                                                                'dark:bg-gray-900 dark:border-gray-700 dark:placeholder-gray-500',
                                                            'style' => 'text-align:center;',
                                                            'x-on:keydown.enter.prevent' => '$refs.filtrarTelefonoBtn && $refs.filtrarTelefonoBtn.click()',
                                                        ])
                                                        ->suffixAction(
                                                            Action::make('filtrarTelefono')
                                                                ->icon('heroicon-o-magnifying-glass')
                                                                ->color('primary')
                                                                ->extraAttributes([
                                                                    'class' => 'text-xs',
                                                                    'x-ref' => 'filtrarTelefonoBtn',
                                                                ])
                                                                ->action(function (FormGet $get, FormSet $set) {
                                                                    $valor = trim((string) ($get('filtro_telefono') ?? ''));
                                                                    $set('filtro_telefono', $valor);
                                                                    self::aplicarFiltrosYPaginar($get, $set);
                                                                })
                                                        )
                                                        ->dehydrated(false)
                                                        ->columnSpan(1),
                                                ]),

                                                // Repeater (tabla de resultados)
                                                Repeater::make('resultados_busqueda')
                                                    ->label('Resultados de búsqueda')
                                                    ->dehydrated(false)
                                                    ->addable(false)
                                                    ->deletable(false)
                                                    ->reorderable(false)
                                                    ->columns(1)
                                                    ->visible(function (FormGet $get) {
                                                        $token = $get('resultados_busqueda_token');
                                                        if (! $token) {
                                                            return false;
                                                        }
                                                        $base = Cache::get("facturacion_terceros_{$token}", []);
                                                        return is_array($base) && count($base) > 0;
                                                    })
                                                    ->extraAttributes([
                                                        'class' =>
                                                            'border rounded-md p-2 bg-gray-50 ' .
                                                            'dark:bg-gray-800 dark:border-gray-700 ' .
                                                            'max-h-[60vh] overflow-y-auto',
                                                    ])
                                                    ->schema([
                                                        Grid::make(6)
                                                            ->schema([
                                                                Actions::make([
                                                                    Action::make('seleccionar_tercero')
                                                                        ->label('Seleccionar')
                                                                        ->icon('heroicon-o-check-circle')
                                                                        ->color('success')
                                                                        ->extraAttributes([
                                                                            'class' => 'text-[11px]',
                                                                        ])
                                                                        ->action(function (FormGet $get, FormSet $set) {
                                                                            $cedulaFila = $get('cedula') ?? null;

                                                                            if (! $cedulaFila) {
                                                                                Notification::make()
                                                                                    ->title('Registro inválido')
                                                                                    ->body('El registro seleccionado no tiene número de identificación.')
                                                                                    ->warning()
                                                                                    ->send();
                                                                                return;
                                                                            }

                                                                            $token = $get('../../resultados_busqueda_token');
                                                                            $todos = $token
                                                                                ? Cache::get("facturacion_terceros_{$token}", [])
                                                                                : [];

                                                                            $registro = collect($todos)->first(function ($row) use ($cedulaFila) {
                                                                                return (string) ($row['cedula'] ?? '') === (string) $cedulaFila;
                                                                            });

                                                                            if (! $registro) {
                                                                                Notification::make()
                                                                                    ->title('Error')
                                                                                    ->body('No se encontraron los datos completos del tercero seleccionado.')
                                                                                    ->danger()
                                                                                    ->send();
                                                                                return;
                                                                            }

                                                                            $cedula   = $registro['cedula']              ?? null;
                                                                            $nombre   = $registro['nombre']              ?? null;
                                                                            $telefono = $registro['telefono']            ?? null;
                                                                            $direccion= $registro['direccion']           ?? null;
                                                                            $email    = $registro['email']               ?? null;
                                                                            $ciudad   = $registro['ciudad']              ?? null;
                                                                            $zona     = $registro['zona']                ?? null;
                                                                            $tipoId   = $registro['tipo_identificacion'] ?? null;

                                                                            $nombre1   = $registro['nombre1']      ?? null;
                                                                            $nombre2   = $registro['nombre2']      ?? null;
                                                                            $apellido1 = $registro['apellido1']    ?? null;
                                                                            $apellido2 = $registro['apellido2']    ?? null;
                                                                            $razon     = $registro['razon_social'] ?? null;

                                                                            $nombreComercial = $registro['nombre_comercial'] ?? null;
                                                                            $barrio          = $registro['barrio']           ?? null;

                                                                            $tipoIdUpper = strtoupper(trim((string) $tipoId));

                                                                            // Setear campos NORMALES (para lógica interna)
                                                                            $set('../../cedula', $cedula);
                                                                            $set('../../telefono', $telefono);
                                                                            $set('../../direccion', $direccion);
                                                                            $set('../../email_cliente', $email);
                                                                            $set('../../ciudad_cliente', $ciudad);
                                                                            $set('../../zona_cliente', $zona);
                                                                            $set('../../tipo_identificacion_cliente', $tipoId);
                                                                            $set('../../nombre_comercial_cliente', $nombreComercial);
                                                                            $set('../../barrio_cliente', $barrio);

                                                                            $set('../../cedula_cliente_real', $cedula);
                                                                            $set('../../telefono_real', $telefono);
                                                                            $set('../../direccion_real', $direccion);
                                                                            $set('../../email_cliente_real', $email);
                                                                            $set('../../ciudad_cliente_real', $ciudad);
                                                                            $set('../../zona_cliente_real', $zona);
                                                                            $set('../../tipo_identificacion_cliente_real', $tipoId);
                                                                            $set('../../nombre_comercial_cliente_real', $nombreComercial);
                                                                            $set('../../barrio_cliente_real', $barrio);

                                                                            if ($tipoIdUpper === 'NIT') {
                                                                                $set('../../nombre_cliente', $razon ?: $nombre);
                                                                                $set('../../nombre_cliente_real', $razon ?: $nombre); 
                                                                                $set('../../nombre1_cliente', null);
                                                                                $set('../../nombre2_cliente', null);
                                                                                $set('../../apellido1_cliente', null);
                                                                                $set('../../apellido2_cliente', null);
                                                                                // NUEVO: campos _real
                                                                                $set('../../nombre1_cliente_real', null);
                                                                                $set('../../nombre2_cliente_real', null);
                                                                                $set('../../apellido1_cliente_real', null);
                                                                                $set('../../apellido2_cliente_real', null);
                                                                            } else {
                                                                                $set('../../nombre_cliente', null);
                                                                                $set('../../nombre_cliente_real', null); 
                                                                                $set('../../nombre1_cliente', $nombre1);
                                                                                $set('../../nombre2_cliente', $nombre2);
                                                                                $set('../../apellido1_cliente', $apellido1);
                                                                                $set('../../apellido2_cliente', $apellido2);
                                                                                // NUEVO: campos _real
                                                                                $set('../../nombre1_cliente_real', $nombre1);
                                                                                $set('../../nombre2_cliente_real', $nombre2);
                                                                                $set('../../apellido1_cliente_real', $apellido1);
                                                                                $set('../../apellido2_cliente_real', $apellido2);
                                                                            }

                                                                            // 🔹 Campos necesarios para la validación de reservas
                                                                            $numeroIdent = $registro['codigoInterno'] ?? $registro['cedula'] ?? null;
                                                                            $tel1        = $registro['telefono1']     ?? null;
                                                                            $tel2        = $registro['telefono2']     ?? null;
                                                                            $celular     = $registro['celular']       ?? $telefono ?? null;

                                                                            $set('../../numero_identificacion_cliente', $numeroIdent);
                                                                            $set('../../telefono1_cliente', $tel1);
                                                                            $set('../../telefono2_cliente', $tel2);
                                                                            $set('../../celular_cliente',   $celular);

                                                                            Log::info('[SeleccionTercero] Campos cargados para reserva', [
                                                                                'numero_identificacion_cliente' => $numeroIdent,
                                                                                'telefono1_cliente'             => $tel1,
                                                                                'telefono2_cliente'             => $tel2,
                                                                                'celular_cliente'               => $celular,
                                                                            ]);

                                                                            $set('../../mostrar_modal_resultados', false);
                                                                            $set('../../mostrar_detalle_tercero', true);

                                                                            Notification::make()
                                                                                ->title('Tercero seleccionado')
                                                                                ->body('Los datos del cliente han sido cargados en el formulario.')
                                                                                ->success()
                                                                                ->send();
                                                                        })

                                                                ])
                                                                ->columnSpan(1),

                                                                TextInput::make('cedula')
                                                                    ->label('')
                                                                    ->disabled()
                                                                    ->extraInputAttributes([
                                                                        'class' =>
                                                                            'text-[11px] leading-tight whitespace-normal break-words ' .
                                                                            'text-center font-semibold text-gray-900 dark:text-gray-100',
                                                                        'style' =>
                                                                            'text-align:center !important;' .
                                                                            'opacity:1 !important;' .
                                                                            'background-color:transparent !important;' .
                                                                            '-webkit-text-fill-color:inherit !important;' .
                                                                            'color:inherit !important;',
                                                                    ])
                                                                    ->columnSpan(1),

                                                                TextInput::make('nombre')
                                                                    ->label('')
                                                                    ->disabled()
                                                                    ->extraInputAttributes([
                                                                        'class' =>
                                                                            'text-[11px] leading-tight whitespace-normal break-words ' .
                                                                            'text-center font-semibold text-gray-900 dark:text-gray-100',
                                                                        'style' =>
                                                                            'text-align:center !important;' .
                                                                            'opacity:1 !important;' .
                                                                            'background-color:transparent !important;' .
                                                                            '-webkit-text-fill-color:inherit !important;' .
                                                                            'color:inherit !important;',
                                                                    ])
                                                                    ->columnSpan(3),

                                                                TextInput::make('telefono')
                                                                    ->label('')
                                                                    ->disabled()
                                                                    ->extraInputAttributes([
                                                                        'class' =>
                                                                            'text-[11px] leading-tight whitespace-normal break-words ' .
                                                                            'text-center font-semibold text-gray-900 dark:text-gray-100',
                                                                        'style' =>
                                                                            'text-align:center !important;' .
                                                                            'opacity:1 !important;' .
                                                                            'background-color:transparent !important;' .
                                                                            '-webkit-text-fill-color:inherit !important;' .
                                                                            'color:inherit !important;',
                                                                    ])
                                                                    ->columnSpan(1),
                                                            ]),
                                                    ]),

                                                // Mensaje cuando NO hay resultados
                                                Textarea::make('mensaje_sin_resultados')
                                                    ->label('')
                                                    ->default('No se encontraron coincidencias.')
                                                    ->disabled()
                                                    ->visible(function (FormGet $get) {
                                                        $token = $get('resultados_busqueda_token');
                                                        if ($token) {
                                                            $base = Cache::get("facturacion_terceros_{$token}", []);
                                                            return is_array($base) && count($base) === 0;
                                                        }
                                                        return true;
                                                    })
                                                    ->extraAttributes([
                                                        'class' =>
                                                            'mt-6 text-center text-sm ' .
                                                            'text-gray-700 dark:text-gray-200 ' .
                                                            'bg-transparent border-none shadow-none',
                                                    ])
                                                    ->dehydrated(false),

                                                // Controles inferiores (registros por página + paginación)
                                                Grid::make(2)
                                                    ->visible(function (FormGet $get) {
                                                        $token = $get('resultados_busqueda_token');
                                                        if (! $token) {
                                                            return false;
                                                        }
                                                        $base = Cache::get("facturacion_terceros_{$token}", []);
                                                        return is_array($base) && count($base) > 0;
                                                    })
                                                    ->schema([
                                                        Select::make('per_page_resultados')
                                                            ->label('Registros')
                                                            ->options([
                                                                5  => '5',
                                                                10 => '10',
                                                                20 => '20',
                                                            ])
                                                            ->default(5)
                                                            ->reactive()
                                                            ->extraAttributes([
                                                                'class' =>
                                                                    'text-xs text-gray-800 dark:text-gray-100 ' .
                                                                    'dark:bg-gray-900 dark:border-gray-700 w-16',
                                                                'style' => 'max-width:4rem;',
                                                            ])
                                                            
                                                            ->afterStateUpdated(function ($state, FormGet $get, FormSet $set) {
                                                                $token = $get('resultados_busqueda_token');
                                                                if (! $token) {
                                                                    $set('resultados_busqueda', []);
                                                                    return;
                                                                }
                                                                $full    = Cache::get("facturacion_terceros_{$token}", []);
                                                                $page    = (int) ($get('pagina_resultados') ?? 1);
                                                                $perPage = in_array((int) $state, [5, 10, 20]) ? (int) $state : 5;

                                                                $total   = count($full);
                                                                $maxPage = max(1, (int) ceil($total / $perPage));

                                                                if ($page > $maxPage) {
                                                                    $page = $maxPage;
                                                                    $set('pagina_resultados', $page);
                                                                }

                                                                $offset = max(0, ($page - 1) * $perPage);
                                                                $slice  = array_slice($full, $offset, $perPage);

                                                                $set('resultados_busqueda', $slice);
                                                            }),

                                                        Actions::make([
                                                            Action::make('prev_page')
                                                                ->label('')
                                                                ->icon('heroicon-o-chevron-left')
                                                                ->color('secondary')
                                                                ->extraAttributes([
                                                                    'class' => 'px-2',
                                                                ])
                                                                ->action(function (FormGet $get, FormSet $set) {
                                                                    $token = $get('resultados_busqueda_token');
                                                                    if (! $token) {
                                                                        return;
                                                                    }

                                                                    $full    = Cache::get("facturacion_terceros_{$token}", []);
                                                                    $perPage = (int) ($get('per_page_resultados') ?? 5);
                                                                    $perPage = in_array($perPage, [5, 10, 20]) ? $perPage : 5;

                                                                    if (count($full) === 0) {
                                                                        Notification::make()
                                                                            ->title('Sin resultados')
                                                                            ->body('No hay registros para paginar.')
                                                                            ->warning()
                                                                            ->send();
                                                                        return;
                                                                    }

                                                                    $page    = (int) ($get('pagina_resultados') ?? 1);
                                                                    $maxPage = max(1, (int) ceil(count($full) / $perPage));

                                                                    if ($page <= 1) {
                                                                        Notification::make()
                                                                            ->title('Primera página')
                                                                            ->body('Ya se encuentra en la primera página.')
                                                                            ->info()
                                                                            ->send();
                                                                        $page = 1;
                                                                    } else {
                                                                        $page--;
                                                                    }

                                                                    $set('pagina_resultados', $page);

                                                                    $offset = max(0, ($page - 1) * $perPage);
                                                                    $slice  = array_slice($full, $offset, $perPage);

                                                                    $set('resultados_busqueda', $slice);
                                                                }),

                                                            Action::make('page_info')
                                                                ->visible(function (FormGet $get) {
                                                                    $token = $get('resultados_busqueda_token');
                                                                    if (! $token) {
                                                                        return '0/0';
                                                                    }
                                                                    $full    = Cache::get("facturacion_terceros_{$token}", []);
                                                                    $page    = (int) ($get('pagina_resultados') ?? 1);
                                                                    $perPage = (int) ($get('per_page_resultados') ?? 5);
                                                                    $perPage = in_array($perPage, [5, 10, 20]) ? $perPage : 5;

                                                                    $total   = count($full);
                                                                    $maxPage = max(1, (int) ceil($total / $perPage));

                                                                    return $page . '/' . $maxPage;
                                                                })
                                                                ->disabled()
                                                                ->extraAttributes([
                                                                    'class' =>
                                                                        'text-[11px] px-2 text-gray-800 dark:text-gray-100 font-semibold',
                                                                ]),

                                                            Action::make('next_page')
                                                                ->label('')
                                                                ->icon('heroicon-o-chevron-right')
                                                                ->color('secondary')
                                                                ->extraAttributes([
                                                                    'class' => 'px-2',
                                                                ])
                                                                ->action(function (FormGet $get, FormSet $set) {
                                                                    $token = $get('resultados_busqueda_token');
                                                                    if (! $token) {
                                                                        return;
                                                                    }

                                                                    $full    = Cache::get("facturacion_terceros_{$token}", []);
                                                                    $perPage = (int) ($get('per_page_resultados') ?? 5);
                                                                    $perPage = in_array($perPage, [5, 10, 20]) ? $perPage : 5;

                                                                    if (count($full) === 0) {
                                                                        Notification::make()
                                                                            ->title('Sin resultados')
                                                                            ->body('No hay registros para paginar.')
                                                                            ->warning()
                                                                            ->send();
                                                                        return;
                                                                    }

                                                                    $page    = (int) ($get('pagina_resultados') ?? 1);
                                                                    $maxPage = max(1, (int) ceil(count($full) / $perPage));

                                                                    if ($page >= $maxPage) {
                                                                        Notification::make()
                                                                            ->title('Última página')
                                                                            ->body('Ya se encuentra en la última página.')
                                                                            ->info()
                                                                            ->send();
                                                                    } else {
                                                                        $page++;
                                                                        $set('pagina_resultados', $page);

                                                                        $offset = max(0, ($page - 1) * $perPage);
                                                                        $slice  = array_slice($full, $offset, $perPage);

                                                                        $set('resultados_busqueda', $slice);
                                                                    }
                                                                }),
                                                        ])->alignment('center'),
                                                    ]),
                                            ])
                                            ->extraAttributes([
                                                'class' =>
                                                    'bg-white dark:bg-gray-900 ' .
                                                    'w-full mx-auto rounded-xl shadow-lg ' .
                                                    'p-4 sm:p-5 space-y-4 ' .
                                                    'border border-gray-200 dark:border-gray-700 ' .
                                                    'overflow-y-auto max-h-[70vh] ' .
                                                    'text-gray-900 dark:text-gray-100',
                                            ]),
                                        ])
                                        ->columnSpanFull(),
                                // ============================================


                                // =========================================================
                                // DATOS DEL CLIENTE SELECCIONADO (sección debajo)
                                // =========================================================
                                Section::make('Datos del cliente seleccionado')
                                    ->visible(fn (FormGet $get) => filled($get('cedula_cliente_real')))
                                    ->schema([
                                        TextInput::make('tipo_identificacion_cliente')
                                            ->label('Tipo identificación')
                                            ->disabled()
                                            ->formatStateUsing(fn (FormGet $get) => $get('tipo_identificacion_cliente_real'))
                                            ->maxLength(100),

                                        TextInput::make('cedula')
                                            ->label('Número de identificación')
                                            ->maxLength(30)
                                            ->disabled()
                                            ->required()
                                            ->formatStateUsing(fn (FormGet $get) => $get('cedula_cliente_real'))

                                            ->validationMessages([
                                                'required' => 'Debe seleccionar un tercero (el número de identificación no puede estar vacío).',
                                            ])
                                            ->dehydrated(false)
                                            ->live()
                                            ->afterStateUpdated(function ($state) {
                                                Session::put('cedula', $state);
                                            }),




                                        TextInput::make('nombre_cliente')
                                            ->label(function (FormGet $get) {
                                                $tipo = strtoupper((string) ($get('tipo_identificacion_cliente_real') ?? ''));
                                                return $tipo === 'NIT'
                                                    ? 'Razón social'
                                                    : 'Nombre del cliente';
                                            })
                                            ->maxLength(255)
                                            ->columnSpan(3)
                                            ->visible(function (FormGet $get) {
                                                $tipo = strtoupper((string) ($get('tipo_identificacion_cliente_real') ?? ''));
                                                return $tipo === 'NIT';
                                            })
                                            ->formatStateUsing(fn (FormGet $get) => $get('nombre_cliente_real'))
                                            ->disabled(),

                                        Grid::make(4)
                                            ->visible(function (FormGet $get) {
                                                $tipo = strtoupper((string) ($get('tipo_identificacion_cliente') ?? ''));
                                                return $tipo !== 'NIT';
                                            })
                                            ->schema([
                                                TextInput::make('nombre1_cliente')
                                                    ->label('Primer nombre')
                                                    ->maxLength(100)
                                                    ->formatStateUsing(fn (FormGet $get) => $get('nombre1_cliente_real'))
                                                    ->disabled(),

                                                TextInput::make('nombre2_cliente')
                                                    ->label('Segundo nombre')
                                                    ->maxLength(100)
                                                    ->formatStateUsing(fn (FormGet $get) => $get('nombre2_cliente_real'))
                                                    ->disabled(),

                                                TextInput::make('apellido1_cliente')
                                                    ->label('Primer apellido')
                                                    ->maxLength(100)
                                                    ->formatStateUsing(fn (FormGet $get) => $get('apellido1_cliente_real'))
                                                    ->disabled(),

                                                TextInput::make('apellido2_cliente')
                                                    ->label('Segundo apellido')
                                                    ->maxLength(100)
                                                    ->formatStateUsing(fn (FormGet $get) => $get('apellido2_cliente_real'))
                                                    ->disabled(),
                                            ])
                                            ->columnSpanFull(),

                                        TextInput::make('telefono')
                                            ->label('Teléfono')
                                            ->tel()
                                            ->maxLength(30)
                                            ->formatStateUsing(fn (FormGet $get) => $get('telefono_real'))
                                            ->disabled(),

                                        TextInput::make('email_cliente')
                                            ->label('Correo electrónico')
                                            ->email()
                                            ->maxLength(255)
                                            ->columnSpan(2)
                                            ->formatStateUsing(fn (FormGet $get) => $get('email_cliente_real'))
                                            ->disabled(),

                                        TextInput::make('ciudad_cliente')
                                            ->label('Ciudad')
                                            ->maxLength(255)
                                            ->formatStateUsing(fn (FormGet $get) => $get('ciudad_cliente_real'))
                                            ->disabled(),

                                        TextInput::make('zona_cliente')
                                            ->label('Zona')
                                            ->maxLength(255)
                                            ->formatStateUsing(fn (FormGet $get) => $get('zona_cliente_real'))
                                            ->disabled(),

                                        TextInput::make('nombre_comercial_cliente')
                                            ->label('Nombre comercial')
                                            ->maxLength(255)
                                            ->columnSpan(2)
                                            ->formatStateUsing(fn (FormGet $get) => $get('nombre_comercial_cliente_real'))
                                            ->disabled(),

                                        TextInput::make('barrio_cliente')
                                            ->label('Barrio')
                                            ->maxLength(255)
                                            ->formatStateUsing(fn (FormGet $get) => $get('barrio_cliente_real'))
                                            ->disabled(),

                                        Textarea::make('direccion')
                                            ->label('Dirección')
                                            ->rows(2)
                                            ->columnSpanFull()
                                            ->formatStateUsing(fn (FormGet $get) => $get('direccion_real'))
                                            ->disabled(),
                                    ])
                                    ->columns(4),


                            ]),

                        Forms\Components\Hidden::make('id_cliente')
                            ->required(),
                    ])
                    ->columns(1)
                    ->collapsible(),

                // ============================================
                // SECCIÓN 3: SELECCIÓN DE PRODUCTOS  Y VARIANTES
                // ============================================
                Forms\Components\Section::make('Selección de Productos')
                    ->description('Busca y selecciona los productos a consignar. Elige la bodega correcta.')
                    ->icon('heroicon-o-shopping-cart')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('Productos')
                            ->minItems(1)
                            ->addActionLabel('+ Agregar otro producto')
                            ->dehydrated(true)
                            ->reorderable(false)
                            ->collapsible()
                            ->defaultItems(1)
                            ->schema([
                                // Checkbox para seleccionar producto para eliminar
                                Forms\Components\Checkbox::make('seleccionado')
                                    ->label('Seleccionar para eliminar')
                                    ->inline()
                                    ->extraAttributes([
                                        'class' => 'pt-6 flex justify-end custom-checkbox-wrapper',
                                    ])
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 1,
                                    ]),

                                Forms\Components\Grid::make(12)
                                    ->schema([
                                        // SELECT DE BODEGA
                                        Forms\Components\Select::make('bodega_id')
                                            ->label('Bodega')
                                            ->placeholder('Seleccionar bodega')
                                            ->required()
                                            ->options(function (Forms\Get $get) {
                                                $user = auth()->user();
                                                $cedula = $user->cedula ?? null;

                                                $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                                
                                                if (!$asesor) {
                                                    return [];
                                                }
                                                
                                                $AaPrin = \App\Models\AaPrin::where('ID_Asesor', $asesor->ID_Asesor)->first();

                                                $columna = 'ID_Sede';
                                                $valores = $AaPrin->ID_Sede;

                                                if ($AaPrin->ID_Sede == null || $AaPrin->ID_Sede == '') {
                                                    $socio = \App\Models\SocioDistritec::where('Cedula', $cedula)->first();
                                                    $ID_sede = \App\Models\Sede::where('ID_Socio', $socio->ID_Socio)->first();
                                                    $valores = $ID_sede?->ID_Sede;
                                                }

                                                $ID_Sede = \App\Models\Sede::where($columna, $valores)->first();
                                                $codigoSucursal = $ID_Sede->Codigo_de_sucursal ?? null;

                                                if (!$codigoSucursal) {
                                                    return \App\Models\ZBodegaFacturacion::query()
                                                        ->where('ID_Sede', 49)
                                                        ->orderBy('Nombre_Bodega')
                                                        ->pluck('Nombre_Bodega', 'Cod_Bog');
                                                }

                                                $idSede = \App\Models\Sede::where('Codigo_de_sucursal', $codigoSucursal)
                                                    ->value('ID_Sede');

                                                if (!$idSede) {
                                                    return \App\Models\ZBodegaFacturacion::query()
                                                        ->where('ID_Sede', 49)
                                                        ->orderBy('Nombre_Bodega')
                                                        ->pluck('Nombre_Bodega', 'Cod_Bog');
                                                }

                                                return \App\Models\ZBodegaFacturacion::query()
                                                    ->whereIn('ID_Sede', [$idSede, 49])
                                                    ->orderBy('Nombre_Bodega')
                                                    ->pluck('Nombre_Bodega', 'Cod_Bog');
                                            })
                                            ->live()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                $set('codigo_bodega', $state);
                                                $set('codigo_bodega_display', $state); 

                                                $bodega = \App\Models\ZBodegaFacturacion::where('Cod_Bog', $state)->first();
                                                $set('nombre_bodega', $bodega?->Nombre_Bodega);

                                                // Limpiar campos de producto
                                                $set('codigo_producto', null);
                                                $set('nombre_producto', null);
                                                $set('marca', null);
                                                $set('cantidad', null);
                                                
                                                // Resetear variantes
                                                $set('variantes', []);
                                                $set('variantes_json', null);
                                                $set('variantes_configuradas', false);
                                                $set('tiene_variantes_disponibles', false);
                                                
                                                // Resetear checkbox
                                                $set('seleccionado', false);
                                            })
                                            ->extraAttributes([
                                                'x-data' => '{}',
                                                'x-on:change' => <<<'JS'
                                                    $nextTick(() => {
                                                        const intentarClick = (intentos = 0) => {
                                                            const buttons = document.querySelectorAll('button[wire\\:click]');
                                                            let targetButton = null;

                                                            buttons.forEach(btn => {
                                                                const wireClick = btn.getAttribute('wire:click');
                                                                if (wireClick && wireClick.includes('buscarProducto')) {
                                                                    targetButton = btn;
                                                                }
                                                            });

                                                            if (targetButton) {
                                                                console.log('✅ Botón encontrado');
                                                                targetButton.click();
                                                            } else if (intentos < 10) {
                                                                setTimeout(() => intentarClick(intentos + 1), 200);
                                                            }
                                                        };

                                                        setTimeout(() => intentarClick(0), 300);
                                                    })
                                                JS
                                            ])
                                            ->searchable()
                                            ->columnSpan(6),

                                        Forms\Components\Hidden::make('item_key')
                                            ->default(fn () => uniqid()),

                                        Forms\Components\Hidden::make('codigo_bodega'),
                                        Forms\Components\Hidden::make('nombre_bodega'),
                                        
                                        // Hidden para variantes
                                        Forms\Components\Hidden::make('variantes_json')
                                            ->default(null)
                                            ->dehydrated(true),

                                        // CAMPO CÓDIGO BODEGA CON BOTÓN MODAL
                                        Forms\Components\TextInput::make('codigo_bodega_display')
                                            ->label('Código Bodega')
                                            ->readOnly() 
                                            ->dehydrated(false)
                                            ->extraInputAttributes([
                                                'class' => 'cursor-not-allowed'
                                            ])
                                            ->columnSpan(6)
                                            ->suffixAction(
                                                Forms\Components\Actions\Action::make('buscarProducto')
                                                    ->icon('heroicon-o-cursor-arrow-ripple')
                                                    ->iconButton()
                                                    ->tooltip('Haz clic para buscar productos')
                                                    ->extraAttributes([
                                                        'style' => '
                                                            background-color: transparent !important;
                                                            border: 2px solid #82cc0e !important;
                                                            box-shadow: 0 0 10px rgba(130, 204, 14, 0.3) !important;
                                                            color: #82cc0e !important;
                                                        ',
                                                        'class' => '
                                                            !text-[#82cc0e]
                                                            [&_svg]:!text-[#82cc0e]
                                                            animate-pulse
                                                            hover:animate-none
                                                            hover:!bg-[#82cc0e]
                                                            hover:!text-white
                                                            hover:scale-110
                                                            transition-all
                                                            duration-300
                                                            cursor-pointer
                                                            rounded-full
                                                            !w-12
                                                            !h-12
                                                        ',
                                                    ])
                                                    ->modalHeading('')
                                                    ->modalWidth('7xl')
                                                    ->disabled(fn (Forms\Get $get) => empty($get('codigo_bodega')))
                                                    ->fillForm(fn (Forms\Get $get): array => [
                                                        'codigo_bodega_modal' => $get('codigo_bodega'),
                                                    ])
                                                    ->form([
                                                        Forms\Components\Hidden::make('codigo_bodega_modal'),

                                                        Forms\Components\Section::make('')
                                                            ->schema([
                                                                Forms\Components\Placeholder::make('tabla_productos')
                                                                    ->label('')
                                                                    ->content(function (Forms\Get $get) {
                                                                        $codigoBodega = $get('codigo_bodega_modal');
                                                                        $cedulaCliente = $get('../../cedula_cliente');

                                                                        /*if (!$codigoBodega || !$cedulaCliente) {
                                                                            return new HtmlString('<div class="text-center p-4">⚠️ Datos incompletos</div>');
                                                                        }*/

                                                                        $items = $get('items_modal') ?? [];
                                                                        $array_codigos = array_column($items, 'codigo_producto');
                                                                        $array_bodega = array_column($items, 'codigo_bodega');

                                                                        try {
                                                                            return new HtmlString(
                                                                                \App\Filament\Resources\FacturacionResource\Forms\ModalProductosBodega::generarTablaProductos(
                                                                                    $codigoBodega,
                                                                                    $get('codigo_producto_modal'),
                                                                                    $get('busqueda_nombre'),
                                                                                    $get('busqueda_marca'),
                                                                                    $cedulaCliente,
                                                                                    $array_codigos,
                                                                                    $array_bodega,
                                                                                )
                                                                            );
                                                                        } catch (\Exception $e) {
                                                                            return new HtmlString('<div class="text-center p-4 bg-red-50 text-red-700 rounded-lg">Error: ' . $e->getMessage() . '</div>');
                                                                        }
                                                                    }),
                                                            ]),

                                                        Forms\Components\Hidden::make('productos_seleccionados')
                                                            ->extraAttributes(['data-role' => 'productos-seleccionados']),
                                                        Forms\Components\Hidden::make('codigo_producto_modal'),
                                                        Forms\Components\Hidden::make('busqueda_nombre'),
                                                        Forms\Components\Hidden::make('busqueda_marca'),
                                                        Forms\Components\Hidden::make('items_modal')->dehydrated(false),
                                                    ])
                                                    ->action(function (array $data, Forms\Get $get, Forms\Set $set) {
                                                        $productosJson = $data['productos_seleccionados'] ?? '[]';
                                                        $productos = json_decode($productosJson, true);

                                                        if (!is_array($productos) || count($productos) === 0) {
                                                            Notification::make()->title('Debes seleccionar al menos un producto')->danger()->send();
                                                            return;
                                                        }

                                                        $totalSeleccionados = count($productos);
                                                        $items = $get('../../items') ?? [];
                                                        $bodega = $get('bodega_id');
                                                        $codigoBodega = $get('codigo_bodega');
                                                        $currentKey = $get('item_key');

                                                        $currentIndex = null;
                                                        foreach ($items as $idx => $item) {
                                                            if (($item['item_key'] ?? null) === $currentKey) {
                                                                $currentIndex = $idx;
                                                                break;
                                                            }
                                                        }

                                                        $first = array_shift($productos);

                                                        if ($first && $currentIndex !== null) {
                                                            $items[$currentIndex]['bodega_id'] = $bodega;
                                                            $items[$currentIndex]['codigo_bodega'] = $codigoBodega;
                                                            $items[$currentIndex]['codigo_producto'] = $first['codigo'] ?? null;
                                                            $items[$currentIndex]['nombre_producto'] = $first['nombre'] ?? '';
                                                            $items[$currentIndex]['marca'] = $first['marca'] ?? '';
                                                            $items[$currentIndex]['producto_id'] = $first['codigo'] ?? null;
                                                            $items[$currentIndex]['cantidad'] = 1;
                                                            
                                                            // Resetear variantes
                                                            $items[$currentIndex]['variantes'] = [];
                                                            $items[$currentIndex]['variantes_json'] = null;
                                                            $items[$currentIndex]['variantes_configuradas'] = false;
                                                            $items[$currentIndex]['tiene_variantes_disponibles'] = false;
                                                        } elseif ($first) {
                                                            $items[] = [
                                                                'item_key' => uniqid(),
                                                                'bodega_id' => $bodega,
                                                                'codigo_bodega' => $codigoBodega,
                                                                'codigo_producto' => $first['codigo'] ?? null,
                                                                'nombre_producto' => $first['nombre'] ?? '',
                                                                'marca' => $first['marca'] ?? '',
                                                                'producto_id' => $first['codigo'] ?? null,
                                                                'cantidad' => 1,
                                                                'variantes' => [],
                                                                'variantes_json' => null,
                                                                'variantes_configuradas' => false,
                                                                'tiene_variantes_disponibles' => false,
                                                            ];
                                                        }

                                                        foreach ($productos as $producto) {
                                                            if (empty($producto['codigo'])) continue;

                                                            $items[] = [
                                                                'item_key' => uniqid(),
                                                                'bodega_id' => $bodega,
                                                                'codigo_bodega' => $codigoBodega,
                                                                'codigo_producto' => $producto['codigo'],
                                                                'nombre_producto' => $producto['nombre'] ?? '',
                                                                'marca' => $producto['marca'] ?? '',
                                                                'producto_id' => $producto['codigo'],
                                                                'cantidad' => 1,
                                                                'variantes' => [],
                                                                'variantes_json' => null,
                                                                'variantes_configuradas' => false,
                                                                'tiene_variantes_disponibles' => false,
                                                            ];
                                                        }

                                                        $set('../../items', $items);
                                                        Notification::make()->title('Se agregaron ' . $totalSeleccionados . ' producto(s)')->success()->send();
                                                    })
                                                    ->fillForm(fn (Forms\Get $get): array => [
                                                        'codigo_bodega_modal' => $get('codigo_bodega'),
                                                        'items_modal' => $get('../../items') ?? [],
                                                    ])
                                                    ->closeModalByClickingAway(false)
                                                    ->modalSubmitAction(fn ($action) => $action->label('Aceptar')->extraAttributes(['class' => 'fi-hidden-submit hidden']))
                                                    ->modalCancelAction(fn ($action) => $action->label('Cancelar')->extraAttributes(['class' => 'fi-hidden-cancel hidden']))
                                            ),
                                    ])
                                    ->columnSpan(11),

                                // Información del producto
                                Forms\Components\Grid::make(12)
                                    ->schema([
                                        Forms\Components\TextInput::make('codigo_producto')
                                            ->label('Código del Producto')
                                            ->readOnly()
                                            ->required()
                                            ->columnSpan(3)
                                            ->visible(fn (Forms\Get $get) => !empty($get('codigo_producto'))),

                                        Forms\Components\TextInput::make('nombre_producto')
                                            ->label('Nombre del Producto')
                                            ->readOnly()
                                            ->columnSpan(5)
                                            ->visible(fn (Forms\Get $get) => !empty($get('nombre_producto'))),

                                        Forms\Components\TextInput::make('marca')
                                            ->label('Marca del Producto')
                                            ->readOnly()
                                            ->columnSpan(2)
                                            ->visible(fn (Forms\Get $get) => !empty($get('marca'))),
                                    ])
                                    ->visible(fn (Forms\Get $get) => !empty($get('codigo_producto')))
                                    ->columnSpan(12),

                                // Hidden para variantes
                                Forms\Components\Hidden::make('variantes_configuradas')
                                    ->default(false)
                                    ->dehydrated(true),

                                Forms\Components\Hidden::make('tiene_variantes_disponibles')
                                    ->default(false)
                                    ->dehydrated(true),

                                // GRID CANTIDAD Y VARIANTES
                                Forms\Components\Grid::make(12)
                                    ->schema([
                                        Forms\Components\TextInput::make('cantidad')
                                            ->label('Cantidad')
                                            ->numeric()
                                            ->minValue(1)
                                            ->required()
                                            ->default(1)
                                            ->placeholder('Ingrese la cantidad')
                                            ->live()
                                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                                $cantidad = (int) $state;

                                                if ($cantidad <= 0) {
                                                    $set('cantidad', null);
                                                    $set('variantes', []);
                                                    $set('variantes_json', null);
                                                    $set('variantes_configuradas', false);
                                                    $set('tiene_variantes_disponibles', false);
                                                    return;
                                                }

                                                $codigoBodega = $get('codigo_bodega') ?? '';
                                                $codigoProducto = $get('codigo_producto') ?? '';

                                                if (!empty($codigoBodega) && !empty($codigoProducto)) {
                                                    try {
                                                        $controller = new \App\Http\Controllers\Api\ProductosController(
                                                            new \App\Services\ConsignacionService()
                                                        );

                                                        $request = new \Illuminate\Http\Request([
                                                            'codigoProducto' => $codigoProducto,
                                                            'codigo_bodega' => $codigoBodega,
                                                        ]);

                                                        $response = $controller->productoDisponibles($request);
                                                        $totalDisponibles = is_array($response) ? count($response) : 0;

                                                        if ($cantidad > $totalDisponibles) {
                                                            $cantidadAjustada = $totalDisponibles > 0 ? $totalDisponibles : null;
                                                            $set('cantidad', $cantidadAjustada);

                                                            Notification::make()
                                                                ->title('Cantidad excede disponibilidad')
                                                                ->body("Solo hay {$totalDisponibles} unidad(es) disponible(s) en esta bodega.")
                                                                ->warning()
                                                                ->send();

                                                            $cantidad = $totalDisponibles;
                                                        }

                                                        $htmlVariantes = \App\Filament\Resources\FacturacionResource\Forms\ModalProductosVariante::generarListaProductosVariantes(
                                                            $cantidad,
                                                            $codigoProducto,
                                                            $codigoBodega
                                                        );

                                                        $hayVariantes = !str_contains($htmlVariantes, 'Sin variantes disponibles');
                                                        $set('tiene_variantes_disponibles', $hayVariantes);

                                                        if (!$hayVariantes) {
                                                            $set('variantes', []);
                                                            $set('variantes_json', null);
                                                            $set('variantes_configuradas', false);
                                                        }

                                                    } catch (\Exception $e) {
                                                        \Log::error('Error validando disponibilidad: ' . $e->getMessage());
                                                        $set('tiene_variantes_disponibles', false);
                                                    }
                                                }

                                                $variantesActuales = $get('variantes') ?? [];
                                                if (!empty($variantesActuales) && count($variantesActuales) !== $cantidad) {
                                                    $set('variantes', []);
                                                    $set('variantes_json', null);
                                                    $set('variantes_configuradas', false);

                                                    Notification::make()
                                                        ->title('Variantes reiniciadas')
                                                        ->body('Debes reconfigurar las variantes según la nueva cantidad')
                                                        ->warning()
                                                        ->send();
                                                }
                                            })
                                            ->suffix('un.')
                                            ->columnSpan(3)
                                            ->visible(fn (Forms\Get $get) => !empty($get('codigo_producto'))),

                                        // Indicador de estado de variantes
                                        Forms\Components\Placeholder::make('estado_variantes')
                                            ->label('Variantes')
                                            ->content(function (Forms\Get $get) {
                                                $cantidad = (int) ($get('cantidad') ?? 0);
                                                $variantes = $get('variantes') ?? [];
                                                $configuradas = $get('variantes_configuradas') ?? false;
                                                $tieneVariantesDisponibles = $get('tiene_variantes_disponibles') ?? false;

                                                if ($cantidad === 0) {
                                                    return new HtmlString('
                                                        <div class="fi-input-wrapper flex items-center gap-2 rounded-lg shadow-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            <span class="text-sm text-gray-500">Ingresa cantidad</span>
                                                        </div>
                                                    ');
                                                }

                                                if (!$tieneVariantesDisponibles) {
                                                    return new HtmlString('
                                                        <div class="fi-input-wrapper flex items-center gap-2 rounded-lg shadow-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            <span class="text-sm text-red-500"><strong>Sin variantes</strong></span>
                                                        </div>
                                                    ');
                                                }

                                                if (!$configuradas) {
                                                    return new HtmlString('
                                                        <div class="fi-input-wrapper flex items-center gap-2 rounded-lg shadow-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                            </svg>
                                                            <span class="text-sm text-yellow-500"><strong>Requerido: ' . $cantidad . ' variante(s)</strong></span>
                                                        </div>
                                                    ');
                                                }

                                                return new HtmlString('
                                                    <div class="fi-input-wrapper flex items-center gap-2 rounded-lg shadow-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <span class="text-sm text-green-500"><strong>' . count($variantes) . '</strong> variante(s) configuradas</span>
                                                    </div>
                                                ');
                                            })
                                            ->columnSpan(6)
                                            ->visible(fn (Forms\Get $get) => !empty($get('codigo_producto'))),

                                        // Botón para configurar variantes
                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('configurarVariantes')
                                                ->label(fn (Forms\Get $get) => ($get('variantes_configuradas') ? 'Modificar Variantes' : 'Configurar Variantes'))
                                                ->icon(fn (Forms\Get $get) => ($get('variantes_configuradas') ? 'heroicon-o-pencil-square' : 'heroicon-o-plus-circle'))
                                                ->color(fn (Forms\Get $get) => (int) ($get('cantidad') ?? 0) === 0 ? 'gray' : (($get('variantes_configuradas') ? 'gray' : 'primary')))
                                                ->disabled(fn (Forms\Get $get) => (int) ($get('cantidad') ?? 0) === 0 || !$get('tiene_variantes_disponibles'))
                                                ->modalWidth('5xl')
                                                ->form([
                                                    Forms\Components\Hidden::make('cantidad_requerida'),
                                                    Forms\Components\Hidden::make('codigo_bodega_requerido'),
                                                    Forms\Components\Hidden::make('codigo_producto_requerido'),

                                                    Forms\Components\Section::make('')
                                                        ->schema([
                                                            Forms\Components\Placeholder::make('selector_variantes')
                                                                ->label('')
                                                                ->content(function (Forms\Get $get) {
                                                                    $cantidad = (int) ($get('cantidad_requerida') ?? 0);
                                                                    $codigoBodega = (string) ($get('codigo_bodega_requerido') ?? '');
                                                                    $codigoProducto = (string) ($get('codigo_producto_requerido') ?? '');

                                                                    return new HtmlString(
                                                                        \App\Filament\Resources\FacturacionResource\Forms\ModalProductosVariante::generarListaProductosVariantes(
                                                                            $cantidad,
                                                                            $codigoProducto,
                                                                            $codigoBodega
                                                                        )
                                                                    );
                                                                }),
                                                        ]),

                                                    Forms\Components\Hidden::make('variantes_seleccionadas')
                                                        ->extraAttributes(['data-role' => 'variantes-seleccionadas']),
                                                ])
                                                ->fillForm(fn (Forms\Get $get): array => [
                                                    'cantidad_requerida' => (int) ($get('cantidad') ?? 0),
                                                    'codigo_bodega_requerido' => (string) ($get('codigo_bodega') ?? ''),
                                                    'codigo_producto_requerido' => (string) ($get('codigo_producto') ?? ''),
                                                ])
                                                ->action(function (array $data, Forms\Get $get, Forms\Set $set) {
                                                    $variantesJson = $data['variantes_seleccionadas'] ?? '[]';
                                                    $variantes = json_decode($variantesJson, true);

                                                    if (!is_array($variantes) || count($variantes) === 0) {
                                                        Notification::make()->title('Debes seleccionar al menos una variante')->danger()->send();
                                                        return;
                                                    }

                                                    $cantidadRequerida = (int) ($data['cantidad_requerida'] ?? 0);

                                                    if (count($variantes) !== $cantidadRequerida) {
                                                        Notification::make()
                                                            ->title('Selección incorrecta')
                                                            ->body("Debes seleccionar EXACTAMENTE {$cantidadRequerida} variante(s). Tienes " . count($variantes))
                                                            ->danger()
                                                            ->send();
                                                        return;
                                                    }

                                                    $variantesMapeadas = [];
                                                    foreach ($variantes as $index => $variante) {
                                                        $variantesMapeadas[] = [
                                                            'numero' => $index + 1,
                                                            'codigo' => $variante['codigo'] ?? '',
                                                            'descripcion' => $variante['descripcion'] ?? '',
                                                            'marca' => $variante['marca'] ?? '',
                                                        ];
                                                    }

                                                    $set('variantes', $variantesMapeadas);
                                                    $set('variantes_configuradas', true);
                                                    $set('variantes_json', json_encode($variantesMapeadas, JSON_UNESCAPED_UNICODE));

                                                    Notification::make()
                                                        ->title('Variantes configuradas exitosamente')
                                                        ->body('Se configuraron ' . count($variantesMapeadas) . ' variante(s)')
                                                        ->success()
                                                        ->send();
                                                })
                                                ->closeModalByClickingAway(false)
                                                ->modalSubmitAction(fn ($action) => $action->label('Aceptar')->extraAttributes(['class' => 'fi-hidden-submit hidden']))
                                                ->modalCancelAction(fn ($action) => $action->label('Cancelar')->extraAttributes(['class' => 'fi-hidden-cancel hidden'])),
                                        ])
                                        ->columnSpan(3)
                                        ->visible(fn (Forms\Get $get) => !empty($get('codigo_producto'))),

                                        // Repeater oculto para guardar variantes
                                        Forms\Components\Repeater::make('variantes')
                                            ->schema([
                                                Forms\Components\Hidden::make('numero')->dehydrated(true),
                                                Forms\Components\Hidden::make('codigo')->dehydrated(true),
                                                Forms\Components\Hidden::make('descripcion')->dehydrated(true),
                                                Forms\Components\Hidden::make('marca')->dehydrated(true),
                                            ])
                                            ->defaultItems(0)
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->dehydrated(true)
                                            ->dehydratedWhenHidden()
                                            ->hidden(true)
                                            ->columnSpan(12),
                                    ])
                                    ->visible(fn (Forms\Get $get) => !empty($get('codigo_producto')))
                                    ->columnSpan(12),

                                Forms\Components\Hidden::make('producto_id'),
                            ]),

                        // BOTÓN PARA ELIMINAR SELECCIONADOS
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('eliminarSeleccionados')
                                ->label('Eliminar seleccionados')
                                ->color('danger')
                                ->icon('heroicon-o-trash')
                                ->requiresConfirmation()
                                ->action(function (Forms\Get $get, Forms\Set $set) {
                                    $items = $get('items') ?? [];
                                    $itemsFiltrados = array_values(array_filter($items, fn ($item) => empty($item['seleccionado'])));
                                    $cantidadEliminados = count($items) - count($itemsFiltrados);
                                    $set('items', $itemsFiltrados);
                                    Notification::make()->title('Se eliminaron ' . $cantidadEliminados . ' producto(s)')->success()->send();
                                }),
                        ])
                        ->alignment('right'),

                        // Fecha global
                        Forms\Components\DateTimePicker::make('fecha')
                            ->label('Fecha de Consignación')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->extraAttributes([
                        'class' => 'bg-white dark:bg-gray-900 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('producto_id')->label('Producto')->searchable(),
                Tables\Columns\TextColumn::make('cantidad')->label('Cantidad'),
                Tables\Columns\TextColumn::make('fecha')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('fecha', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsignacions::route('/'),
            'create' => Pages\CreateConsignacion::route('/create'),
            'view' => Pages\ViewConsignacion::route('/{record}'),
            'edit' => Pages\EditConsignacion::route('/{record}/edit'),
        ];
    }
}