<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductosResource\Pages;
use App\Models\ZModelo;
use App\Models\ZCategoria;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource de Filament para administrar productos/modelos.
 *
 * Puntos clave:
 * - Columna "Estado" muestra Habilitado/Inhabilitado con colores.
 * - Acciones "Habilitar/Inhabilitar" guardan y refrescan el registro.
 * - Filtro por estado robusto (maneja null/strings).
 * - Lógica auxiliar para construir el nombre del modelo con RAM/Almacenamiento.
 */
class ProductosResource extends Resource
{
    /** @var class-string<ZModelo> */
    protected static ?string $model = ZModelo::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Productos';
    protected static ?string $modelLabel = 'Producto';
    protected static ?string $pluralModelLabel = 'Productos';

    /** Helper para verificar roles permitidos */
    protected static function isAdminLike(): bool
    {
        $user = auth()->user();
        return (bool) $user?->hasAnyRole(['admin', 'super_admin']);
    }

    /** Oculta el recurso del menú si no es admin/super_admin */
    public static function shouldRegisterNavigation(): bool
    {
        return self::isAdminLike();
    }

    /** Permisos globales del recurso */
    public static function canViewAny(): bool
    {
        return self::isAdminLike();
    }

    public static function canCreate(): bool
    {
        return self::isAdminLike();
    }

    public static function canDeleteAny(): bool
    {
        return self::isAdminLike();
    }

    /** Permisos por registro */
    public static function canView(Model $record): bool
    {
        return self::isAdminLike();
    }

    public static function canEdit(Model $record): bool
    {
        return self::isAdminLike();
    }

    public static function canDelete(Model $record): bool
    {
        return self::isAdminLike();
    }

