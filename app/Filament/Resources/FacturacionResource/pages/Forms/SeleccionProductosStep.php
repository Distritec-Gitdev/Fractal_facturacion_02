<?php

namespace App\Filament\Resources\FacturacionResource\Forms;

use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Actions;

use App\Models\ZBodegaFacturacion;
use App\Models\Sede;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Session;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\ProductosController;
use Filament\Actions\StaticAction;
use App\Http\Controllers\Api\CarteraController;

use App\Services\ConsignacionService;

use Filament\Forms\Components\Checkbox;




class SeleccionProductosStep
{
    public string $codigoProducto = '';


    public static function seguimientoCartera(string $nit): array
    {
        try {
            $controller = app(CarteraController::class);

            $request = Request::create('/api/seguimiento', 'POST', [
                'nit' => $nit
            ]);

            $response = $controller->seguimiento($request);

            // Si es una JsonResponse, extraer los datos
            if ($response instanceof JsonResponse) {
                $data = $response->getData(true);
                return $data;
            }

            // Si ya es un array, retornarlo directamente
            if (is_array($response)) {
                return $response;
            }

            return [
                'error' => 'Respuesta inválida del servidor',
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    public static function formatearNumero($numero) {
        $numero = (string) $numero;
        $longitud = strlen($numero);
        $resultado = '';

        for ($i = 0; $i < $longitud; $i++) {
            // Agregar el dígito
            $resultado .= $numero[$i];

            // Agregar punto cada 3 dígitos desde el final
            $posicionDesdeElFinal = $longitud - $i - 1;
            if ($posicionDesdeElFinal > 0 && $posicionDesdeElFinal % 3 === 0) {
                $resultado .= '.';
            }
        }

        return $resultado;
    }


    public static function make(): Step
    {
        return Step::make('Selección de Productos')
            ->icon('heroicon-o-shopping-cart')
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make()
                            ->schema([
                                Placeholder::make('titulo')
                                    ->label('')
                                    ->content(new HtmlString('<h3 class="text-lg font-semibold text-gray-900 dark:text-white">Selección de Artículos — Busca y selecciona los productos a facturar. Elige la bodega correcta.</h3>'))
                                    ->columnSpan([
                                        'default' => 12,
                                        'lg' => 8,
                                    ]),

                                // Campo de Total Final 
                                Grid::make()
                                    ->schema([
                                        Placeholder::make('label_total')
                                            ->label('')
                                            ->content(new HtmlString('<span class="text-sm font-medium text-gray-900 dark:text-gray-900">Total de la Factura:</span>'))
                                            ->columnSpan(6),


                                        Placeholder::make('total_factura_display')
                                        ->label('')
                                        ->content(function (Get $get, Set $set) {

                                            $items = $get('items') ?? [];
                                            $tipo_venta = $get("Tipo_venta");
                                            $medio_pago = $get("medio_pago");
                                            $total = 0;

                                            foreach ($items as $item) {
                                                $cantidad = (float) ($item['cantidad'] ?? 0);
                                                $precio = (float) ($item['precio_unitario_raw'] ?? $item['precio_unitario'] ?? 0);
                                                $total += $cantidad * $precio;
                                            }

                                            // FORMATO CON PUNTOS, SIN DECIMALES
                                            $totalFormateado = number_format($total, 0, ',', '.');

                                            $nit = $get('cedula');
                                            if ($nit != null) {
                                                $seguimiento = self::seguimientoCartera($nit);
                                                if (isset($seguimiento['resultado_consignacion'])) {
                                                    $valorConsignacion = (int) ($seguimiento['resultado_consignacion']);
                                                    $set('puede_avanzar', true);

                                                    if ($valorConsignacion > 0 && $total > $valorConsignacion ) {
                                                        if ($tipo_venta == 3 || $medio_pago == 8) {
                                                            $set('puede_avanzar', false);

                                                            Notification::make()
                                                                ->title('Advertencia: Límite de Cupo Superado')
                                                                ->body('El total de la factura ($' . self::formatearNumero($total) . ') supera el cupo disponible, por favor comunicate con el área de cartera. ($' . self::formatearNumero($valorConsignacion) . ')')
                                                                ->warning()
                                                                ->persistent()
                                                                ->actions([
                                                                    \Filament\Notifications\Actions\Action::make('aceptar')
                                                                        ->label('Aceptar')
                                                                        ->button()
                                                                        ->close(),
                                                                ])
                                                                ->send();

                                                            Log::info('Seguimiento de cartera', [
                                                                'supera_la_cantidad' => true,
                                                                'nit' => $nit,
                                                                'total_factura' => $totalFormateado,
                                                                'valor_consignacion' => $valorConsignacion,
                                                                'resultado_completo' => $seguimiento['resultado_consignacion']
                                                            ]);
                                                        }
                                                        
                                                    }
                                                }
                                            }

                                            return new HtmlString('
                                                <div class="flex items-center justify-end">
                                                    <span class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                                                        $' . $totalFormateado . '
                                                    </span>
                                                </div>
                                            ');
                                        })
                                        ->columnSpan(6),
                                    ])
                                    ->columns(12)
                                    ->columnSpan([
                                        'default' => 12,
                                        'lg' => 4,
                                    ])
                                    ->extraAttributes([
                                        'class' => 'bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border-2 border-primary-200 dark:border-primary-800',
                                    ]),
                            ])
                            ->columns(12),
                    ])
                    ->extraAttributes([
                        'class' => 'mb-4',
                    ]),

                Section::make()
                    ->schema([

                        Repeater::make('items')

                            ->label('Productos')
                            ->minItems(1)
                            ->addActionLabel('+ Agregar otro producto')
                            ->dehydrated(true)
                            ->reorderable(false)
                            ->collapsible()
                            ->defaultItems(1)
                            ->columns(1)

                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                self::recalcularTotalFactura($get, $set);
                            })

                            ->schema([
                               Checkbox::make('seleccionado')
                                    ->label('Seleccionar producto para eliminar')
                                    ->inline()
                                    ->extraAttributes([
                                        'class' => 'pt-6 flex justify-end custom-checkbox-wrapper',
                                    ])
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 1,
                                    ]),




                                

                                Grid::make()
                                    ->schema([
                                        Select::make('bodega')
                                            ->label('Bodega')
                                            ->placeholder('Seleccionar bodega')
                                            ->required()
                                            ->options(function (Get $get) {
                                                $user   = auth()->user();
                                                $cedula = $user->cedula ?? null;

                                                $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                                $AaPrin = \App\Models\AaPrin::where('ID_Asesor', $asesor->ID_Asesor)->first();

                                                $columna = 'ID_Sede';
                                                $valores = $AaPrin->ID_Sede;

                                                if ($AaPrin->ID_Sede == null || $AaPrin->ID_Sede == '') {
                                                    $socio    = \App\Models\SocioDistritec::where('Cedula', $cedula)->first();
                                                    $ID_sede  = \App\Models\Sede::where('ID_Socio', $socio->ID_Socio)->first();
                                                    $valores  = $ID_sede?->ID_Sede;
                                                }

                                                $ID_Sede = \App\Models\Sede::where($columna, $valores)->first();

                                                $codigoSucursal = $ID_Sede->Codigo_de_sucursal ?? null;

                                                if (! $codigoSucursal) {
                                                    // Si no hay código de sucursal, solo mostrar las de ID_Sede 49
                                                    return ZBodegaFacturacion::query()
                                                        ->where('ID_Sede', 49)
                                                        ->orderBy('Nombre_Bodega')
                                                        ->pluck('Nombre_Bodega', 'Cod_Bog');
                                                }

                                                $idSede = Sede::where('Codigo_de_sucursal', $codigoSucursal)
                                                    ->value('ID_Sede');

                                                if (! $idSede) {
                                                    // Si no hay ID_Sede, solo mostrar las de ID_Sede 49
                                                    return ZBodegaFacturacion::query()
                                                        ->where('ID_Sede', 49)
                                                        ->orderBy('Nombre_Bodega')
                                                        ->pluck('Nombre_Bodega', 'Cod_Bog');
                                                }

                                                // OBTENER TODAS LAS BODEGAS (del asesor + sede 49) en una sola consulta
                                                $datosCombinados = ZBodegaFacturacion::query()
                                                    ->whereIn('ID_Sede', [$idSede, 49])
                                                    ->orderBy('Nombre_Bodega')
                                                    ->pluck('Nombre_Bodega', 'Cod_Bog');

                                                session(['bodegas_sede_' => $datosCombinados]);

                                                return $datosCombinados;
                                            })
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                $set('codigo_bodega', $state);

                                                $bodega = ZBodegaFacturacion::where('Cod_Bog', $state)->first();

                                                $set('../../codigo_bodega_factura', $bodega?->Cod_Bog);
                                                $set('../../nombre_bodega_factura', $bodega?->Nombre_Bodega);


                                                $set('codigo_producto', null);
                                                $set('nombre_producto', null);
                                                $set('marca', null);
                                                $set('cantidad', null);
                                                $set('precio_unitario_fake', null);
                                                $set('precio_unitario_raw', 0);
                                                $set('subtotal_linea', 0);
                                                
                                                //RESETEAR VARIANTES
                                                $set('variantes', []);
                                                $set('variantes_json', null);
                                                $set('variantes_configuradas', false);
                                                $set('tiene_variantes_disponibles', false);
                                                
                                                //RESETEAR CHECKBOX
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
                                                                console.log('Botón encontrado en intento:', intentos);
                                                                targetButton.click();
                                                            } else if (intentos < 10) {
                                                                console.log('Botón no encontrado, reintentando...', intentos);
                                                                setTimeout(() => intentarClick(intentos + 1), 200);
                                                            } else {
                                                                console.error('No se pudo encontrar el botón después de 10 intentos');
                                                            }
                                                        };

                                                        setTimeout(() => intentarClick(0), 300);
                                                    })
                                                JS
                                            ])
                                            ->searchable()
                                            ->columnSpan([
                                                'default' => 12,
                                                'md' => 6,
                                            ]),

                                        Hidden::make('item_key')
                                            ->default(fn () => uniqid()),

                                        // ✅ FIX: guardar variantes como JSON dentro del item (viaja al siguiente step)
                                        Hidden::make('variantes_json')
                                            ->default(null)
                                            ->dehydrated(true),

                                        TextInput::make('codigo_bodega')
                                            ->label('Código Bodega')
                                            ->columnSpan(6)
                                            ->disabled()
                                            ->suffixAction(
                                                Action::make('buscarProducto')

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
                                                            [&_svg]:!fill-[#82cc0e]
                                                            [&_svg]:!stroke-[#82cc0e]
                                                            animate-pulse
                                                            hover:animate-none
                                                            hover:!bg-[#82cc0e]
                                                            hover:!text-white
                                                            hover:[&_svg]:!text-white
                                                            hover:[&_svg]:!fill-white
                                                            hover:[&_svg]:!stroke-white
                                                            hover:scale-110
                                                            hover:!border-[#82cc0e]
                                                            hover:!shadow-[0_0_25px_#82cc0e]
                                                            active:scale-95
                                                            transition-all
                                                            duration-300
                                                            cursor-pointer
                                                            rounded-full
                                                            !w-12
                                                            !h-12
                                                            filter-none
                                                            backdrop-filter-none
                                                        ',
                                                    ])
                                                    ->modalHeading('')
                                                    ->disabled(fn (Get $get) => empty($get('codigo_bodega')))
                                                    ->fillForm(fn (Get $get): array => [
                                                        'codigo_bodega_modal' => $get('codigo_bodega'),
                                                    ])
                                                    ->form([
                                                        Hidden::make('codigo_bodega_modal'),

                                                        Section::make('')
                                                            ->schema([
                                                                Placeholder::make('tabla_productos')
                                                                    ->label('')
                                                                    ->content(function (Get $get, Set $set) {
                                                                        $codigoBodega = $get('codigo_bodega_modal');

                                                                        Log::info('Debug fuentes de cédula en modal:', [
                                                                            'url'        => request()->query('cedula'),
                                                                            'sess_cedula'=> Session::get('cedula'),
                                                                            'sess_nit'   => Session::get('nuevo_nit'),
                                                                        ]);

                                                                        $cedulaCliente = request()->query('cedula')
                                                                            ?? Session::get('cedula')
                                                                            ?? Session::get('nuevo_nit');

                                                                        $items = $get('items_modal') ?? [];
                                                                        $array_codigos = array_column($items, 'codigo_producto');
                                                                        $array_bodega  = array_column($items, 'codigo_bodega');


                                                                        return new HtmlString(
                                                                            ModalProductosBodega::generarTablaProductos(
                                                                                $codigoBodega,
                                                                                $get('codigo_producto_modal'),
                                                                                $get('busqueda_nombre'),
                                                                                $get('busqueda_marca'),
                                                                                $cedulaCliente,
                                                                                $array_codigos,
                                                                                $array_bodega,
                                                                            )
                                                                        );
                                                                    }),
                                                            ]),

                                                        // Aquí llega el JSON desde Alpine (selected[])
                                                        Hidden::make('productos_seleccionados')
                                                            ->extraAttributes(['data-role' => 'productos-seleccionados']),

                                                        Hidden::make('codigo_bodega_modal'),
                                                        Hidden::make('items_modal')->dehydrated(false),

                                                    ])
                                                    ->action(function (array $data, Get $get, Set $set) {
                                                        // JSON que envía el modal con los productos seleccionados
                                                        $productosJson = $data['productos_seleccionados'] ?? '[]';
                                                        $productos = json_decode($productosJson, true);

                                                        if (! is_array($productos) || count($productos) === 0) {
                                                            Notification::make()
                                                                ->title('Debes seleccionar al menos un producto en la tabla')
                                                                ->danger()
                                                                ->send();

                                                            return;
                                                        }

                                                        // Guardamos cuántos productos venían en total (para el mensaje)
                                                        $totalSeleccionados = count($productos);

                                                        // Estado actual del Repeater "items" (todas las filas)
                                                        $items = $get('../../items') ?? [];
                        

                                                        // Bodega / código de bodega de la fila donde abriste el modal
                                                        $bodega       = $get('bodega');
                                                        $codigoBodega = $get('codigo_bodega');
                                                        $currentKey   = $get('item_key'); // Hidden::make('item_key') en cada fila

                                                        // Buscar el índice de la fila actual dentro del repeater
                                                        $currentIndex = null;
                                                        foreach ($items as $idx => $item) {
                                                            if (($item['item_key'] ?? null) === $currentKey) {
                                                                $currentIndex = $idx;
                                                                break;
                                                            }
                                                        }

                                                        // Tomamos el primer producto de la selección
                                                        $first = array_shift($productos);

                                                        if ($first && $currentIndex !== null) {
                                                            $precio   = (float) ($first['precio'] ?? 0);
                                                            // Si ya había cantidad en la fila, la respetamos; si no, 1
                                                            $cantidad = (float) ($items[$currentIndex]['cantidad'] ?? 1);

                                                            // ✅ FIX: no tocar/romper variantes existentes, solo actualizar campos de producto
                                                            $items[$currentIndex]['bodega']            = $bodega;
                                                            $items[$currentIndex]['codigo_bodega']     = $codigoBodega;
                                                            $items[$currentIndex]['codigo_producto']   = $first['codigo'] ?? null;
                                                            $items[$currentIndex]['nombre_producto']   = $first['nombre'] ?? '';
                                                            $items[$currentIndex]['marca']             = $first['marca'] ?? '';
                                                            $items[$currentIndex]['precio_unitario']   = $precio;
                                                            $items[$currentIndex]['precio_unitario_raw'] = $precio;
                                                            $items[$currentIndex]['subtotal_linea']    = $cantidad * $precio;

                                                            // ✅ Si cambias producto, reinicia variantes (porque ya no corresponden)
                                                            $items[$currentIndex]['variantes'] = $items[$currentIndex]['variantes'] ?? [];
                                                            $items[$currentIndex]['variantes_json'] = $items[$currentIndex]['variantes_json'] ?? null;
                                                            $items[$currentIndex]['variantes_configuradas'] = $items[$currentIndex]['variantes_configuradas'] ?? false;
                                                            $items[$currentIndex]['tiene_variantes_disponibles'] = $items[$currentIndex]['tiene_variantes_disponibles'] ?? false;

                                                        } elseif ($first) {
                                                            // Por si acaso no encontramos la fila (no debería pasar, pero humanos...)
                                                            $precio   = (float) ($first['precio'] ?? 0);
                                                            $cantidad = 1;

                                                            $items[] = [
                                                                'item_key'        => uniqid(),
                                                                'bodega'          => $bodega,
                                                                'codigo_bodega'   => $codigoBodega,
                                                                'codigo_producto' => $first['codigo'] ?? null,
                                                                'nombre_producto' => $first['nombre'] ?? '',
                                                                'marca'           => $first['marca'] ?? '',
                                                                'precio_unitario' => $precio,
                                                                'precio_unitario_raw' => $precio,
                                                                'cantidad'        => $cantidad,
                                                                'subtotal_linea'  => $cantidad * $precio,

                                                                // ✅ FIX: defaults variantes
                                                                'variantes' => [],
                                                                'variantes_json' => null,
                                                                'variantes_configuradas' => false,
                                                                'tiene_variantes_disponibles' => false,
                                                            ];
                                                        }

                                                        // Para los productos restantes, creamos NUEVAS filas
                                                        foreach ($productos as $producto) {
                                                            if (empty($producto['codigo'])) {
                                                                continue;
                                                            }

                                                            $precio   = (float) ($producto['precio'] ?? 0);
                                                            $cantidad = 1;

                                                            $items[] = [
                                                                'item_key'        => uniqid(),
                                                                'bodega'          => $bodega,
                                                                'codigo_bodega'   => $codigoBodega,
                                                                'codigo_producto' => $producto['codigo'],
                                                                'nombre_producto' => $producto['nombre'] ?? '',
                                                                'marca'           => $producto['marca']  ?? '',
                                                                'precio_unitario' => $precio,
                                                                'precio_unitario_raw' => $precio,
                                                                'cantidad'        => $cantidad,
                                                                'subtotal_linea'  => $cantidad * $precio,

                                                                // ✅ FIX: defaults variantes
                                                                'variantes' => [],
                                                                'variantes_json' => null,
                                                                'variantes_configuradas' => false,
                                                                'tiene_variantes_disponibles' => false,
                                                            ];
                                                        }

                                                        // Guardamos TODO el repeater actualizado
                                                        $set('../../items', $items);

                                                        Notification::make()
                                                            ->title('Se agregaron ' . $totalSeleccionados . ' producto(s) a la factura')
                                                            ->success()
                                                            ->send();
                                                    })
                                                    ->fillForm(fn (Get $get): array => [
                                                        'codigo_bodega_modal' => $get('codigo_bodega'),
                                                        'items_modal' => $get('../../items') ?? [], 
                                                    ])


                                                    ->closeModalByClickingAway(false)
                                                    ->modalSubmitAction(function ($action) {
                                                        return $action
                                                            ->label('Aceptar')
                                                            ->extraAttributes([
                                                                'class' => 'fi-hidden-submit hidden',
                                                            ]);
                                                    })
                                                    ->modalCancelAction(function ($action) {
                                                        return $action
                                                            ->label('Cancelar')
                                                            ->extraAttributes([
                                                                'class' => 'fi-hidden-cancel hidden',
                                                            ]);
                                                    })
                                            )


                                    ])
                                    ->columns(12),

                                Grid::make()
                                ->schema([
                                    TextInput::make('codigo_producto')
                                        ->label('Código del Producto')
                                        ->readOnly()
                                        ->maxLength(255)
                                        ->required()
                                        ->columnSpan([
                                            'default' => 12,
                                            'md' => 8,
                                        ])
                                        ->visible(fn (Get $get) => !empty($get('codigo_producto'))),

                                    TextInput::make('nombre_producto')
                                        ->label('Nombre del Producto')
                                        ->readOnly()
                                        ->maxLength(255)
                                        ->columnSpan([
                                            'default' => 12,
                                            'md' => 8,
                                        ])
                                        ->visible(fn (Get $get) => !empty($get('nombre_producto'))),

                                    TextInput::make('marca')
                                        ->label('Marca del Producto')
                                        ->readOnly()
                                        ->maxLength(100)
                                        ->columnSpan([
                                            'default' => 12,
                                            'md' => 4,
                                        ])
                                        ->visible(fn (Get $get) => !empty($get('marca'))),
                                ])
                                ->columns(12)
                                ->visible(fn (Get $get) =>
                                    !empty($get('codigo_producto')) ||
                                    !empty($get('nombre_producto')) ||
                                    !empty($get('marca')) ||
                                    !empty($get('cantidad'))
                                ),

                                Hidden::make('variantes_configuradas')
                                    ->default(false)
                                    ->dehydrated(true),

                                Hidden::make('tiene_variantes_disponibles')
                                    ->default(false)
                                    ->dehydrated(true),

                                Grid::make()
                                    ->schema([
                                        Grid::make()
                                            ->schema([
                                                TextInput::make('cantidad')
                                                ->label('Cantidad')
                                                ->numeric()
                                                ->minValue(1)
                                                ->required()
                                                ->default(null)
                                                ->placeholder('Ingrese la cantidad')
                                                ->live()
                                                ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                    $cantidad = (int) $state;

                                                    if ($cantidad <= 0) {
                                                        $set('cantidad', null);
                                                        $set('variantes', []);
                                                        $set('variantes_json', null); // ✅ FIX
                                                        $set('variantes_configuradas', false);
                                                        $set('tiene_variantes_disponibles', false);
                                                        return;
                                                    }

                                                    // OBTENER DISPONIBLES EN BODEGA
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

                                                            // VALIDACIÓN CRÍTICA: Cantidad NO puede ser mayor a disponibles
                                                            if ($cantidad > $totalDisponibles) {
                                                                $cantidadAjustada = $totalDisponibles > 0 ? $totalDisponibles : null;
                                                                $set('cantidad', $cantidadAjustada);

                                                                Notification::make()
                                                                    ->title('Cantidad excede disponibilidad')
                                                                    ->body("Solo hay {$totalDisponibles} unidad(es) disponible(s) en esta bodega. La cantidad se ajustó automáticamente.")
                                                                    ->warning()
                                                                    ->duration(5000)
                                                                    ->send();

                                                                $cantidad = $totalDisponibles;
                                                            }

                                                            // Verificar si hay variantes disponibles
                                                            $htmlVariantes = ModalProductosVariante::generarListaProductosVariantes(
                                                                $cantidad,
                                                                $codigoProducto,
                                                                $codigoBodega
                                                            );

                                                            $hayVariantes = !str_contains($htmlVariantes, 'Sin variantes disponibles');
                                                            $set('tiene_variantes_disponibles', $hayVariantes);

                                                            if (!$hayVariantes) {
                                                                // ✅ si no hay variantes, limpiar estado de variantes
                                                                $set('variantes', []);
                                                                $set('variantes_json', null);
                                                                $set('variantes_configuradas', false);

                                                                Notification::make()
                                                                    ->title('Sin variantes disponibles')
                                                                    ->body('No hay variantes disponibles para este producto en esta bodega. No podrás configurar variantes.')
                                                                    ->warning()
                                                                    ->duration(5000)
                                                                    ->send();
                                                            }

                                                        } catch (\Exception $e) {
                                                            \Log::error('Error validando disponibilidad: ' . $e->getMessage());
                                                            $set('tiene_variantes_disponibles', false);
                                                        }
                                                    }

                                                    $precio = (float) ($get('precio_unitario_raw') ?? 0);
                                                    $set('subtotal_linea', $cantidad * $precio);

                                                    $variantesActuales = $get('variantes') ?? [];

                                                    if (!empty($variantesActuales) && count($variantesActuales) !== $cantidad) {
                                                        $set('variantes', []);
                                                        $set('variantes_json', null); // ✅ FIX
                                                        $set('variantes_configuradas', false);

                                                        Notification::make()
                                                            ->title('Variantes reiniciadas')
                                                            ->body('Debes reconfigurar las variantes según la nueva cantidad')
                                                            ->warning()
                                                            ->send();
                                                    }

                                                    self::recalcularTotalFactura($get, $set);
                                                })
                                                ->columnSpan([
                                                    'default' => 12,
                                                    'md' => 4,
                                                ])
                                                ->visible(fn (Get $get) => !empty($get('marca'))),

                                                // OJO: Esto estaba duplicado en tu código original.
                                                // No lo borro porque pediste "sin eliminar nada", pero lo dejo igual y dehydrated(true).
                                                Hidden::make('variantes_configuradas')
                                                    ->default(false)
                                                    ->dehydrated(true),

                                                Grid::make()
                                                ->schema([
                                                    // Indicador de estado
                                                    Placeholder::make('estado_variantes')
                                                    ->label('Variantes')
                                                    ->content(function (Get $get) {
                                                        $cantidad = (int) ($get('cantidad') ?? 0);
                                                        $variantes = $get('variantes') ?? [];
                                                        $configuradas = $get('variantes_configuradas') ?? false;
                                                        $tieneVariantesDisponibles = $get('tiene_variantes_disponibles') ?? false;

                                                        if ($cantidad === 0) {
                                                            return new HtmlString('
                                                                <div class="fi-input-wrapper flex items-center gap-2 rounded-lg shadow-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                                    <svg class="w-5 h-5 text-gray-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                    </svg>
                                                                    <span class="text-sm text-gray-500">
                                                                        Ingresa una cantidad para configurar variantes
                                                                    </span>
                                                                </div>
                                                            ');
                                                        }

                                                        // SI NO HAY VARIANTES DISPONIBLES, mostrar mensaje diferente
                                                        if (! $tieneVariantesDisponibles) {
                                                            return new HtmlString('
                                                                <div class="fi-input-wrapper flex items-center gap-2 rounded-lg shadow-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                    </svg>
                                                                    <span class="text-sm text-red-500">
                                                                        <strong>Sin variantes:</strong> No hay variantes disponibles para este producto en esta bodega
                                                                    </span>
                                                                </div>
                                                            ');
                                                        }

                                                        if (! $configuradas) {
                                                            return new HtmlString('
                                                                <div class="fi-input-wrapper flex items-center gap-2 rounded-lg shadow-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                                    </svg>
                                                                    <span class="text-sm text-yellow-500">
                                                                        <strong>Requerido:</strong> Debes configurar <strong>' . $cantidad . '</strong> variante(s) para continuar
                                                                    </span>
                                                                </div>
                                                            ');
                                                        }

                                                        return new HtmlString('
                                                            <div class="fi-input-wrapper flex items-center gap-2 rounded-lg shadow-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                                <span class="text-sm text-green-500">
                                                                    <strong>' . count($variantes) . '</strong> variante(s) configurada(s) correctamente
                                                                </span>
                                                            </div>
                                                        ');

                                                    })
                                                    ->columnSpan(8),
                                                    // NUEVO: Contador de variantes disponibles
                                                    Placeholder::make('variantes_disponibles')
                                                        ->label('Disponibles')
                                                        ->content(function (Get $get) {
                                                            $codigoBodega = $get('codigo_bodega') ?? '';
                                                            $codigoProducto = $get('codigo_producto') ?? '';
                                                            $cantidad = (int) ($get('cantidad') ?? 0);

                                                            $controller = new \App\Http\Controllers\Api\ProductosController(
                                                                    new \App\Services\ConsignacionService()
                                                                );

                                                            $request = new \Illuminate\Http\Request([
                                                                'codigoProducto' => $codigoProducto,
                                                                'codigo_bodega' => $codigoBodega,
                                                            ]);

                                                            $response = $controller->productoDisponibles($request);
                                                            $totalDisponibles = is_array($response) ? count($response) : 0;

                                                            if (empty($codigoBodega) || empty($codigoProducto)) {
                                                                return new HtmlString('
                                                                    <div class="text-center text-gray-500 dark:text-gray-400 p-3">
                                                                        <span class="text-2xl">📦</span>
                                                                        <p class="text-sm mt-1">--</p>
                                                                    </div>
                                                                ');
                                                            }

                                                            try {
                                                                $productoValidacion = ModalProductosVariante::validarEnConsignaciones(
                                                                    $cantidad,//cantidadSeleccionada
                                                                    $codigoProducto,//codigoProducto
                                                                    $codigoBodega,//codigo_bodega
                                                                    "0",//codigoVariante (por defecto para validar solo productos sin variante)
                                                                    $totalDisponibles,//existenciaDisponible
                                                                );
                                                                try {
                                                                    Notification::make()
                                                                        ->title('Atención')
                                                                        ->body($productoValidacion['mensaje'] )
                                                                        ->actions([
                                                                            \Filament\Notifications\Actions\Action::make('ir_consignaciones')
                                                                                ->label('Ir a Consignaciones')
                                                                                ->color('primary')
                                                                                ->button()
                                                                                ->url($productoValidacion['url'] ?? '', true) // true para abrir en nueva pestaña
                                                                        ])
                                                                        ->persistent()
                                                                        ->warning()
                                                                        ->send();
 
                                                                } catch (\Throwable $th) {
                                                                    \Log::error($productoValidacion['error']);

                                                                }

                                                                $color = $totalDisponibles >= $cantidad ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';

                                                                return new HtmlString('
                                                                    <div class="text-center p-3">
                                                                        <span class="text-2xl">📦</span>
                                                                        <p class="text-2xl font-bold ' . $color . ' mt-1">' . $totalDisponibles . '</p>
                                                                        <p class="text-xs text-gray-500 dark:text-gray-400">en bodega</p>
                                                                    </div>
                                                                ');

                                                            } catch (\Exception $e) {
                                                                \Log::error('Error obteniendo variantes: ' . $e->getMessage());

                                                                return new HtmlString('
                                                                    <div class="text-center text-red-500 p-3">
                                                                        <span class="text-2xl">⚠️</span>
                                                                        <p class="text-xs mt-1">Error</p>
                                                                    </div>
                                                                ');
                                                            }
                                                        })
                                                        ->columnSpan(2)
                                                        ->hidden(),

                                                        // Botón para abrir el modal
                                                        Actions::make([
                                                            Action::make('configurarVariantes')
                                                                ->label(fn (Get $get) => ($get('variantes_configuradas') ? 'Modificar Variantes' : 'Configurar Variantes'))
                                                                ->icon(fn (Get $get) => ($get('variantes_configuradas') ? 'heroicon-o-pencil-square' : 'heroicon-o-plus-circle'))
                                                                ->color(fn (Get $get) => (int) ($get('cantidad') ?? 0) === 0 ? 'gray' : (($get('variantes_configuradas') ? 'gray' : 'primary')))
                                                                ->disabled(fn (Get $get) => (int) ($get('cantidad') ?? 0) === 0 || !$get('tiene_variantes_disponibles')) // AQUÍ EL CAMBIO
                                                                ->modalWidth('5xl')
                                                                ->form([
                                                                    Hidden::make('cantidad_requerida'),
                                                                    Hidden::make('codigo_bodega_requerido'),
                                                                    Hidden::make('codigo_producto_requerido'),

                                                                    Section::make('')
                                                                        ->schema([
                                                                            Placeholder::make('selector_variantes')
                                                                                ->label('')
                                                                                ->content(function (Get $get) {
                                                                                    $cantidad = (int) ($get('cantidad_requerida') ?? 0);
                                                                                    $codigoBodega = (string) ($get('codigo_bodega_requerido') ?? 0);
                                                                                    $codigoProducto = (string) ($get('codigo_producto_requerido') ?? " ");

                                                                                    return new HtmlString(
                                                                                        ModalProductosVariante::generarListaProductosVariantes($cantidad, $codigoProducto, $codigoBodega)
                                                                                    );
                                                                                }),

                                                                        ]),

                                                                        // Después del Actions::make de configurarVariantes
                                                                        Hidden::make('variantes_validacion')
                                                                            ->default(fn (Get $get) => $get('variantes_configuradas') ? 'configuradas' : null)
                                                                            ->live()
                                                                            ->rules([
                                                                                function (Get $get) {
                                                                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                                                        $tieneVariantesDisponibles = $get('tiene_variantes_disponibles') ?? false;
                                                                                        $variantesConfiguradas = $get('variantes_configuradas') ?? false;
                                                                                        $cantidad = (int) ($get('cantidad') ?? 0);

                                                                                        // Si hay variantes disponibles y cantidad > 0, entonces deben estar configuradas
                                                                                        if ($tieneVariantesDisponibles && $cantidad > 0 && !$variantesConfiguradas) {
                                                                                            $fail('Debes configurar las variantes antes de continuar.');
                                                                                        }
                                                                                    };
                                                                                },
                                                                            ])
                                                                            ->dehydrated(false),

                                                                    // Input hidden que recibe el JSON
                                                                    Hidden::make('variantes_seleccionadas')
                                                                        ->extraAttributes(['data-role' => 'variantes-seleccionadas']),
                                                                ])
                                                                ->fillForm(fn (Get $get): array => [
                                                                    'cantidad_requerida' => (int) ($get('cantidad') ?? 0),
                                                                    'codigo_bodega_requerido' => (string) ($get('codigo_bodega') ?? 0),
                                                                    'codigo_producto_requerido' => (string) ($get('codigo_producto') ?? " "),
                                                                ])
                                                                ->action(function (array $data, Get $get, Set $set) {
                                                                    // Obtener el JSON de variantes seleccionadas
                                                                    $variantesJson = $data['variantes_seleccionadas'] ?? '[]';
                                                                    $variantes = json_decode($variantesJson, true);

                                                                    if (!is_array($variantes) || count($variantes) === 0) {
                                                                        Notification::make()
                                                                            ->title('Debes seleccionar al menos una variante')
                                                                            ->danger()
                                                                            ->send();
                                                                        return;
                                                                    }

                                                                    $cantidadRequerida = (int) ($data['cantidad_requerida'] ?? 0);

                                                                    if (count($variantes) !== $cantidadRequerida) {
                                                                        Notification::make()
                                                                            ->title('Selección incorrecta')
                                                                            ->body("Debes seleccionar EXACTAMENTE {$cantidadRequerida} variante(s). Actualmente tienes " . count($variantes) . " seleccionada(s).")
                                                                            ->danger()
                                                                            ->duration(6000)
                                                                            ->send();
                                                                        return;
                                                                    }

                                                                    Log::info('Procesando variantes seleccionadas:', [
                                                                        'cantidad_requerida' => $cantidadRequerida,
                                                                        'cantidad_recibida' => count($variantes),
                                                                        'variantes' => $variantes
                                                                    ]);

                                                                    // Mapear las variantes al formato del Repeater
                                                                    $variantesMapeadas = [];
                                                                    foreach ($variantes as $index => $variante) {
                                                                        $variantesMapeadas[] = [
                                                                            'numero' => $index + 1,
                                                                            'codigo' => $variante['codigo'] ?? '',
                                                                            'descripcion' => $variante['descripcion'] ?? '',
                                                                            'marca' => $variante['marca'] ?? '',
                                                                        ];
                                                                    }

                                                                    // Guardar en el estado del componente
                                                                    $set('variantes', $variantesMapeadas);
                                                                    $set('variantes_configuradas', true);

                                                                    // ✅ FIX CLAVE: guardar JSON dentro del item para que viaje al siguiente step
                                                                    $set('variantes_json', json_encode($variantesMapeadas, JSON_UNESCAPED_UNICODE));

                                                                    Log::info('Variantes guardadas correctamente:', [
                                                                        'total' => count($variantesMapeadas),
                                                                        'datos' => $variantesMapeadas
                                                                    ]);

                                                                    Notification::make()
                                                                        ->title('Variantes configuradas exitosamente')
                                                                        ->body('Se han configurado ' . count($variantesMapeadas) . ' variante(s) correctamente')
                                                                        ->success()
                                                                        ->duration(4000)
                                                                        ->send();
                                                                })
                                                                ->modalSubmitActionLabel('Aceptar')
                                                                ->modalSubmitAction(function ($action) {
                                                                    return $action
                                                                        ->label('Aceptar')
                                                                        ->extraAttributes([
                                                                            'class' => 'fi-hidden-submit hidden',
                                                                        ]);
                                                                })
                                                                ->modalCancelAction(function ($action) {
                                                                    return $action
                                                                        ->label('Cancelar')
                                                                        ->extraAttributes([
                                                                            'class' => 'fi-hidden-cancel hidden',
                                                                        ]);
                                                                })
                                                                ->closeModalByClickingAway(false),
                                                        ])
                                                        ->columnSpan(4),
                                                    ])
                                                    ->columns(12)
                                                    ->columnSpan(8),

                                                // Botón compacto para ver variantes + Repeater oculto para datos
                                                Grid::make()
                                                    ->schema([
                                                        // Indicador compacto con botón para ver detalles
                                                        Actions::make([
                                                            Action::make('verVariantes')
                                                                ->label(fn (Get $get) => count($get('variantes') ?? []) . ' variante(s) configuradas')
                                                                ->icon('heroicon-o-eye')
                                                                ->color('success')
                                                                ->badge(fn (Get $get) => count($get('variantes') ?? []))
                                                                ->badgeColor('success')
                                                                ->modalHeading('Variantes Configuradas')
                                                                ->modalWidth('xl')
                                                                ->closeModalByClickingAway(false)
                                                                ->modalCloseButton(true)
                                                                ->modalContent(function (Get $get) {
                                                                    $variantes = $get('variantes') ?? [];

                                                                    if (empty($variantes)) {
                                                                        return new HtmlString('
                                                                            <div class="text-center py-12">
                                                                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                                                                </svg>
                                                                                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No hay variantes configuradas</p>
                                                                            </div>
                                                                        ');
                                                                    }

                                                                    $total = count($variantes);

                                                                    $html = '<div class="space-y-4">';

                                                                    foreach ($variantes as $variante) {
                                                                        $numero = $variante['numero'] ?? '';
                                                                        $codigo = $variante['codigo'] ?? '';

                                                                        $html .= '
                                                                            <div class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-colors">
                                                                                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold">
                                                                                    ' . $numero . '
                                                                                </div>
                                                                                <div class="flex-1 min-w-0">
                                                                                    <p class="font-mono text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                                                                        ' . htmlspecialchars($codigo) . '
                                                                                    </p>
                                                                                </div>
                                                                            </div>
                                                                        ';
                                                                    }

                                                                    $html .= '</div>';

                                                                    // Resumen simple
                                                                    $html .= '
                                                                        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                                                                            <div class="flex items-center justify-between text-sm">
                                                                                <span class="font-medium text-gray-600 dark:text-gray-400">Total:</span>
                                                                                <span class="font-bold text-gray-900 dark:text-gray-100 text-lg">' . $total . ' variante(s)</span>
                                                                            </div>
                                                                        </div>
                                                                    ';

                                                                    return new HtmlString($html);
                                                                })
                                                                ->modalCancelAction(false)
                                                                ->modalSubmitAction(function ($action) {
                                                                    return $action
                                                                        ->label('Cerrar')
                                                                        ->color('gray');
                                                                })
                                                                ->action(fn () => null),
                                                        ])
                                                        ->hidden(fn (Get $get) => !($get('variantes_configuradas') ?? false))
                                                        ->columnSpan(12),

                                                        // Repeater oculto para guardar los datos
                                                        // ✅ FIX: aunque esté oculto, DEBE viajar en el submit.
                                                        Repeater::make('variantes')
                                                            ->schema([
                                                                Hidden::make('numero')->dehydrated(true),
                                                                Hidden::make('codigo')->dehydrated(true),
                                                                Hidden::make('descripcion')->dehydrated(true),
                                                                Hidden::make('marca')->dehydrated(true),
                                                            ])
                                                            ->defaultItems(0)
                                                            ->addable(false)
                                                            ->deletable(false)
                                                            ->reorderable(false)
                                                            ->columnSpan(12)
                                                            ->dehydrated(true)
                                                            ->dehydratedWhenHidden()
                                                            ->hidden(true),
                                                    ])
                                                    ->columns(12)
                                                    ->columnSpan(12),
                                            ])
                                            ->columns(12),


                                        TextInput::make('precio_unitario_fake')
                                            ->label('Precio Unitario')
                                            ->prefix('$')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->dehydrated(false)
                                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                    $valorLimpio = preg_replace('/[^\d]/', '', $state);
                                                    $precio = (float) $valorLimpio;
                                                    $set('precio_unitario_raw', $precio);

                                                    $cantidad = (float) ($get('cantidad') ?? 0);
                                                    $subtotal = $cantidad * $precio;

                                                    $set('subtotal_linea', $subtotal);

                                                    $set('precio_unitario_fake', number_format($precio, 0, ',', '.'));

                                                    self::recalcularTotalFactura($get, $set);
                                                })
                                            ->columnSpan([
                                                'default' => 12,
                                                'sm' => 4,
                                            ]),

                                        Hidden::make('precio_unitario_raw')
                                            ->default(0)
                                            ->dehydrated(true),

                                         Placeholder::make('subtotal_linea_display')
                                            ->label('Subtotal')
                                            ->content(function (Get $get) {
                                                $subtotal = (float) ($get('subtotal_linea') ?? 0);
                                                $formateado = number_format($subtotal, 0, ',', '.');

                                                return new HtmlString('
                                                    <div class="fi-input-wrapper flex items-center gap-2 rounded-lg shadow-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 px-3 py-2">
                                                        <span class="text-gray-500 dark:text-gray-400">$</span>
                                                        <span class="font-bold text-lg text-gray-900 dark:text-white">' . $formateado . '</span>
                                                    </div>
                                                ');
                                            })
                                            ->columnSpan([
                                                'default' => 12,
                                                'sm' => 4,
                                            ]),

                                        Hidden::make('subtotal_linea')
                                            ->default(0)
                                            ->dehydrated(false),
                                    ])
                                    ->columns(12),

                            ]),



                        Actions::make([
                            Action::make('eliminarSeleccionados')
                                ->label('Eliminar seleccionados')
                                ->color('danger')
                                ->icon('heroicon-o-trash')
                                ->requiresConfirmation()
                                ->action(function (Get $get, Set $set) {
                                    $items = $get('items') ?? [];

                                    // Filtramos los que NO están seleccionados
                                    $itemsFiltrados = array_values(
                                        array_filter($items, fn ($item) => empty($item['seleccionado']))
                                    );

                                    // Seteamos de nuevo el repeater con los items restantes
                                    $set('items', $itemsFiltrados);
                                }),
                        ])
                        ->alignment('right'),

                    ])
                    ->extraAttributes([
                        'class' =>
                            'bg-white dark:bg-gray-900 rounded-xl shadow-sm ' .
                            'p-6 border border-gray-200 dark:border-gray-700',
                    ]),
            ]);
    }

     /**
     * Recalcula el total de la factura sumando todos los items
     */
    protected static function recalcularTotalFactura(Get $get, Set $set)
    {
        $items = $get('items') ?? [];
        $tipo_venta = $get("Tipo_venta");
        $medio_pago = $get("medio_pago");
        $total = 0;

        foreach ($items as $item) {
            $cantidad = (float) ($item['cantidad'] ?? 0);
            // CRÍTICO: Usar precio_unitario_raw para cálculos
            $precio = (float) ($item['precio_unitario_raw'] ?? $item['precio_unitario'] ?? 0);
            $total += $cantidad * $precio;
        }

        // Total que usas en otros pasos
        $set('../../total_factura', $total);
        $set('../../valor_total', $total);

        // Leer el cupo
        $cupo = (int) ($get('../../cartera_cupo_disponible') ?? 0);

        $set('puede_avanzar', true);
        // Validar cupo
        if ($cupo > 0 && $total > $cupo) {
            if ($tipo_venta == 3 || $medio_pago == 8) {
                $set('puede_avanzar', false);
                $cupoFormateado = number_format($cupo, 0, ',', '.');
                $totalFormateado = number_format($total, 0, ',', '.');

                $mensaje = "El valor de la factura supera el cupo disponible.\n" .
                    "Cupo disponible: $" . $cupoFormateado . "\n" .
                    "Valor de la factura: $" . $totalFormateado . "\n" .
                    "Comunícate con el área de cartera.";

                Notification::make()
                    ->title('Error: Límite de Cupo Superado')
                    ->body($mensaje)
                    ->danger()
                    ->duration(8000)
                    ->send();

                $set('../../factura_supera_cupo', true);
            }
            
        } else {
            $set('../../factura_supera_cupo', false);
        }
    }
}










