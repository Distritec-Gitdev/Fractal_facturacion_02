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
                                Forms\Components\TextInput::make('codigo_asesor')
                                    ->label('Código del Asesor')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default(function () {
                                        $user = auth()->user();
                                        $cedula = $user->cedula ?? null;
                                        $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                        return $asesor?->ID_Asesor ?? 'N/A';
                                    }),

                                Forms\Components\TextInput::make('nombre_asesor')
                                    ->label('Nombre del Asesor')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default(function () {
                                        $user = auth()->user();
                                        return $user->name ?? 'N/A';
                                    }),

                                Forms\Components\TextInput::make('sede_nombre')
                                    ->label('Sede')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default(function () {
                                        $user = auth()->user();
                                        $cedula = $user->cedula ?? null;

                                        $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                        
                                        if (!$asesor) {
                                            return 'N/A';
                                        }

                                        $AaPrin = \App\Models\AaPrin::where('ID_Asesor', $asesor->ID_Asesor)->first();

                                        $valores = $AaPrin->ID_Sede;

                                        if ($AaPrin->ID_Sede == null || $AaPrin->ID_Sede == '') {
                                            $socio = \App\Models\SocioDistritec::where('Cedula', $cedula)->first();
                                            $ID_sede = \App\Models\Sede::where('ID_Socio', $socio->ID_Socio)->first();
                                            $valores = $ID_sede?->ID_Sede;
                                        }

                                        $ID_Sede = \App\Models\Sede::where('ID_Sede', $valores)->first();
                                        return $ID_Sede?->Nombre ?? 'N/A';
                                    }),
                            ]),

                        Forms\Components\Hidden::make('vendedor_id')
                            ->default(function () {
                                $user = auth()->user();
                                $cedula = $user->cedula ?? null;
                                $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                return $asesor?->ID_Asesor;
                            }),
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
                                Forms\Components\TextInput::make('cedula_cliente')
                                    ->label('Cédula del Cliente')
                                    ->required()
                                    ->numeric()
                                    ->placeholder('Ej: 1234567890')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $cliente = \App\Models\ClienteConsignacion::where('cedula', $state)->first();
                                            
                                            if ($cliente) {
                                                $set('id_cliente', $cliente->id_cliente);
                                                
                                                $nombreCompleto = trim(implode(' ', array_filter([
                                                    $cliente->nombre1_cliente ?? null,
                                                    $cliente->nombre2_cliente ?? null,
                                                    $cliente->apellido1_cliente ?? null,
                                                    $cliente->apellido2_cliente ?? null,
                                                ])));
                                                
                                                $set('nombre_cliente', $nombreCompleto ?: 'Cliente sin nombre');
                                            } else {
                                                $set('id_cliente', null);
                                                $set('nombre_cliente', 'Cliente no encontrado');
                                                
                                                Notification::make()
                                                    ->title('Cliente no encontrado')
                                                    ->warning()
                                                    ->body('No existe un cliente con la cédula: ' . $state)
                                                    ->send();
                                            }
                                        }
                                    })
                                    ->helperText('Ingrese la cédula para buscar el cliente'),

                                Forms\Components\TextInput::make('nombre_cliente')
                                    ->label('Nombre del Cliente')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('Se cargará automáticamente'),
                            ]),

                        Forms\Components\Hidden::make('id_cliente')
                            ->required(),
                    ])
                    ->columns(1)
                    ->collapsible(),

                // ============================================
                // SECCIÓN 3: SELECCIÓN DE BODEGA Y PRODUCTOS
                // ============================================
                Forms\Components\Section::make('Selección de Bodega y Productos')
                    ->description('Seleccione la bodega y busque los productos a consignar')
                    ->icon('heroicon-o-cube')
                    ->schema([
                        // Campos hidden necesarios para el modal
                        Forms\Components\Hidden::make('codigo_bodega'),
                        Forms\Components\Hidden::make('nombre_bodega'),
                        Forms\Components\Hidden::make('codigo_bodega_modal'),
                        Forms\Components\Hidden::make('codigo_producto_modal'),
                        Forms\Components\Hidden::make('busqueda_nombre'),
                        Forms\Components\Hidden::make('busqueda_marca'),
                        Forms\Components\Hidden::make('productos_seleccionados')
                            ->extraAttributes(['data-role' => 'productos-seleccionados']),

                        // SELECT DE BODEGA
                        Forms\Components\Grid::make(2)
                            ->schema([
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
                                                ->orderBy('Nombre_Bog')
                                                ->pluck('Nombre_Bog', 'Cod_Bog');
                                        }

                                        $idSede = \App\Models\Sede::where('Codigo_de_sucursal', $codigoSucursal)
                                            ->value('ID_Sede');

                                        if (!$idSede) {
                                            return \App\Models\ZBodegaFacturacion::query()
                                                ->where('ID_Sede', 49)
                                                ->orderBy('Nombre_Bog')
                                                ->pluck('Nombre_Bog', 'Cod_Bog');
                                        }

                                        $datosCombinados = \App\Models\ZBodegaFacturacion::query()
                                            ->whereIn('ID_Sede', [$idSede, 49])
                                            ->orderBy('Nombre_Bog')
                                            ->pluck('Nombre_Bog', 'Cod_Bog');

                                        return $datosCombinados;
                                    })
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        $set('codigo_bodega', $state);

                                        $bodega = \App\Models\ZBodegaFacturacion::where('Cod_Bog', $state)->first();
                                        $set('nombre_bodega', $bodega?->Nombre_Bog);

                                        // Actualizar el campo display
                                        $set('codigo_bodega_display', $state);
                                        $set('codigo_bodega_modal', $state);

                                        // Limpiar campos de producto cuando cambie la bodega
                                        $set('codigo_producto', null);
                                        $set('nombre_producto', null);
                                        $set('marca_producto', null);
                                        $set('precio_unitario', null);
                                        $set('producto_id', null);
                                        
                                        // Limpiar búsquedas
                                        $set('codigo_producto_modal', null);
                                        $set('busqueda_nombre', null);
                                        $set('busqueda_marca', null);
                                    })
                                    ->extraAttributes([
                                        'x-data' => '{}',
                                        'x-on:change' => <<<'JS'
                                            $nextTick(() => {
                                                console.log('Bodega seleccionada, buscando botón...');
                                                
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
                                                        console.log('✅ Botón encontrado en intento:', intentos);
                                                        // Hacer scroll al botón para que sea visible
                                                        targetButton.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                                    } else if (intentos < 10) {
                                                        console.log('⏳ Botón no encontrado, reintentando...', intentos);
                                                        setTimeout(() => intentarClick(intentos + 1), 200);
                                                    } else {
                                                        console.error('❌ No se pudo encontrar el botón después de 10 intentos');
                                                    }
                                                };

                                                setTimeout(() => intentarClick(0), 300);
                                            })
                                        JS
                                    ])
                                    ->searchable(),

                                Forms\Components\TextInput::make('nombre_bodega_display')
                                    ->label('Nombre Bodega')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default(fn (Forms\Get $get) => $get('nombre_bodega')),
                            ]),

                        // CAMPO DE CÓDIGO DE BODEGA CON BOTÓN PARA BUSCAR PRODUCTOS
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\TextInput::make('codigo_bodega_display')
                                    ->label('Código Bodega')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->live()
                                    ->default(fn (Forms\Get $get) => $get('codigo_bodega'))
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
                                            ->modalHeading('Buscar Productos')
                                            ->modalWidth('7xl')
                                            ->disabled(fn (Forms\Get $get) => empty($get('codigo_bodega')))
                                            ->before(function (Forms\Get $get, Forms\Set $set) {
                                                // Asegurarse de que el modal tenga el código correcto
                                                $codigoBodega = $get('codigo_bodega');
                                                $set('codigo_bodega_modal', $codigoBodega);
                                                
                                                \Log::info('🔍 Preparando modal:', [
                                                    'codigo_bodega' => $codigoBodega,
                                                    'cedula_cliente' => $get('cedula_cliente'),
                                                ]);
                                            })
                                            ->form([
                                                Forms\Components\Grid::make(3)
                                                    ->schema([
                                                        Forms\Components\TextInput::make('codigo_producto_modal')
                                                            ->label('Código de Producto')
                                                            ->placeholder('Buscar por código')
                                                            ->live(debounce: 500),

                                                        Forms\Components\TextInput::make('busqueda_nombre')
                                                            ->label('Nombre del Producto')
                                                            ->placeholder('Buscar por nombre')
                                                            ->live(debounce: 500),

                                                        Forms\Components\TextInput::make('busqueda_marca')
                                                            ->label('Marca')
                                                            ->placeholder('Buscar por marca')
                                                            ->live(debounce: 500),
                                                    ]),

                                                Forms\Components\Section::make('')
                                                    ->schema([
                                                        Forms\Components\Placeholder::make('tabla_productos')
                                                            ->label('')
                                                            ->content(function (Forms\Get $get) {
                                                                $codigoBodega = $get('codigo_bodega_modal') ?? $get('../codigo_bodega');
                                                                $cedulaCliente = $get('../cedula_cliente');

                                                                \Log::info('Generando tabla con:', [
                                                                    'bodega' => $codigoBodega,
                                                                    'cedula' => $cedulaCliente,
                                                                ]);

                                                                if (!$codigoBodega) {
                                                                    return new HtmlString(
                                                                        '<div class="text-center p-4 bg-yellow-50 text-yellow-700 rounded-lg">
                                                                            ⚠️ Por favor, seleccione una bodega primero.
                                                                        </div>'
                                                                    );
                                                                }

                                                                if (!$cedulaCliente) {
                                                                    return new HtmlString(
                                                                        '<div class="text-center p-4 bg-yellow-50 text-yellow-700 rounded-lg">
                                                                            ⚠️ Por favor, ingrese la cédula del cliente primero.
                                                                        </div>'
                                                                    );
                                                                }

                                                                $array_codigos = [];
                                                                $array_bodega = [];

                                                                try {
                                                                    $tabla = \App\Filament\Resources\FacturacionResource\Forms\ModalProductosBodega::generarTablaProductos(
                                                                        $codigoBodega,
                                                                        $get('codigo_producto_modal'),
                                                                        $get('busqueda_nombre'),
                                                                        $get('busqueda_marca'),
                                                                        $cedulaCliente,
                                                                        $array_codigos,
                                                                        $array_bodega,
                                                                    );

                                                                    return new HtmlString($tabla);
                                                                } catch (\Exception $e) {
                                                                    \Log::error('Error generando tabla:', [
                                                                        'error' => $e->getMessage(),
                                                                        'trace' => $e->getTraceAsString()
                                                                    ]);

                                                                    return new HtmlString(
                                                                        '<div class="text-center p-4 bg-red-50 text-red-700 rounded-lg">
                                                                            ❌ Error al cargar productos: ' . $e->getMessage() . '
                                                                        </div>'
                                                                    );
                                                                }
                                                            }),
                                                    ]),

                                                Forms\Components\Hidden::make('productos_seleccionados_modal')
                                                    ->extraAttributes(['data-role' => 'productos-seleccionados']),
                                            ])
                                            ->action(function (array $data, Forms\Get $get, Forms\Set $set) {
                                                \Log::info('Action ejecutada con data:', $data);

                                                $productosJson = $data['productos_seleccionados_modal'] ?? 
                                                            $data['productos_seleccionados'] ?? '[]';
                                                
                                                $productos = json_decode($productosJson, true);

                                                \Log::info('Productos parseados:', $productos);

                                                if (!is_array($productos) || count($productos) === 0) {
                                                    Notification::make()
                                                        ->title('Debes seleccionar al menos un producto en la tabla')
                                                        ->danger()
                                                        ->send();
                                                    return;
                                                }

                                                // Tomar el primer producto seleccionado
                                                $producto = $productos[0];

                                                \Log::info('Producto seleccionado:', $producto);

                                                $set('producto_id', $producto['codigo'] ?? null);
                                                $set('codigo_producto', $producto['codigo'] ?? null);
                                                $set('nombre_producto', $producto['nombre'] ?? '');
                                                $set('marca_producto', $producto['marca'] ?? '');
                                                $set('precio_unitario', (float)($producto['precio'] ?? 0));

                                                Notification::make()
                                                    ->title('Producto seleccionado correctamente')
                                                    ->success()
                                                    ->body('Código: ' . ($producto['codigo'] ?? 'N/A'))
                                                    ->send();
                                            })
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
                                    ),
                            ]),

                        // INFORMACIÓN DEL PRODUCTO SELECCIONADO
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('codigo_producto')
                                    ->label('Código Producto')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('nombre_producto')
                                    ->label('Nombre Producto')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('marca_producto')
                                    ->label('Marca')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('precio_unitario')
                                    ->label('Precio Unitario')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->prefix('$')
                                    ->numeric(),
                            ]),

                        // CANTIDAD Y FECHA
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Hidden::make('producto_id')
                                    ->required(),

                                Forms\Components\TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(1)
                                    ->suffix('unidades')
                                    ->placeholder('Ingrese la cantidad'),

                                Forms\Components\DateTimePicker::make('fecha')
                                    ->label('Fecha de Consignación')
                                    ->default(now())
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false),
                            ]),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('nombre_cliente')
                    ->label('Cliente')
                    ->searchable(['cliente.nombre1_cliente', 'cliente.apellido1_cliente', 'cliente.cedula'])
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('producto.codigo')
                    ->label('Código Producto')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('producto.descripcion')
                    ->label('Producto')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }
                        return $state;
                    }),
                    
                Tables\Columns\TextColumn::make('bodega.Nombre_Bodega')
                    ->label('Bodega')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('cantidad')
                    ->label('Cantidad')
                    ->numeric()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('vendedor_id')
                    ->label('Vendedor')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bodega_id')
                    ->label('Bodega')
                    ->relationship('bodega', 'Nombre_Bodega')
                    ->preload(),
                    
                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'],
                                fn (Builder $query, $date): Builder => $query->whereDate('fecha', '>=', $date),
                            )
                            ->when(
                                $data['hasta'],
                                fn (Builder $query, $date): Builder => $query->whereDate('fecha', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('fecha', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
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