<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgentesResource\Pages;
use App\Models\Agentes;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder; // 👈 NUEVO

class AgentesResource extends Resource
{
    protected static ?string $model = Agentes::class;

    protected static ?string $navigationGroup = 'Administración';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $modelLabel = 'Agente';
    protected static ?string $pluralModelLabel = 'Agentes';
    protected static ?string $navigationLabel = 'Agentes';

    // IMPORTANTE: define el slug que usarán los permisos de Shield
    protected static ?string $slug = 'agentes';

    /** Helper para componer los nombres de permiso de Shield */
    protected static function perm(string $action): string
    {
        // genera: view_any_agentes, create_agentes, update_agentes, delete_agentes, view_agentes...
        return "{$action}_" . (static::$slug ?? 'agentes');
    }

    /** Admin + super_admin + agente_admin pasan siempre; el resto necesita el permiso */
    protected static function canAll(string $permission): bool
    {
        $u = auth()->user();
        if (! $u) return false;

        $super = config('filament-shield.super_admin.name', 'super_admin');

        return $u->hasAnyRole([$super,  'agente_admin']) || $u->can($permission);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAll(static::perm('view_any'));
    }

    public static function canViewAny(): bool
    {
        return static::canAll(static::perm('view_any'));
    }

    public static function canCreate(): bool
    {
        return static::canAll(static::perm('create'));
    }

    // Si tienes Policy: puedes usar ->can('view', $record) en vez de permisos directos
    public static function canView(Model $record): bool
    {
        return auth()->user()?->can(static::perm('view')) ?? false;
        // return auth()->user()->can('view', $record);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can(static::perm('update')) ?? false;
        // return auth()->user()->can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can(static::perm('delete')) ?? false;
        // return auth()->user()->can('delete', $record);
    }

    public static function canDeleteAny(): bool
    {
        $u = auth()->user();
        $super = config('filament-shield.super_admin.name', 'super_admin');

        // Si quieres que 'agente_admin' también pueda borrado masivo, déjalo incluido
        return $u?->hasAnyRole([$super, 'admin', 'agente_admin']) ?? false;
    }

    // 👇 NUEVO: filtra para mostrar solo terceros con id_tipo_tercero = 3 (Agente)
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('id_tipo_tercero', 3);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Datos del tercero')->schema([
                // Fuerza el tipo de tercero = 3 (Agente)
                Hidden::make('id_tipo_tercero')
                    ->default(3)
                    ->disabled()     // no editable
                    ->dehydrated(),  // se envía al guardar aunque esté disabled

                TextInput::make('nombre_pv')
                    ->label('Nombre PV')
                    ->maxLength(100)
                    ->required(),

                TextInput::make('nombre_tercero')
                    ->label('Nombre tercero')
                    ->maxLength(150)
                    ->required(),

                Select::make('tipo_documento')
                    ->label('Tipo de Documento')
                    ->required()
                    ->options(TipoDocumento::pluck('desc_identificacion', 'ID_Identificacion_Tributaria'))
                    ->searchable()
                    ->preload(),

                TextInput::make('numero_documento')
                    ->label('Número de documento')
                    ->maxLength(30)
                    ->required(),
                   // ->unique(ignoreRecord: true),

                TextInput::make('correo')
                    ->label('Correo')
                    ->email()
                    ->maxLength(150),

                TextInput::make('telefono')
                    ->label('Teléfono')
                    ->maxLength(30),

                // Guarda 1/0 (coincide con TINYINT en la BD)
                Select::make('estado')
                    ->label('Estado')
                    ->options([
                        1 => 'Activo',
                        0 => 'Inactivo',
                    ])
                    ->default(1)
                    ->required(),
            ])->columns(2),

            Section::make('Ubicación')->schema([
                // belongsTo Sede::class, 'id_sede' -> 'ID_Sede'
                Select::make('id_sede')
                    ->label('Sede')
                    ->relationship('sede', 'Name_Sede')
                    ->searchable()
                    ->preload()
                    ->required(),
            ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id_tercero')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('nombre_pv')
                    ->label('Nombre PV')
                    ->searchable(),

                TextColumn::make('nombre_tercero')
                    ->label('Nombre tercero')
                    ->searchable(),

                TextColumn::make('tipoDocumento.desc_identificacion')
                    ->label('Tipo doc.')
                    ->badge(),

                TextColumn::make('numero_documento')
                    ->label('N° documento')
                    ->searchable(),

                TextColumn::make('correo')
                    ->label('Correo')
                    ->searchable(),

                TextColumn::make('telefono')
                    ->label('Teléfono'),

                // Tipo de tercero según la relación (ya solo serán agentes por el filtro)
                TextColumn::make('tipoTercero.nombre')
                    ->label('Tipo de tercero')
                    ->badge()
                    ->color('primary'),

                // Muestra estado como badge usando 1/0 del DB
                TextColumn::make('estado')
                    ->label('Estado')
                    ->formatStateUsing(fn ($record) => (int) $record->estado === 1 ? 'Activo' : 'Inactivo')
                    ->badge()
                    ->color(fn ($record) => (int) $record->estado === 1 ? 'success' : 'danger'),

                // Nombre de la sede (otra BD)
                TextColumn::make('sede.Name_Sede')
                    ->label('Sede'),
                    //->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        1 => 'Activo',
                        0 => 'Inactivo',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAgentes::route('/'),
            'create' => Pages\CreateAgentes::route('/create'),
            'edit'   => Pages\EditAgentes::route('/{record}/edit'),
        ];
    }
}
