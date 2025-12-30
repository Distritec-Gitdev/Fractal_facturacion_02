<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonResource\Pages;
use App\Filament\Resources\PersonResource\RelationManagers;
use App\Models\Person;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;

class PersonResource extends Resource
{
    protected static ?string $model = Person::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    ///////////////////////////////////////



protected static function canAll(string $permission): bool
{
    $u = auth()->user();
    if (! $u) return false;

    // super_admin viene del config de Shield por si lo cambiaste
    $super = config('filament-shield.super_admin.name', 'super_admin');

    //if ($u->hasAnyRole([$super, 'admin'])) {
      //  return true;
    //}

    if ($u->hasAnyRole([''])) {
        return true;
    }

    return $u->can($permission);
}

public static function shouldRegisterNavigation(): bool
{
    // ❌ No mostrar en el menú
    return false;
}

public static function canViewAny(): bool
{
    // ❌ Bloquear listado (evita acceso por URL)
    return false;
}

public static function canView(Model $record): bool
{
    // ❌ Bloquear show
    return false;
}

public static function canCreate(): bool
{
    // ❌ Bloquear create
    return false;
}

public static function canEdit(Model $record): bool
{
    // ❌ Bloquear edit
    return false;
}

public static function canDelete(Model $record): bool
{
    // ❌ Bloquear delete
    return false;
}

public static function canDeleteAny(): bool
{
    // ❌ Bloquear bulk delete
    return false;
}

    ///////////////////////////////////////



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('cedula')
                    ->required()
                    ->unique(Person::class, 'cedula', ignoreRecord: true)
                    ->maxLength(20),
                Select::make('estado')
                    ->options([
                        'aprobado' => 'Aprobado',
                        'no aprobado' => 'No Aprobado',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre'),
                TextColumn::make('cedula'),
                BadgeColumn::make('estado')
                    ->colors([
                        'success' => 'aprobado',
                        'danger'  => 'no aprobado',
                    ]),
            ]);
            // Se remueve el poll para usar solo Broadcasting
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
            'index' => Pages\ListPeople::route('/'),
            'create' => Pages\CreatePerson::route('/create'),
            'edit' => Pages\EditPerson::route('/{record}/edit'),
        ];
    }
}