    /**
     * Define el formulario de creación/edición.
     * Incluye:
     *  - Selección de marca y categoría (relaciones)
     *  - Campo de nombre con “autocomposición”
     *  - Fieldset condicional RAM/Almacenamiento solo para categorías de dispositivos
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Información del producto')
                ->schema([
                    // Marca (relación con creación inline)
                    Forms\Components\Select::make('idmarca')
                        ->label('Marca')
                        ->relationship('marca', 'name_marca')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false)
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name_marca')
                                ->label('Nombre de la marca')
                                ->required()
                                ->maxLength(150),
                        ]),

                    // Categoría (relación). Al cambiar, recomponer nombre con posibles sufijos.
                    Forms\Components\Select::make('id_categoria')
                        ->label('Categoría')
                        ->relationship('categoria', 'categoria')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false)
                        ->reactive()
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) =>
                            $set('name_modelo', self::composeName($get))
                        ),

                    // Nombre base del modelo. Se recompone en vivo con RAM/Alm cuando aplique.
                    Forms\Components\TextInput::make('name_modelo')
                        ->label('Modelo / Producto')
                        ->required()
                        ->maxLength(150)
                        ->live(debounce: 500)
                        ->afterStateUpdated(fn ($state) => null) // ⬅️ no reescribe tu texto mientras editas
                        // Normaliza SOLO al persistir (en vivo se permiten espacios finales)
                        ->dehydrateStateUsing(function ($state, Get $get) {
                            $state = is_string($state) ? trim($state) : $state;
                            return self::buildNombreConcatenado(
                                $state,
                                $get('id_categoria'),
                                $get('ram'),
                                $get('almacenamiento')
                            );
                        }),

                    // Sufijo RAM/Almacenamiento (solo CREAR; en EDITAR se oculta)
                    Forms\Components\Fieldset::make('RAM / Almacenamiento')
                        ->schema([
                            // RAM (solo dígitos; multi-dígito; actualiza en vivo)
                            Forms\Components\TextInput::make('ram')
                                ->label('RAM')
                                ->suffix('GB')
                                ->type('text')
                                ->inputMode('numeric') // teclado numérico sugerido
                                ->rules(['nullable', 'regex:/^\d*$/']) // SOLO dígitos o vacío
                                ->dehydrated(false) // no se guarda directo; solo compone el nombre
                                ->reactive()
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    // Limpia todo lo que no sea dígito y reasigna el valor limpio
                                    $clean = preg_replace('/\D/', '', (string) $state) ?? '';
                                    $clean = $clean === '' ? null : $clean;
                                    $set('ram', $clean);

                                    // Recompone nombre al instante usando override para evitar lag
                                    $set('name_modelo', self::composeName($get, ramOverride: $clean));
                                }),

                            // Almacenamiento (solo dígitos; multi-dígito; actualiza en vivo)
                            Forms\Components\TextInput::make('almacenamiento')
                                ->label('Almacenamiento')
                                ->suffix('GB')
                                ->type('text')
                                ->inputMode('numeric')
                                ->rules(['nullable', 'regex:/^\d*$/']) // SOLO dígitos o vacío
                                ->dehydrated(false)
                                ->live(debounce: 400)
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    $clean = preg_replace('/\D/', '', (string) $state) ?? '';
                                    $clean = $clean === '' ? null : $clean;

                                    // Recompone nombre al instante usando override, sin tocar el valor del input
                                    $set('name_modelo', self::composeName($get, almOverride: $clean));
                                }),
                        ])
                        ->columns(2)
                        ->visible(fn (Get $get) => self::isDispositivo($get('id_categoria')))
                        // En edición ocultamos el fieldset para no reescribir accidentalmente el nombre.
                        ->hidden(fn (Get $get, ?string $operation) => $operation === 'edit'),
                ])
                ->columns(3),
        ]);
    }

    /**
     * Define las columnas, filtros y acciones de la tabla de índice.
     *
     * - Estado: muestra badge con color según 0/1. Se usa ->state() para garantizar int.
     * - Filtros por Marca, Categoría y Estado.
     * - Acciones Habilitar/Inhabilitar que guardan con save() y luego refresh() para reflejo inmediato.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Marca
                Tables\Columns\TextColumn::make('marca.name_marca')
                    ->label('Marca')
                    ->sortable()
                    ->searchable(),

                // Categoría
                Tables\Columns\TextColumn::make('categoria.categoria')
                    ->label('Categoría')
                    ->sortable()
                    ->searchable(),

                // Nombre del modelo
                Tables\Columns\TextColumn::make('name_modelo')
                    ->label('Modelo / Producto')
                    ->sortable()
                    ->searchable(),

                /**
                 * Columna Estado (badge):
                 * - Usamos ->state() para asegurar un 0/1 numérico (evita null/falsy).
                 * - ->formatStateUsing() para mostrar etiquetas de negocio.
                 * - ->colors() para feedback visual (verde/rojo).
                 */
                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->state(fn (ZModelo $record) => (int) $record->estado)
                    ->formatStateUsing(fn ($state) => ((int) $state) === 1 ? 'Habilitado' : 'Inhabilitado')
                    ->colors([
                        'success' => fn ($state) => (int) $state === 1,
                        'danger'  => fn ($state) => (int) $state === 0,
                    ])
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('idmarca')
                    ->label('Marca')
                    ->relationship('marca', 'name_marca'),

                Tables\Filters\SelectFilter::make('id_categoria')
                    ->label('Categoría')
                    ->relationship('categoria', 'categoria'),