// ==========================================
// CLASE HELPER - Agregar al final del archivo
// ==========================================

/*class VariantesModalHelper
{
    public static function obtenerProductosDisponibles()
    {
        return [
            [ 'codigo' => 'IMEI001234567890', 'descripcion' => 'REDMI 15 - IMEI: 001234567890', 'marca' => 'Xiaomi' ],
            [ 'codigo' => 'IMEI001234567891', 'descripcion' => 'REDMI 15 - IMEI: 001234567891', 'marca' => 'Xiaomi' ],
            [ 'codigo' => 'IMEI001234567892', 'descripcion' => 'REDMI 15 - IMEI: 001234567892', 'marca' => 'Xiaomi' ],
            [ 'codigo' => 'IMEI001234567893', 'descripcion' => 'REDMI 15 - IMEI: 001234567893', 'marca' => 'Xiaomi' ],
            [ 'codigo' => 'IMEI001234567894', 'descripcion' => 'REDMI 15 - IMEI: 001234567894', 'marca' => 'Xiaomi' ],
            [ 'codigo' => 'SERIAL_LAP001', 'descripcion' => 'LAPTOP HP - Serial: LAP001', 'marca' => 'HP' ],
            [ 'codigo' => 'SERIAL_LAP002', 'descripcion' => 'LAPTOP HP - Serial: LAP002', 'marca' => 'HP' ],
            [ 'codigo' => 'SERIAL_LAP003', 'descripcion' => 'LAPTOP HP - Serial: LAP003', 'marca' => 'HP' ],
        ];
    }


    public static function generarListaProductosVariantesV3(array $seleccionadas, string $busqueda = '')
    {
        $productos = self::obtenerProductosDisponibles($busqueda);

        $html = '<div class="var-list-container" data-selected="' . htmlspecialchars(json_encode($seleccionadas)) . '">';

        foreach ($productos as $producto) {
            $codigo = $producto['codigo'];
            $nombre = $producto['descripcion'];

            $html .= "
                <div
                    class='var-item cursor-pointer p-2 border rounded-lg mb-2 hover:bg-gray-100'
                    data-code='{$codigo}'
                >
                    <strong>{$codigo}</strong><br>
                    <span>{$nombre}</span>
                </div>
            ";
        }

        $html .= '</div>';

        return $html;
    }
}*/

namespace App\Livewire;

use Livewire\Component;

class VariantesSelector extends Component
{
    public $variantes = [];
    public $cantidadRequerida = 2;

    protected $listeners = [
        'variantesUpdated' => 'onVariantesUpdated',
    ];

    public function onVariantesUpdated($seleccionadas)
    {
        $this->variantes = is_string($seleccionadas)
            ? json_decode($seleccionadas, true)
            : $seleccionadas;

        if (!is_array($this->variantes)) {
            $this->variantes = [];
        }

        $this->validateVariantes();
    }

    public function validateVariantes()
    {
        if (count($this->variantes) !== $this->cantidadRequerida) {
            $this->addError(
                'variantes',
                "Debes seleccionar EXACTAMENTE {$this->cantidadRequerida} productos. Actualmente tienes " . count($this->variantes) . "."
            );
        } else {
            $this->resetErrorBag('variantes');
        }
    }

    public function render()
    {
        return view('livewire.variantes-selector');
    }
}
