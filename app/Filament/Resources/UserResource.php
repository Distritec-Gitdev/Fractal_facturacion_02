<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use App\Support\Filament\AdminOnlyResourceAccess;


class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Seguridad';
    protected static ?int $navigationSort = 3;

  use AdminOnlyResourceAccess;


    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos básicos')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('cedula')
                                ->extraAttributes(['class' => 'text-lg font-bold'])
                                ->label('Cédula')
                                ->required()
                                ->numeric()
                                ->minLength(5)
                                ->maxLength(15)
                                ->validationMessages([
                                    'max_length' => 'Máximo 15 dígitos permitidos.',
                                    'min_length' => 'Mínimo 5 dígitos permitidos.',
                                ])
                                ->live()
                                ->extraInputAttributes(['onInput' => 'this.value = this.value.toUpperCase()']),
                               

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Forms\Components\DateTimePicker::make('email_verified_at')
                        ->label('Verificado el')
                        ->native(false)
                        ->seconds(false),
                ]),

           Forms\Components\Section::make('Credenciales')
            ->columns(2)
            ->schema([
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')        // evita autocompletado del navegador
                    ->afterStateHydrated(function (TextInput $component) {
                        // Al abrir en edición, limpia el campo para no mostrar el hash
                        $component->state('');
                    })
                    // Hash solo si se envía algo
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    // Requerido solo al crear
                    ->required(fn (string $context) => $context === 'create')
                    ->rule('confirmed')
                    ->helperText('Déjalo vacío si no deseas cambiar la contraseña.'),

                TextInput::make('password_confirmation')
                    ->label('Confirmar contraseña')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->dehydrated(false)
                    ->required(fn (string $context) => $context === 'create'),
                ]),

            Forms\Components\Section::make('Autorización')
                ->columns(1)
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->label('Roles')
                        ->relationship('roles', 'name') // Spatie
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->helperText('Asigna uno o varios roles al usuario.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('cedula')
                    ->label('Cedula')
                    ->searchable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verificado')
                    ->boolean()
                    ->trueIcon('heroicon-m-check-badge')
                    ->falseIcon('heroicon-m-x-mark')
                    ->state(fn ($record) => !is_null($record->email_verified_at))
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name'),

                Tables\Filters\TernaryFilter::make('verified')
                    ->label('Verificado')
                    ->boolean()
                    ->trueLabel('Solo verificados')
                    ->falseLabel('Solo no verificados')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('email_verified_at'),
                        false: fn ($q) => $q->whereNull('email_verified_at'),
                        blank: fn ($q) => $q
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()?->can('delete_user') ?? false), // opcional con Shield
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Aquí podrías añadir RelationManagers (ej. tokens, activity, etc.)
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
