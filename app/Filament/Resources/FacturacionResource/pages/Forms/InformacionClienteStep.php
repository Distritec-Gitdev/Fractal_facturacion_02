<?php

namespace App\Filament\Resources\FacturacionResource\Forms;

use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Para crear tercero en el mismo step
use App\Services\TercerosApiService;
use App\Models\ZDepartamentos;
use App\Models\ZMunicipios;
use App\Models\TipoDocumento;
use Illuminate\Support\Carbon;

// Ventas cantadas
use App\Models\Reserva_venta;
use App\Models\AaPrin;
use App\Models\InfTrab;
use Filament\Support\Exceptions\Halt;




class InformacionClienteStep
{
    public static function make(): Step
    {
        return Step::make('Información del cliente')
            ->icon('heroicon-o-identification') //  Datos del cliente
            ->schema([
                // =========================================================
                // BARRA DE BÚSQUEDA PRINCIPAL + CREAR TERCERO
                // =========================================================
                 Grid::make([
                            'default' => 8,   // 8 columnas base
                            'md'      => 8,   
                            'lg'      => 8,
                        ])
                    ->schema([
                        Hidden::make('dummy_left')->dehydrated(false),

                        TextInput::make('busqueda_tercero')
                            ->label('Buscar tercero (cédula o nombre)Ingrese al menos 2 caracteres ')
                           //->helperText('Ingrese al menos 2 caracteres (cédula o nombre) y presione "Buscar".')
                            ->extraAttributes([
                                'class' => 'w-full text-sm md:text-base',
                                // ENTER aquí dispara SOLO la lupa principal
                                'x-on:keydown.enter.prevent' => '$refs.buscarTerceroBtn && $refs.buscarTerceroBtn.click()',
                            ])
                            ->live(false)
                            ->afterStateHydrated(function ($state, Set $set) {
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
                                    ->action(function (Get $get, Set $set) {
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
                                            $response = Http::acceptJson()
                                                ->post(route('api.terceros.buscar'), [
                                                    'query' => $query,
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
                                            Log::info('[Facturacion] buscarTercero - respuesta', ['body' => $body]);

                                            if (! ($body['success'] ?? false)) {
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

                                            if (! is_array($data)) {
                                                $data = [$data];
                                            }

                                            // ============================
                                            // MAPEO UNIFORME de datos
                                            // ============================
                                            $clientesOriginales = collect($data)->map(function ($item) {
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
                                            })->toArray();

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
                                            $token = (string) Str::uuid();
                                            Cache::put(
                                                "facturacion_terceros_{$token}",
                                                $clientesOriginales,
                                                now()->addMinutes(5)
                                            );

                                            $set('resultados_busqueda_token', $token);

                                            // Paginación inicial
                                            $perPage = (int) ($get('per_page_resultados') ?? 5);
                                            if (! in_array($perPage, [5, 10, 20])) {
                                                $perPage = 5;
                                            }

                                            $set('pagina_resultados', 1);
                                            $slice = array_slice($clientesOriginales, 0, $perPage);
                                            $set('resultados_busqueda', $slice);

                                            // Mostrar modal inline de resultados
                                            $set('mostrar_modal_resultados', true);
                                            $set('mostrar_modal_crear_tercero', false);
                                            $set('mostrar_detalle_tercero', false);

                                            Notification::make()
                                                ->title('Resultados cargados')
                                                ->body('Se encontraron ' . $totalEncontrados . ' coincidencias.')
                                                ->success()
                                                ->send();
                                        } catch (\Throwable $e) {
                                            Log::error('[Facturacion] Error en buscarTercero', [
                                                'exception' => $e,
                                                'message'   => $e->getMessage(),
                                            ]);

                                            Notification::make()
                                                ->title('Error al consultar terceros')
                                                ->body('Ocurrió un error al comunicarse con el servicio. Intente nuevamente.')
                                                ->danger()
                                                ->send();
                                        }
                                    }),

                                ])

                                 ->columnSpan(5),   // ← ocupa 9 de 12 columnas (ajustable)

                                // ⬇️ NUEVO: botón Crear tercero separado, alineado a la derecha
                                \Filament\Forms\Components\Actions::make([

                                // ➕ Crear tercero
                                Action::make('abrirCrearTercero')
                                    ->label('Crear tercero')
                                    ->icon('heroicon-o-user-plus')
                                    ->color('success')
                                    ->extraAttributes([
                                        // un poco de margen a la izquierda en pantallas medianas en adelante
                                        'class' => 'md:ml-4',
                                    ])
                                    ->action(function (Set $set) {

                                        // 🔹 1. LIMPIAR SIEMPRE LOS DATOS DEL TERCERO YA SELECCIONADO
                                        //    (esto hace que la validación vea la identificación como vacía)

                                        // Campo principal que se usa para validar el paso:
                                        $set('numero_identificacion_cliente', null); 
                                        $set('cedula', null);                        // por si la validación usa este

                                        // Tipo de identificación seleccionado
                                        $set('tipo_identificacion_cliente', null);

                                        // Datos de identificación / nombre / contacto que se muestran después de seleccionar
                                        $set('razon_social_cliente', null);
                                        $set('primer_nombre_cliente', null);
                                        $set('segundo_nombre_cliente', null);
                                        $set('primer_apellido_cliente', null);
                                        $set('segundo_apellido_cliente', null);
                                        $set('nombre_comercial_cliente', null);
                                        $set('barrio_cliente', null);
                                        $set('telefono_cliente', null);
                                        $set('celular_cliente', null);
                                        $set('direccion_cliente', null);

                                        // Flags auxiliares si los usas para validar que haya tercero cargado
                                        $set('tercero_seleccionado', null);
                                        $set('cliente_cargado', false);

                                        // 🔹 2. OCULTAR MODAL DE RESULTADOS Y MOSTRAR MODAL DE CREACIÓN
                                        $set('mostrar_modal_resultados', false);
                                        $set('mostrar_modal_crear_tercero', true);
                                        $set('mostrar_detalle_tercero', false);

                                        // 🔹 3. LIMPIAR CAMPOS DEL FORMULARIO DE CREACIÓN
                                        $set('nuevo_naturaleza', null);
                                        $set('nuevo_tipo_identificacion', null);
                                        $set('nuevo_nit', null);
                                        $set('nuevo_nombre1', null);
                                        $set('nuevo_nombre2', null);
                                        $set('nuevo_apellido1', null);
                                        $set('nuevo_apellido2', null);
                                        $set('nuevo_razon_social', null);
                                        $set('nuevo_nombre_comercial', null);
                                        $set('nuevo_direccion', null);
                                        $set('nuevo_residencia_departamento', null);
                                        $set('nuevo_codigoCiudad', null);
                                        $set('nuevo_barrio', null);
                                        $set('nuevo_celular', null);
                                        $set('nuevo_telefono1', null);
                                        $set('nuevo_email', null);
                                    }),

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
                    ]),

                // =========================================================
                // "MODAL" INLINE: RESULTADOS DE BÚSQUEDA
                // =========================================================
                Section::make()
                    ->visible(fn (Get $get) => (bool) $get('mostrar_modal_resultados'))
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
                                            ->visible(function (Get $get) {
                                                $token = $get('resultados_busqueda_token');
                                                if (! $token) {
                                                    return false;
                                                }
                                                $base = Cache::get("facturacion_terceros_{$token}", []);
                                                return is_array($base) && count($base) > 0;
                                            })
                                            ->action(function (Get $get, Set $set) {
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
                                            ->action(function (Set $set) {
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
                                ->visible(function (Get $get) {
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
                                                ->action(function (Get $get, Set $set) {
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
                                                ->action(function (Get $get, Set $set) {
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
                                                ->action(function (Get $get, Set $set) {
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
                                ->visible(function (Get $get) {
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
                                                    ->action(function (Get $get, Set $set) { 
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

                                                        if ($tipoIdUpper === 'NIT') {
                                                            $set('../../nombre_cliente', $razon ?: $nombre);
                                                            $set('../../nombre1_cliente', null);
                                                            $set('../../nombre2_cliente', null);
                                                            $set('../../apellido1_cliente', null);
                                                            $set('../../apellido2_cliente', null);
                                                        } else {
                                                            $set('../../nombre_cliente', null);
                                                            $set('../../nombre1_cliente', $nombre1);
                                                            $set('../../nombre2_cliente', $nombre2);
                                                            $set('../../apellido1_cliente', $apellido1);
                                                            $set('../../apellido2_cliente', $apellido2);
                                                        }

                                                        $set('../../cedula', $cedula);
                                                        $set('../../telefono', $telefono);
                                                        $set('../../direccion', $direccion);
                                                        $set('../../email_cliente', $email);
                                                        $set('../../ciudad_cliente', $ciudad);
                                                        $set('../../zona_cliente', $zona);
                                                        $set('../../tipo_identificacion_cliente', $tipoId);

                                                        $set('../../nombre_comercial_cliente', $nombreComercial);
                                                        $set('../../barrio_cliente', $barrio);

                                                        // 🔹 NUEVO: campos necesarios para la validación de reservas
                                                        $numeroIdent = $registro['codigoInterno'] ?? $registro['cedula'] ?? null;
                                                        $tel1        = $registro['telefono1']     ?? null;
                                                        $tel2        = $registro['telefono2']     ?? null;
                                                        $celular     = $registro['celular']       ?? $telefono ?? null;

                                                        $set('../../numero_identificacion_cliente', $numeroIdent);
                                                        $set('../../telefono1_cliente', $tel1);
                                                        $set('../../telefono2_cliente', $tel2);
                                                        $set('../../celular_cliente',   $celular);

                                                        \Log::info('[SeleccionTercero] Campos cargados para reserva', [
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
                                ->visible(function (Get $get) {
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
                                ->visible(function (Get $get) {
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
                                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
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
                                            ->action(function (Get $get, Set $set) {
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
                                            ->label(function (Get $get) {
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
                                            ->action(function (Get $get, Set $set) {
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

                // =========================================================
                // "MODAL" INLINE: CREAR TERCERO
                // =========================================================
                Section::make()
                    ->visible(fn (Get $get) => (bool) $get('mostrar_modal_crear_tercero'))
                    ->extraAttributes([
                        'class' => 'w-full mt-4',
                    ])
                    ->schema([
                        Group::make([
                            Grid::make(1)
                                ->schema([
                                    Actions::make([
                                        Action::make('cerrarModalCrear')
                                            ->label('')
                                            ->icon('heroicon-o-x-mark')
                                            ->color('secondary')
                                            ->extraAttributes([
                                                'class' => 'ml-auto text-gray-700 hover:text-gray-900 dark:text-gray-200 dark:hover:text-white',
                                            ])
                                            ->action(function (Set $set) {
                                                $set('mostrar_modal_crear_tercero', false);
                                            }),
                                    ])->alignment('right'),
                                ]),

                            Section::make('Crear tercero')
                                ->schema([
                                    // Naturaleza + tipo doc + número
                                    Grid::make(3)->schema([
                                        Select::make('nuevo_naturaleza')
                                            ->label('Naturaleza')
                                            ->options([
                                                'N' => 'Persona natural',
                                                'J' => 'Persona jurídica',
                                            ])
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                // Cada vez que cambia naturaleza, limpiar todo el formulario de creación
                                                $set('nuevo_tipo_identificacion', null);
                                                $set('nuevo_nit', null);
                                                $set('nuevo_nombre1', null);
                                                $set('nuevo_nombre2', null);
                                                $set('nuevo_apellido1', null);
                                                $set('nuevo_apellido2', null);
                                                $set('nuevo_razon_social', null);
                                                $set('nuevo_nombre_comercial', null);
                                                $set('nuevo_direccion', null);
                                                $set('nuevo_residencia_departamento', null);
                                                $set('nuevo_codigoCiudad', null);
                                                $set('nuevo_barrio', null);
                                                $set('nuevo_celular', null);
                                                $set('nuevo_telefono1', null);
                                                $set('nuevo_email', null);
                                            })
                                            ->dehydrated(false),

                                        Select::make('nuevo_tipo_identificacion')
                                            ->label('Tipo de identificación')
                                            ->options(function (Get $get) {
                                                $nat = $get('nuevo_naturaleza');

                                                $query = TipoDocumento::query();

                                                // 31 asumimos que es NIT (como en el resto de tu flujo)
                                                if ($nat === 'J') {
                                                    $query->where('ID_Identificacion_Tributaria', '31');
                                                } elseif ($nat === 'N') {
                                                    $query->where('ID_Identificacion_Tributaria', '!=', '31');
                                                } else {
                                                    // Si aún no han escogido naturaleza, no mostramos nada
                                                    return [];
                                                }

                                                return $query->pluck('desc_identificacion', 'ID_Identificacion_Tributaria');
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->disabled(fn (Get $get) => blank($get('nuevo_naturaleza')))
                                            ->dehydrated(false),

                           
                                        TextInput::make('nuevo_nit')
                                            ->label('Número de identificación')
                                            ->numeric()
                                            ->required()
                                            ->dehydrated(false)
                                            ->live()
                                            ->afterStateUpdated(function ($state) {
                                                Session::put('nuevo_nit', $state);
                                            }),
                                    ]),

                                    // Nombre(s) y apellidos SOLO para natural
                                    Grid::make(4)
                                        ->visible(fn (Get $get) => $get('nuevo_naturaleza') === 'N')
                                        ->schema([
                                            TextInput::make('nuevo_nombre1')
                                                ->label('Primer nombre')
                                                ->maxLength(50)
                                                ->required()
                                                ->extraInputAttributes([
                                                    'oninput' => 'this.value = this.value.toUpperCase()',
                                                ])
                                                ->dehydrated(false),

                                            TextInput::make('nuevo_nombre2')
                                                ->label('Segundo nombre')
                                                ->maxLength(50)
                                                ->extraInputAttributes([
                                                    'oninput' => 'this.value = this.value.toUpperCase()',
                                                ])
                                                ->dehydrated(false),

                                            TextInput::make('nuevo_apellido1')
                                                ->label('Primer apellido')
                                                ->maxLength(50)
                                                ->required()
                                                ->extraInputAttributes([
                                                    'oninput' => 'this.value = this.value.toUpperCase()',
                                                ])
                                                ->dehydrated(false),

                                            TextInput::make('nuevo_apellido2')
                                                ->label('Segundo apellido')
                                                ->maxLength(50)
                                                ->extraInputAttributes([
                                                    'oninput' => 'this.value = this.value.toUpperCase()',
                                                ])
                                                ->dehydrated(false),
                                        ]),

                                    // Razón social SOLO para jurídica
                                    Grid::make(1)
                                        ->visible(fn (Get $get) => $get('nuevo_naturaleza') === 'J')
                                        ->schema([
                                            TextInput::make('nuevo_razon_social')
                                                ->label('Razón social')
                                                ->maxLength(100)
                                                ->required()
                                                ->extraInputAttributes([
                                                    'oninput' => 'this.value = this.value.toUpperCase()',
                                                ])
                                                ->dehydrated(false),
                                        ]),

                                    // Nombre comercial (para ambos)
                                    Grid::make(1)->schema([
                                        TextInput::make('nuevo_nombre_comercial')
                                            ->label('Nombre comercial')
                                            ->maxLength(100)
                                            ->extraInputAttributes([
                                                'oninput' => 'this.value = this.value.toUpperCase()',
                                            ])
                                            ->dehydrated(false),
                                    ]),

                                    Grid::make(2)->schema([
                                        TextInput::make('nuevo_direccion')
                                            ->label('Dirección')
                                            ->maxLength(100)
                                            ->required()
                                            ->extraInputAttributes([
                                                'oninput' => 'this.value = this.value.toUpperCase()',
                                            ])
                                            ->dehydrated(false),

                                        TextInput::make('nuevo_barrio')
                                            ->label('Barrio')
                                            ->maxLength(50)
                                            ->extraInputAttributes([
                                                'oninput' => 'this.value = this.value.toUpperCase()',
                                            ])
                                            ->dehydrated(false),
                                    ]),

                                    Grid::make(2)->schema([
                                        Select::make('nuevo_residencia_departamento')
                                            ->label('Departamento de residencia')
                                            ->options(
                                                ZDepartamentos::all()->pluck('name_departamento', 'id')
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                $set('nuevo_codigoCiudad', null);
                                            })
                                            ->required()
                                            ->dehydrated(false),

                                        Select::make('nuevo_codigoCiudad')
                                            ->label('Municipio de residencia')
                                            ->options(function (Get $get) {
                                                $departamentoId = $get('nuevo_residencia_departamento');
                                                if ($departamentoId) {
                                                    return ZMunicipios::where('departamento_id', $departamentoId)
                                                        ->pluck('name_municipio', 'id');
                                                }
                                                return [];
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->dehydrated(false),
                                    ]),

                                    Grid::make(2)->schema([
                                        TextInput::make('nuevo_celular')
                                            ->label('Celular')
                                            ->tel()
                                            ->required()
                                            ->numeric()              // <-- numérico para que 'digits:10' funcione bien
                                            ->rule('digits:10')      // <-- EXACTAMENTE 10 dígitos para poder continuar
                                            ->minLength(10)
                                            ->maxLength(10)
                                            ->helperText('Debe tener exactamente 10 dígitos.')
                                            ->extraInputAttributes([
                                                'inputmode' => 'numeric',
                                                'pattern'   => '\d{10}',
                                                'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                            ])
                                            ->dehydrated(false),

                                        TextInput::make('nuevo_telefono1')
                                            ->label('Teléfono alterno')
                                            ->tel()
                                            //->required()
                                            ->numeric()              // <-- numérico para que 'digits:10' funcione bien
                                            ->rule('digits:10')      // <-- EXACTAMENTE 10 dígitos para poder continuar
                                            ->minLength(10)
                                            ->maxLength(10)
                                            ->helperText('Debe tener exactamente 10 dígitos.')
                                            ->extraInputAttributes([
                                                'inputmode' => 'numeric',
                                                'pattern'   => '\d{10}',
                                                'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                            ])
                                            ->dehydrated(false),
                                    ]),

                                    Grid::make(1)->schema([
                                        TextInput::make('nuevo_email')
                                            ->label('Correo electrónico')
                                            ->email()
                                            ->maxLength(100)
                                            ->required()
                                            ->dehydrated(false),
                                    ]),

                                    Actions::make([
                                        Action::make('guardar_nuevo_tercero')
                                            ->label('Guardar tercero')
                                            ->icon('heroicon-o-check-circle')
                                            ->color('primary')
                                            ->action(function (Get $get, Set $set) {
                                                $naturaleza = trim((string) ($get('nuevo_naturaleza') ?? ''));

                                                $tipoId   = trim((string) ($get('nuevo_tipo_identificacion') ?? ''));
                                                $nit      = trim((string) ($get('nuevo_nit') ?? ''));

                                                $nom1     = trim((string) ($get('nuevo_nombre1') ?? ''));
                                                $nom2     = trim((string) ($get('nuevo_nombre2') ?? ''));
                                                $ape1     = trim((string) ($get('nuevo_apellido1') ?? ''));
                                                $ape2     = trim((string) ($get('nuevo_apellido2') ?? ''));

                                                $razon    = trim((string) ($get('nuevo_razon_social') ?? ''));
                                                $nomCom   = trim((string) ($get('nuevo_nombre_comercial') ?? ''));

                                                $dir      = trim((string) ($get('nuevo_direccion') ?? ''));
                                                $deptId   = $get('nuevo_residencia_departamento');
                                                $munId    = $get('nuevo_codigoCiudad');
                                                $barrio   = trim((string) ($get('nuevo_barrio') ?? ''));

                                                $cel      = trim((string) ($get('nuevo_celular') ?? ''));
                                                $telAlt   = trim((string) ($get('nuevo_telefono1') ?? ''));
                                                $email    = trim((string) ($get('nuevo_email') ?? ''));

                                                // Fechas ya no se usan (se dejan null)
                                                $fechaNac = null;
                                                $fechaExp = null;


                                                // VALIDAR CELULAR: exactamente 10 dígitos
                                                if (! preg_match('/^\d{10}$/', $cel)) {
                                                    Notification::make()
                                                        ->title('Celular inválido')
                                                        ->body('El número de celular debe tener exactamente 10 dígitos.')
                                                        ->danger()
                                                        ->send();
                                                    return;
                                                }

                                                 // VALIDAR TELÉFONO ALTERNO: solo si tiene datos
                                                if ($telAlt !== '' && ! preg_match('/^\d{10}$/', $telAlt)) {
                                                    Notification::make()
                                                        ->title('Teléfono alterno inválido')
                                                        ->body('El teléfono alterno debe tener exactamente 10 dígitos si se diligencia.')
                                                        ->danger()
                                                        ->send();
                                                    return;
                                                }

                                                // VALIDAR EMAIL: formato de correo
                                                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                                    Notification::make()
                                                        ->title('Correo electrónico inválido')
                                                        ->body('Ingrese un correo electrónico válido.')
                                                        ->danger()
                                                        ->send();
                                                    return;
                                                }

                                                // Validaciones comunes
                                                if (
                                                    $naturaleza === '' ||
                                                    $tipoId === '' ||
                                                    $nit === '' ||
                                                    $dir === '' ||
                                                    ! $deptId ||
                                                    ! $munId ||
                                                    $cel === '' ||
                                                   // $telAlt === '' ||
                                                    $email === ''
                                                ) {
                                                    Notification::make()
                                                        ->title('Campos obligatorios')
                                                        ->body('Complete todos los campos obligatorios del formulario de creación.')
                                                        ->danger()
                                                        ->send();
                                                    return;
                                                }

                                                // Validaciones específicas por naturaleza
                                                if ($naturaleza === 'N') {
                                                    if ($nom1 === '' || $ape1 === '') {
                                                        Notification::make()
                                                            ->title('Campos obligatorios')
                                                            ->body('Para persona natural debe diligenciar al menos primer nombre y primer apellido.')
                                                            ->danger()
                                                            ->send();
                                                        return;
                                                    }
                                                } elseif ($naturaleza === 'J') {
                                                    if ($razon === '') {
                                                        Notification::make()
                                                            ->title('Campos obligatorios')
                                                            ->body('Para persona jurídica debe diligenciar la razón social.')
                                                            ->danger()
                                                            ->send();
                                                        return;
                                                    }
                                                }

                                                try {
                                                    $municipioResidencia = ZMunicipios::find($munId);

                                                    // Estructura de datos intermedia
                                                    $data = [
                                                        'ID_Identificacion_Cliente' => $tipoId,
                                                        'nit'               => $nit,
                                                        'codigoInterno'     => $nit,
                                                        'nombre1'           => strtoupper($nom1),
                                                        'nombre2'           => strtoupper($nom2),
                                                        'apellido1'         => strtoupper($ape1),
                                                        'apellido2'         => strtoupper($ape2),
                                                        'direccion'         => strtoupper($dir),
                                                        'codigoCiudad'      => $munId,
                                                        'barrio'            => strtoupper($barrio),
                                                        'celular'           => $cel,
                                                        'telefono1'         => $telAlt,
                                                        'email'             => $email,
                                                        'fecha_nac'         => $fechaNac,      // ahora siempre null
                                                        'fecha_expedicion'  => $fechaExp,      // ahora siempre null
                                                        'clasificacion'     => '02',
                                                        'clase'             => 'C',
                                                        'regimen'           => '01',
                                                        'naturalezaJuridica'=> $naturaleza,
                                                        'agenteRetenedor'   => false,
                                                        'autoretenedor'     => false,
                                                        'tipoNit'           => $tipoId === '31' ? '02' : '01',
                                                    ];

                                                    $data['ciudadExpedicionCedula'] = $data['codigoCiudad'];

                                                    $municipioResidencia = !empty($data['codigoCiudad'])
                                                        ? ZMunicipios::find($data['codigoCiudad'])
                                                        : null;

                                                    $municipioExpedicion = !empty($data['ciudadExpedicionCedula'])
                                                        ? ZMunicipios::find($data['ciudadExpedicionCedula'])
                                                        : null;

                                                    // Nombre completo para natural
                                                    $fullNombreNatural = strtoupper(implode(' ', array_filter([
                                                        $data['nombre1'] ?? '',
                                                        $data['nombre2'] ?? '',
                                                        $data['apellido1'] ?? '',
                                                        $data['apellido2'] ?? '',
                                                    ])));

                                                    // Descripción / razón social y nombre comercial según naturaleza
                                                    if ($naturaleza === 'J') {
                                                        $razonSocialApi     = strtoupper($razon);
                                                        $nombreComercialApi = strtoupper($nomCom ?: $razon);
                                                    } else {
                                                        // Natural
                                                        $razonSocialApi     = $fullNombreNatural;
                                                        $nombreComercialApi = strtoupper($nomCom ?: $fullNombreNatural);
                                                    }

                                                    $datosApi = [
                                                        "tipoIdentificacionTributaria" => (string)($data['ID_Identificacion_Cliente'] ?? ''),
                                                        "nit"                => (string)($data['nit'] ?? ''),
                                                        "codigoInterno"      => (string)($data['nit'] ?? ''),
                                                        "nombre1"            => $data['nombre1'] ?? '',
                                                        "nombre2"            => $data['nombre2'] ?? '',
                                                        "apellido1"          => $data['apellido1'] ?? '',
                                                        "apellido2"          => $data['apellido2'] ?? '',
                                                        "ciudadExpecidionCedula" => $municipioExpedicion ? $municipioExpedicion->code_municipio : '',
                                                        "descripcion"        => $razonSocialApi,
                                                        "nombreComercial"    => $nombreComercialApi,
                                                        "clasificacion"      => $data['clasificacion'] ?? '',
                                                        "tipoNit"            => $data['tipoNit'] ?? '',
                                                        "clase"              => $data['clase'] ?? 'C',
                                                        "observacionGeneral" => '',
                                                        "direccion"          => $data['direccion'] ?? '',
                                                        "codigoCiudad"       => $municipioResidencia ? $municipioResidencia->id : '',
                                                        "barrio"             => $data['barrio'] ?? '',
                                                        "celular"            => $data['celular'] ?? '',
                                                        "telefono1"          => $data['telefono1'] ?? '',
                                                        "email"              => $data['email'] ?? '',
                                                        "fechaNacimiento"    => null, // ya no usamos fecha_nac
                                                        "sexo"               => '',
                                                        "idRegion"           => 1,
                                                        "controlCupoCredito" => 0,
                                                        "regimen"            => $data['regimen'] ?? '',
                                                        "emailFacturacionElectronica" => $data['email'] ?? '',
                                                        "agenteRetenedor"    => ($data['agenteRetenedor'] ?? false) ? 'S' : 'N',
                                                        "autoretenedor"      => ($data['autoretenedor'] ?? false) ? 'S' : 'N',
                                                        "naturalezaJuridica" => $data['naturalezaJuridica'] ?? 'N',
                                                        "permitirTerceroDuplicado" => false,
                                                        "fechaExpedicionCedula" => null, // ya no usamos fecha_expedicion
                                                        "fechaFinalMetrica"  => now()->timezone('UTC')->format('Y-m-d\TH:i:s.v\Z'),
                                                        "usuarioResponsable" => 'YEMINUS',
                                                        "diasEntrega"        => 0,
                                                        "facturarAlcantarillado" => 'S',

                                                        // extras
                                                        "codigoGS1" => null,
                                                        "digitoChequeo" => null,
                                                        "telefono2" => '',
                                                        "descripcionTipoNit" => '',
                                                        "formaPago" => null,
                                                        "descripcionSector" => null,
                                                        "descripcionClasifTercero" => '',
                                                        "descripcionUnidadesNegocio" => null,
                                                        "descripcionFormaPago" => null,
                                                        "descripcionVendedor" => null,
                                                        "descripcionZona" => '',
                                                        "afiliacion" => null,
                                                        "fechaAfiliacion" => null,
                                                        "matricula" => null,
                                                        "fechaMatricula" => null,
                                                        "fechaModificacion" => null,
                                                        "usuarioModificacion" => null,
                                                        "usuarioCreacion" => 'YEMINUS',
                                                        "fechaUltimaCompra" => null,
                                                        "hobby" => null,
                                                        "patron" => null,
                                                        "fechaReactivar" => null,
                                                        "fechaInactivo" => null,
                                                        "observacionesInactivo" => null,
                                                        "fechaCualificacion" => null,
                                                        "informacionCertificadoCalidad" => null,
                                                        "estado" => 'A',
                                                        "paginaWeb" => null,
                                                        "cupoCredito" => null,
                                                        "mensajeAlerta" => null,
                                                        "ocupacion" => null,
                                                        "descripcionCampana" => null,
                                                        "descripcionActividadEconomica" => null,
                                                        "centroDeCosto" => null,
                                                    ];

                                                    /** @var TercerosApiService $tercerosApiService */
                                                    $tercerosApiService = app(TercerosApiService::class);

                                                    $respuestaApi = $tercerosApiService->crearTercero($datosApi);

                                                    if (isset($respuestaApi['infoOperacion']['esExitosa']) && $respuestaApi['infoOperacion']['esExitosa'] === true) {
                                                        $tipoDocModel = TipoDocumento::where('ID_Identificacion_Tributaria', $tipoId)->first();
                                                        $tipoDesc     = $tipoDocModel?->desc_identificacion;
                                                        $tipoUpper    = strtoupper((string) $tipoDesc);

                                                        // Nombre que vamos a mostrar / guardar en el step
                                                        if ($naturaleza === 'J') {
                                                            $fullNombreSeleccion = $razonSocialApi;
                                                        } else {
                                                            $fullNombreSeleccion = $fullNombreNatural;
                                                        }

                                                        $set('cedula', $nit);
                                                        $set('tipo_identificacion_cliente', $tipoDesc);
                                                        $set('telefono', $cel ?: $telAlt);
                                                        $set('direccion', $data['direccion']);
                                                        $set('email_cliente', $email);
                                                        $set('ciudad_cliente', $municipioResidencia?->name_municipio);
                                                        $set('zona_cliente', null);
                                                        $set('nombre_comercial_cliente', $nombreComercialApi);
                                                        $set('barrio_cliente', $data['barrio']);

                                                         // 🔹 Campos ocultos usados por la validación de reservas
                                                        //    (para que el flujo de reservas funcione igual que cuando se selecciona desde el modal de resultados)
                                                        $set('numero_identificacion_cliente', $nit);           // viene del nuevo tercero
                                                        $set('telefono1_cliente',             $telAlt ?? null); // teléfono alterno del formulario
                                                        $set('telefono2_cliente',             null);           // no lo manejamos aquí
                                                        $set('celular_cliente',               $cel ?? null);   // celular principal


                                                        if ($tipoUpper === 'NIT') {
                                                            // Jurídica: solo razón social
                                                            $set('nombre_cliente', $fullNombreSeleccion);
                                                            $set('nombre1_cliente', null);
                                                            $set('nombre2_cliente', null);
                                                            $set('apellido1_cliente', null);
                                                            $set('apellido2_cliente', null);
                                                        } else {
                                                            // Natural: nombres separados
                                                            $set('nombre_cliente', null);
                                                            $set('nombre1_cliente', $data['nombre1']);
                                                            $set('nombre2_cliente', $data['nombre2']);
                                                            $set('apellido1_cliente', $data['apellido1']);
                                                            $set('apellido2_cliente', $data['apellido2']);
                                                        }

                                                        $set('mostrar_modal_crear_tercero', false);
                                                        $set('mostrar_detalle_tercero', true);

                                                        Notification::make()
                                                            ->title('Tercero creado y seleccionado')
                                                            ->body('El tercero se creó correctamente y sus datos han sido cargados.')
                                                            ->success()
                                                            ->send();
                                                    } else {
                                                        // ========================
                                                        // Manejo de errores 
                                                        // ========================
                                                        $messages = [];

                                                        // detalleError de infoOperacion
                                                        if (isset($respuestaApi['infoOperacion']['detalleError'])) {
                                                            $detalle = $respuestaApi['infoOperacion']['detalleError'];

                                                            if (!empty($detalle['tipoError'] ?? null)) {
                                                                $messages[] = 'Tipo Error: ' . $detalle['tipoError'];
                                                            }
                                                            if (!empty($detalle['codigoError'] ?? null)) {
                                                                $messages[] = 'Código: ' . $detalle['codigoError'];
                                                            }
                                                            if (!empty($detalle['mensaje'] ?? null)) {
                                                                $messages[] = 'Mensaje: ' . $detalle['mensaje'];
                                                            }
                                                            if (!empty($detalle['descripcionError'] ?? null)) {
                                                                $messages[] = 'Descripción: ' . $detalle['descripcionError'];
                                                            }
                                                            if (!empty($detalle['origenError'] ?? null)) {
                                                                $messages[] = 'Origen: ' . $detalle['origenError'];
                                                            }
                                                        }

                                                        // mensaje general
                                                        if (!empty($respuestaApi['mensaje'] ?? null)) {
                                                            $messages[] = (string) $respuestaApi['mensaje'];
                                                        }

                                                        // listaErrores (si viene)
                                                        if (isset($respuestaApi['infoOperacion']['listaErrores']) &&
                                                            is_array($respuestaApi['infoOperacion']['listaErrores'])) {
                                                            foreach ($respuestaApi['infoOperacion']['listaErrores'] as $err) {
                                                                if (!empty($err['descripcionError'] ?? null)) {
                                                                    $messages[] = 'Error: ' . $err['descripcionError'];
                                                                }
                                                                if (!empty($err['tipoError'] ?? null)) {
                                                                    $messages[] = 'Tipo: ' . $err['tipoError'];
                                                                }
                                                            }
                                                        }

                                                        // datos si es código como "ALERT_ALREADY_EXIST"
                                                        if (!empty($respuestaApi['datos']) && is_string($respuestaApi['datos'])) {
                                                            $messages[] = 'Código: ' . $respuestaApi['datos'];
                                                        }

                                                        // 👉 NUEVO: tratamiento especial para ALERT_ALREADY_EXIST
                                                        $codigoDatos = $respuestaApi['datos'] ?? null;
                                                        if (is_string($codigoDatos) && strtoupper($codigoDatos) === 'ALERT_ALREADY_EXIST') {
                                                            Notification::make()
                                                                ->title('El tercero ya existe')
                                                                ->body('Cliente ya esta creado en el sistema.')
                                                                ->warning()
                                                                ->send();

                                                            // No seguimos con el manejo genérico
                                                            return;
                                                        }

                                                        // fallback
                                                        $errorMessage = trim(implode(' | ', array_unique(array_filter($messages))));

                                                        if ($errorMessage === '') {
                                                            $errorMessage = 'Error desconocido al crear cliente en Yeminus';
                                                        }

                                                        $lower = mb_strtolower($errorMessage, 'UTF-8');
                                                        if (str_contains($lower, 'ya existe')) {
                                                            // Error específico: mensaje de la API indica que ya existe
                                                            Notification::make()
                                                                ->title('El tercero ya existe')
                                                                ->body($errorMessage)
                                                                ->warning()
                                                                ->send();
                                                            return;
                                                        }

                                                        Log::error('Error al crear cliente en Yeminus desde Step:', [
                                                            'respuesta'      => $respuestaApi,
                                                            'datos_enviados' => $datosApi,
                                                        ]);

                                                        Notification::make()
                                                            ->title('Error al crear cliente')
                                                            ->body($errorMessage)
                                                            ->danger()
                                                            ->send();
                                                    }

                                                } catch (\Throwable $e) {
                                                    Log::error('Excepción al crear tercero desde Step:', [
                                                        'exception' => $e,
                                                    ]);

                                                    Notification::make()
                                                        ->title('Error en el sistema')
                                                        ->body('Ocurrió un error inesperado al intentar crear el cliente.')
                                                        ->danger()
                                                        ->send();
                                                }
                                            }),

                                        Action::make('cancelar_creacion')
                                            ->label('Cancelar')
                                            ->color('secondary')
                                            ->action(function (Set $set) {
                                                $set('mostrar_modal_crear_tercero', false);
                                            }),
                                    ])->alignment('right'),
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

                // =========================================================
                // DATOS DEL CLIENTE SELECCIONADO (sección debajo)
                // =========================================================
                Section::make('Datos del cliente seleccionado')
                    ->visible(fn (Get $get) => (bool) $get('mostrar_detalle_tercero'))
                    ->schema([
                        TextInput::make('tipo_identificacion_cliente')
                            ->label('Tipo identificación')
                            ->disabled()
                            ->maxLength(100),

                        TextInput::make('cedula')
                            ->label('Número de identificación')
                            ->maxLength(30)
                            ->disabled()
                            ->required()
                            ->validationMessages([
                                'required' => 'Debe seleccionar un tercero (el número de identificación no puede estar vacío).',
                            ])
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                Session::put('cedula', $state);
                            }),




                        TextInput::make('nombre_cliente')
                            ->label(function (Get $get) {
                                $tipo = strtoupper((string) ($get('tipo_identificacion_cliente') ?? ''));
                                return $tipo === 'NIT'
                                    ? 'Razón social'
                                    : 'Nombre del cliente';
                            })
                            ->maxLength(255)
                            ->columnSpan(3)
                            ->visible(function (Get $get) {
                                $tipo = strtoupper((string) ($get('tipo_identificacion_cliente') ?? ''));
                                return $tipo === 'NIT';
                            })
                            ->disabled(),

                        Grid::make(4)
                            ->visible(function (Get $get) {
                                $tipo = strtoupper((string) ($get('tipo_identificacion_cliente') ?? ''));
                                return $tipo !== 'NIT';
                            })
                            ->schema([
                                TextInput::make('nombre1_cliente')
                                    ->label('Primer nombre')
                                    ->maxLength(100)
                                    ->disabled(),

                                TextInput::make('nombre2_cliente')
                                    ->label('Segundo nombre')
                                    ->maxLength(100)
                                    ->disabled(),

                                TextInput::make('apellido1_cliente')
                                    ->label('Primer apellido')
                                    ->maxLength(100)
                                    ->disabled(),

                                TextInput::make('apellido2_cliente')
                                    ->label('Segundo apellido')
                                    ->maxLength(100)
                                    ->disabled(),
                            ])
                            ->columnSpanFull(),

                        TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(30)
                            ->disabled(),

                        TextInput::make('email_cliente')
                            ->label('Correo electrónico')
                            ->email()
                            ->maxLength(255)
                            ->columnSpan(2)
                            ->disabled(),

                        TextInput::make('ciudad_cliente')
                            ->label('Ciudad')
                            ->maxLength(255)
                            ->disabled(),

                        TextInput::make('zona_cliente')
                            ->label('Zona')
                            ->maxLength(255)
                            ->disabled(),

                        TextInput::make('nombre_comercial_cliente')
                            ->label('Nombre comercial')
                            ->maxLength(255)
                            ->columnSpan(2)
                            ->disabled(),

                        TextInput::make('barrio_cliente')
                            ->label('Barrio')
                            ->maxLength(255)
                            ->disabled(),

                        Textarea::make('direccion')
                            ->label('Dirección')
                            ->rows(2)
                            ->columnSpanFull()
                            ->disabled(),
                    ])
                    ->columns(4),

                // =========================================================
                // HIDDEN STATE
                // =========================================================
                Hidden::make('resultados_busqueda_token')
                    ->default(null)
                    ->dehydrated(false),

                Hidden::make('resultados_busqueda')
                    ->default([])
                    ->dehydrated(false),

                Hidden::make('pagina_resultados')
                    ->default(1)
                    ->dehydrated(false),

                Hidden::make('mostrar_modal_resultados')
                    ->default(false)
                    ->dehydrated(false),

                Hidden::make('mostrar_modal_crear_tercero')
                    ->default(false)
                    ->dehydrated(false),

                Hidden::make('mostrar_detalle_tercero')
                    ->default(false)
                    ->dehydrated(false),

                Hidden::make('filtro_cedula')
                    ->default('')
                    ->dehydrated(false),

                Hidden::make('filtro_nombre')
                    ->default('')
                    ->dehydrated(false),

                Hidden::make('filtro_telefono')
                    ->default('')
                    ->dehydrated(false),

                Hidden::make('per_page_resultados')
                    ->default(5)
                    ->dehydrated(false),
            ])
            ->columns(1)
            ->afterValidation(function (Get $get) {
                $cedula = trim((string) ($get('cedula') ?? ''));


                if ($cedula === '') {
                    Notification::make()
                        ->title('Falta información del cliente')
                        ->body('Debe seleccionar un tercero para continuar. El número de identificación es obligatorio.')
                        ->danger()
                        ->send();

                    throw ValidationException::withMessages([
                        'cedula' => 'Debe seleccionar un tercero para continuar. El número de identificación no puede estar vacío.',
                    ]);
                }

                   \Log::info('[ReservaCheck] --- INICIO afterValidation Información del cliente ---');

            // 🔹 Datos del cliente seleccionados (ya lo tenías)
            $numeroIdentCliente = $get('numero_identificacion_cliente');
            $tel1               = $get('telefono1_cliente');
            $tel2               = $get('telefono2_cliente');
            $celular            = $get('celular_cliente');

            \Log::info('[ReservaCheck] Datos cliente seleccionados', [
                'numero_identificacion_cliente' => $numeroIdentCliente,
                'telefono1_cliente'             => $tel1,
                'telefono2_cliente'             => $tel2,
                'celular_cliente'               => $celular,
            ]);

            // ⚠️ Si no hay documento → no ejecuta lógica de reservas
            if (blank($numeroIdentCliente)) {
                \Log::warning('[ReservaCheck] numero_identificacion_cliente está vacío. No se ejecuta lógica de reservas.');
                return;
            }

            // 🔹 Teléfonos válidos
            $telefonos = collect([$tel1, $tel2, $celular])
                ->filter(fn ($t) => filled($t))
                ->values()
                ->all();

            \Log::info('[ReservaCheck] Teléfonos válidos para búsqueda', [
                'telefonos' => $telefonos,
            ]);

            // ================================
            // 1) BUSCAR RESERVA POR TELÉFONO
            // ================================
            $reserva = null;

            if (! empty($telefonos)) {
                \Log::info('[ReservaCheck] Buscando reserva por teléfono en reserva_venta...');

                $reserva = \App\Models\Reserva_venta::whereIn('Telefono_Cliente', $telefonos)
                    ->orderByDesc('Fecha_Registro_Venta')
                    ->orderByDesc('Hora_Registro_Venta')
                    ->first();

                \Log::info('[ReservaCheck] Resultado búsqueda por teléfono', [
                    'encontrada' => (bool) $reserva,
                    'reserva'    => $reserva,
                ]);
            }

            // ================================
            // 2) SI NO HAY POR TELÉFONO, BUSCAR POR DOCUMENTO
            // ================================
            if (! $reserva) {
                \Log::info('[ReservaCheck] Buscando reserva por documento en reserva_venta...', [
                    'N_Documento_Cliente' => $numeroIdentCliente,
                ]);

                $reserva = \App\Models\Reserva_venta::where('N_Documento_Cliente', $numeroIdentCliente)
                    ->orderByDesc('Fecha_Registro_Venta')
                    ->orderByDesc('Hora_Registro_Venta')
                    ->first();

                \Log::info('[ReservaCheck] Resultado búsqueda por documento', [
                    'encontrada' => (bool) $reserva,
                    'reserva'    => $reserva,
                ]);
            }

            // Si no hay ninguna reserva → no pasa nada
            if (! $reserva) {
                \Log::info('[ReservaCheck] No se encontró ninguna reserva para este cliente. Se continúa el flujo normalmente.');
                return;
            }

            // ================================
            // 3) CÁLCULO DE FECHA / ESTADO
            // ================================
            $fecha = $reserva->Fecha_Registro_Venta;
            $hora  = $reserva->Hora_Registro_Venta;

            \Log::info('[ReservaCheck] Datos de fecha/hora de reserva', [
                'Fecha_Registro_Venta' => $fecha,
                'Hora_Registro_Venta'  => $hora,
            ]);

            if ($fecha && $hora) {
                $fechaHoraRegistro = \Carbon\Carbon::parse($fecha . ' ' . $hora);
                $diasDiferencia    = $fechaHoraRegistro->diffInDays(now());

                $estadoActual = $reserva->Estado;
                $nuevoEstado  = $diasDiferencia >= 7 ? 'Disponible' : 'Pendiente';

                \Log::info('[ReservaCheck] Cálculo de días y estado', [
                    'fecha_hora_registro' => $fechaHoraRegistro->toDateTimeString(),
                    'dias_diferencia'     => $diasDiferencia,
                    'estado_actual'       => $estadoActual,
                    'nuevo_estado'        => $nuevoEstado,
                ]);

                if ($estadoActual !== $nuevoEstado) {
                    $reserva->Estado = $nuevoEstado;
                    $reserva->save();

                    \Log::info('[ReservaCheck] Estado de reserva actualizado en BD.', [
                        'ID_Reserva' => $reserva->ID_Reserva,
                        'Estado'     => $nuevoEstado,
                    ]);
                } else {
                    \Log::info('[ReservaCheck] Estado de reserva ya era correcto, no se actualiza.');
                }

                // Si quedó DISPONIBLE → dejar seguir y ya
                if ($reserva->Estado === 'Disponible') {
                    \Log::info('[ReservaCheck] Reserva en estado DISPONIBLE. Se continúa el flujo normalmente.');
                    return;
                }
            }

            // ================================
        // 4) SI SIGUE EN PENDIENTE → VALIDAR ASESOR
        // ================================
        \Log::info('[ReservaCheck] Reserva en estado PENDIENTE. Se validará si pertenece al mismo asesor.');

        // 🔹 LEER BIEN EL REPETER detallesCliente DESDE LA RAÍZ
        $detallesCliente = $get('detallesCliente') ?? [];

        \Log::info('[ReservaCheck] Estado completo de detallesCliente', [
            'detallesCliente' => $detallesCliente,
        ]);

        // Como el repeater usa claves UUID, tomamos la primera fila con reset()
        $codigoAsesorRepeater = null;

        if (is_array($detallesCliente) && ! empty($detallesCliente)) {
            $firstRow = reset($detallesCliente); // devuelve el primer elemento del array

            if (is_array($firstRow)) {
                $codigoAsesorRepeater = $firstRow['codigo_asesor'] ?? null;
            }
        }

        // Por si acaso en algún contexto lo hubieras guardado a nivel raíz
        $codigoAsesorRoot = $get('codigo_asesor');

        // Usamos primero el del repeater, si no, el del root
        $codigoAsesorFormulario = $codigoAsesorRepeater ?: $codigoAsesorRoot;

        \Log::info('[ReservaCheck] Extracción de código asesor en formulario', [
            'desde_root_codigo_asesor'     => $codigoAsesorRoot,
            'desde_detallesCliente_first'  => $codigoAsesorRepeater,
            'codigo_asesor_formulario_fin' => $codigoAsesorFormulario,
        ]);

        // Si no hay código asesor en el formulario → no bloqueamos
        if (blank($codigoAsesorFormulario)) {
            \Log::warning('[ReservaCheck] Alguno de los códigos de asesor está vacío. No se bloquea el flujo.');
            return;
        }



    // Buscar AaPrin por ID_Asesor de la reserva
    $aaPrin = \App\Models\AaPrin::where('ID_Asesor', $reserva->ID_Asesor)
        ->where('ID_Estado', '!=', 3)
        ->orderByDesc('ID_Inf_trab')
        ->first();

    \Log::info('[ReservaCheck] Resultado búsqueda AaPrin', [
        'ID_Asesor_reserva' => $reserva->ID_Asesor,
        'aaPrin'            => $aaPrin,
    ]);

    if (! $aaPrin) {
        \Log::warning('[ReservaCheck] No se encontró AaPrin para el asesor de la reserva. No se bloquea el flujo.');
        return;
    }

    // Buscar InfTrab para obtener el código del vendedor de la reserva
    $infTrab = \App\Models\InfTrab::find($aaPrin->ID_Inf_trab);

    \Log::info('[ReservaCheck] Resultado búsqueda InfTrab', [
        'ID_Inf_trab' => $aaPrin->ID_Inf_trab ?? null,
        'infTrab'     => $infTrab,
    ]);

    if (! $infTrab) {
        \Log::warning('[ReservaCheck] No se encontró InfTrab para el ID_Inf_trab. No se bloquea el flujo.');
        return;
    }

    $codigoAsesorReserva = $infTrab->Codigo_vendedor ?? null;

    Log::info('[ReservaCheck] Comparación de códigos de asesor', [
        'codigo_asesor_reserva'    => $codigoAsesorReserva,
        'codigo_asesor_formulario' => $codigoAsesorFormulario,
    ]);

  
    // Para dejar seguir cuando la reserva está PENDIENTE, es obligatorio:
    // - Que exista código de asesor en la reserva (InfTrab)
    // - Que exista código de asesor en el formulario
    // - Que ambos sean IGUALES
    if (empty($codigoAsesorReserva) || empty($codigoAsesorFormulario)) {
        Log::warning('[ReservaCheck] Código de asesor faltante (reserva o formulario). Se bloquea el flujo.', [
            'codigo_asesor_reserva'    => $codigoAsesorReserva,
            'codigo_asesor_formulario' => $codigoAsesorFormulario,
        ]);

        Notification::make()
            ->title('Cliente reservado no perteneciente')
            ->body('El cliente tiene una reserva activa con otro asesor . No puede continuar con la facturación.')
            ->danger()
            ->send();

        throw \Illuminate\Validation\ValidationException::withMessages([
            'busqueda_tercero' => 'El cliente tiene una reserva activa con otro asesor . No puede continuar con la facturación.',
        ]);
    }

    if ($codigoAsesorReserva !== $codigoAsesorFormulario) {
        Log::warning('[ReservaCheck] Códigos de asesor distintos. Se bloquea el flujo.', [
            'codigo_asesor_reserva'    => $codigoAsesorReserva,
            'codigo_asesor_formulario' => $codigoAsesorFormulario,
        ]);

        Notification::make()
            ->title('Cliente reservado no perteneciente')
            ->body('El cliente tiene una reserva activa con otro asesor. No puede continuar con la facturación.')
            ->danger()
            ->send();

        throw \Illuminate\Validation\ValidationException::withMessages([
            'busqueda_tercero' => 'El cliente tiene una reserva activa con otro asesor. No puede continuar con la facturación.',
        ]);
    }

    //  Si llega aquí, ambos códigos existen y coinciden → se permite continuar.
    Log::info('[ReservaCheck] Códigos de asesor coinciden. Se permite continuar.');

            });
    }

    /**
     * Aplica filtros (cedula, nombre, teléfono) sobre el array base en caché
     * y actualiza la paginación en resultados_busqueda.
     */
    protected static function aplicarFiltrosYPaginar(Get $get, Set $set): void
    {
        $token = $get('resultados_busqueda_token');

        if (! $token) {
            $set('resultados_busqueda', []);
            return;
        }

        $base = Cache::get("facturacion_terceros_{$token}", []);
        if (! is_array($base)) {
            $base = [];
        }

        $fCedula = trim((string) ($get('filtro_cedula') ?? ''));
        $fNombre = trim(mb_strtolower((string) ($get('filtro_nombre') ?? ''), 'UTF-8'));
        $fTel    = trim((string) ($get('filtro_telefono') ?? ''));

        $filtrados = collect($base)->filter(function ($row) use ($fCedula, $fNombre, $fTel) {
            $cedula   = (string) ($row['cedula'] ?? '');
            $nombre   = mb_strtolower((string) ($row['nombre'] ?? ''), 'UTF-8');
            $telefono = (string) ($row['telefono'] ?? '');

            $ok = true;

           if ($fCedula !== '') {
               $ok = $ok && str_contains($cedula, $fCedula);
           }

           if ($fNombre !== '') {
               $ok = $ok && str_contains($nombre, $fNombre);
           }

           if ($fTel !== '') {
               $ok = $ok && str_contains($telefono, $fTel);
           }

            return $ok;
        })->values()->all();

        $perPage = (int) ($get('per_page_resultados') ?? 5);
        if (! in_array($perPage, [5, 10, 20])) {
            $perPage = 5;
            $set('per_page_resultados', 5);
        }

        $set('pagina_resultados', 1);
        $set('resultados_busqueda', array_slice($filtrados, 0, $perPage));
    }
}