                /**
                 * Filtro por estado:
                 * - options 0/1 legibles
                 * - query() robusta contra null/strings
                 */
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([1 => 'Habilitado', 0 => 'Inhabilitado'])
                    ->query(fn (Builder $query, array $data) =>
                        isset($data['value']) && $data['value'] !== ''
                            ? $query->where('estado', (int) $data['value'])
                            : $query
                    ),
            ])
            ->actions([
                // Acción estándar de edición
                Tables\Actions\EditAction::make(),

                /**
                 * Acción Inhabilitar:
                 * - visible sólo cuando estado=1
                 * - guarda con save() (evita problemas si algún día cambias fillable)
                 * - refresh() para asegurar que la fila se actualice en la UI
                 */
                Tables\Actions\Action::make('inhabilitar')
                    ->label('Inhabilitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ZModelo $record) => (int) $record->estado === 1)
                    ->action(function (ZModelo $record) {
                        $record->estado = 0;
                        $record->save();
                        $record->refresh();
                    }),

                /**
                 * Acción Habilitar:
                 * - visible sólo cuando estado=0
                 */
                Tables\Actions\Action::make('habilitar')
                    ->label('Habilitar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ZModelo $record) => (int) $record->estado === 0)
                    ->action(function (ZModelo $record) {
                        $record->estado = 1;
                        $record->save();
                        $record->refresh();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    /**
                     * Acción masiva: Inhabilitar seleccionados
                     * - Recorre cada registro y guarda estado=0
                     * - Si prefieres un update masivo, podrías usar query->update([...]);
                     *   pero aquí garantizamos eventos de modelo por registro.
                     */
                    Tables\Actions\BulkAction::make('inhabilitarSeleccionados')
                        ->label('Inhabilitar seleccionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(function (ZModelo $r) {
                                $r->estado = 0;
                                $r->save();
                            });
                        }),
                ]),
            ]);
    }

    /**
     * Query base del resource.
     * - with() carga relaciones para evitar N+1 en la tabla.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['marca', 'categoria']);
    }

    /** @return array */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * Rutas/páginas del recurso.
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProductos::route('/'),
            'create' => Pages\CreateProductos::route('/create'),
            'edit'   => Pages\EditProductos::route('/{record}/edit'),
        ];
    }

    // ===================== Helpers de negocio / UI =====================

    /**
     * Determina si una categoría corresponde a “dispositivo”
     * para mostrar/ocultar campos de RAM/Almacenamiento.
     *
     * @param ?int $categoriaId
     * @return bool
     */
    protected static function isDispositivo(?int $categoriaId): bool
    {
        if (!$categoriaId) return false;
        $nombre = optional(ZCategoria::find($categoriaId))->categoria;
        if (!$nombre) return false;

        return in_array(Str::lower($nombre), [
            'celulares emergentes',
            'celulares libres',
            'computador',
            'tablet',
            'portatiles',
        ], true);
    }

    /**
     * Extrae RAM/Almacenamiento de un nombre con sufijo " NGB +MGB".
     *
     * @param string $nombre
     * @return array{0: ?int, 1: ?int} [ram, alm]
     */
    public static function parsePlusSuffix(string $nombre): array
    {
        if (preg_match('/\s(\d+)\s*GB\s*\+\s*(\d+)\s*GB$/i', $nombre, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        return [null, null];
    }

    /**
     * Elimina el sufijo " NGB +MGB".
     *
     * @param string $nombre
     * @return string
     */
    public static function stripPlusSuffix(string $nombre): string
    {
        return preg_replace('/\s+\d+\s*GB\s*\+\s*\d+\s*GB$/i', '', $nombre) ?? $nombre;
    }

    /**
     * Construye el nombre final en tiempo real en el formulario,
     * tomando en cuenta categoría y campos temporales de RAM/Alm.
     * (No recorta espacios mientras el usuario escribe)
     *
     * @param Get $get
     * @param ?string $baseOverride base manual si se edita directamente el TextInput
     * @param ?string $ramOverride override inmediato para RAM (evita lag de $get)
     * @param ?string $almOverride override inmediato para Almacenamiento (evita lag de $get)
     * @return string
     */
    protected static function composeName(Get $get, ?string $baseOverride = null, ?string $ramOverride = null, ?string $almOverride = null): string
    {
        $base = $baseOverride ?? ($get('name_modelo') ?? '');
        // Quita solo el sufijo si existe, pero NO recortes espacios escritos por el usuario
        $base = self::stripPlusSuffix($base);

        $categoriaId = $get('id_categoria');
        // Usa overrides si vienen, si no, cae al $get(...)
        $ram = $ramOverride ?? $get('ram');
        $alm = $almOverride ?? $get('almacenamiento');

        if (self::isDispositivo($categoriaId) && $ram && $alm) {
            // Evita dobles espacios justo antes del sufijo
            $baseSinEspacioFinal = rtrim($base);
            return $baseSinEspacioFinal . ' ' . ((int) $ram) . 'GB +' . ((int) $alm) . 'GB';
        }

        // Devuelve el texto tal cual (respetando espacios en vivo)
        return $base;
    }

    /**
     * Helper para componer nombre al guardar (create/edit),
     * por si necesitas validar antes de persistir.
     *
     * @param string  $base
     * @param ?int    $categoriaId
     * @param ?string $ram
     * @param ?string $alm
     * @return string
     */
    public static function buildNombreConcatenado(string $base, ?int $categoriaId, ?string $ram, ?string $alm): string
    {
        $base = self::stripPlusSuffix($base);
        $base = trim($base); // normaliza SOLO al persistir

        if (self::isDispositivo($categoriaId) && $ram && $alm) {
            return trim($base . ' ' . ((int) $ram) . 'GB +' . ((int) $alm) . 'GB');
        }

        return trim($base);
    }
}
