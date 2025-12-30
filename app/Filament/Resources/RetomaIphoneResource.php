<?php 

namespace App\Filament\Resources;

use App\Filament\Resources\RetomaIphoneResource\Pages;
use App\Models\RetomaIphone;
use App\Models\ZMarca;
use App\Models\ZModelo;
use App\Models\ZBodegaFacturacion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class RetomaIphoneResource extends Resource
{
    protected static ?string $model = RetomaIphone::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $navigationLabel = 'Retomas iPhone';
    protected static ?string $pluralModelLabel = 'Retomas iPhone';
    protected static ?string $modelLabel = 'Retoma iPhone';

    public static function form(Form $form): Form
    {
        $estadoPantalla = [
            'excelente' => 'Excelente (sin rayones)',
            'buena'     => 'Buena (rayones leves)',
            'rayada'    => 'Rayada (rayones fuertes)',
            'rota'      => 'Rota / con líneas',
        ];

        $estadoGeneral = [
            'como_nuevo' => 'Como nuevo',
            'bueno'      => 'Bueno',
            'regular'    => 'Regular',
            'malo'       => 'Malo',
        ];

        $bateria = collect([50,60,70,80,85,90,95,100])
            ->mapWithKeys(fn ($v) => [$v => $v . '%'])
            ->toArray();

        return $form->schema([
            Forms\Components\Section::make('Datos del equipo')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('idmarca')
                        ->label('Marca')
                        ->options(fn () => (function () {
                            try {
                                return ZMarca::orderBy('name_marca')
                                    ->pluck('name_marca', 'idmarca')
                                    ->toArray() ?: [];
                            } catch (\Throwable $e) {
                                return [];
                            }
                        })())
                        ->preload()
                        ->required()
                        ->live(),

                    Forms\Components\Select::make('idmodelo')
                        ->label('Modelo')
                        ->options(function (Get $get) {
                            $idmarca = $get('idmarca');

                            $q = ZModelo::query()->orderBy('name_modelo');

                            if ($idmarca) {
                                $q->where('idmarca', $idmarca);
                            }

                            return $q->pluck('name_modelo', 'idmodelo')->toArray() ?? [];
                        })
                        ->preload()
                        ->required()
                        ->live(),

                    Forms\Components\Select::make('id_bodega')
                        ->label('Bodega')
                        ->options(fn () => (function () {
                            try {
                                return ZBodegaFacturacion::orderBy('Nombre_Bodega')
                                    ->pluck('Nombre_Bodega', 'ID_Bog')
                                    ->toArray() ?: [];
                            } catch (\Throwable $e) {
                                return [];
                            }
                        })())
                        ->preload()
                        ->required()
                        ->live(),

                    Forms\Components\TextInput::make('imei')
                        ->label('IMEI')
                        ->maxLength(30)
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('precio_compra')
                        ->label('Precio de compra')
                        ->numeric()
                        ->required(),

                    Forms\Components\Select::make('bateria_porcentaje')
                        ->label('% Batería')
                        ->options($bateria)
                        ->required()
                        ->reactive(),

                    Forms\Components\Select::make('estado_pantalla')
                        ->label('Estado de pantalla')
                        ->options($estadoPantalla)
                        ->required()
                        ->reactive(),

                    Forms\Components\Select::make('estado_general')
                        ->label('Estado general')
                        ->options($estadoGeneral)
                        ->required()
                        ->reactive(),

                    Forms\Components\Placeholder::make('preview_calificacion')
                        ->label('Calificación estimada')
                        ->content(function (Get $get) {
                            [$score, $calif] = self::previewScore(
                                (string) $get('estado_pantalla'),
                                (int) $get('bateria_porcentaje'),
                                (string) $get('estado_general'),
                            );

                            return $score ? "Score: {$score} | Calificación: {$calif}" : '-';
                        }),

                    Forms\Components\TextInput::make('codigo_vendedor')
                        ->label('Código vendedor')
                        ->maxLength(50)
                        ->required(),

                    Forms\Components\TextInput::make('nombre_asesor')
                        ->label('Nombre asesor')
                        ->maxLength(120)
                        ->required(),

                    Forms\Components\Textarea::make('observaciones')
                        ->label('Observaciones')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('imei')
                    ->label('IMEI')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('idmarca')
                    ->label('Marca')
                    ->formatStateUsing(fn ($state) => self::marcaMap()[(int) $state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('idmodelo')
                    ->label('Modelo')
                    ->formatStateUsing(fn ($state) => self::modeloMap()[(int) $state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('id_bodega')
                    ->label('Bodega')
                    ->formatStateUsing(fn ($state) => self::bodegaMap()[(int) $state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('precio_compra')
                    ->label('Compra')
                    ->money('COP', true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('bateria_porcentaje')
                    ->label('Batería')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('estado_pantalla')
                    ->label('Pantalla')
                    ->sortable(),

                Tables\Columns\TextColumn::make('estado_general')
                    ->label('General')
                    ->sortable(),

                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('calificacion')
                    ->label('Calif')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nombre_asesor')
                    ->label('Asesor')
                    ->searchable(),

                Tables\Columns\TextColumn::make('codigo_vendedor')
                    ->label('Vendedor')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ingreso')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRetomaIphones::route('/'),
            'create' => Pages\CreateRetomaIphone::route('/create'),
            'edit' => Pages\EditRetomaIphone::route('/{record}/edit'),
        ];
    }

    private static function marcaMap(): array
    {
        return Cache::remember('retomas_marca_map', 3600, fn () =>
            ZMarca::query()
                ->pluck('name_marca', 'idmarca')
                ->mapWithKeys(fn ($v, $k) => [(int) $k => $v])
                ->toArray()
        );
    }

    private static function modeloMap(): array
    {
        return Cache::remember('retomas_modelo_map', 3600, fn () =>
            ZModelo::query()
                ->pluck('name_modelo', 'idmodelo')
                ->mapWithKeys(fn ($v, $k) => [(int) $k => $v])
                ->toArray()
        );
    }

    private static function bodegaMap(): array
    {
        return Cache::remember('retomas_bodega_map', 3600, fn () =>
            ZBodegaFacturacion::query()
                ->pluck('Nombre_Bodega', 'ID_Bog')
                ->mapWithKeys(fn ($v, $k) => [(int) $k => $v])
                ->toArray()
        );
    }

    private static function previewScore(string $pantalla, int $bateria, string $general): array
    {
        $scorePantalla = match ($pantalla) {
            'excelente' => 5,
            'buena'     => 4,
            'rayada'    => 3,
            'rota'      => 1,
            default     => 0,
        };

        $scoreGeneral = match ($general) {
            'como_nuevo' => 5,
            'bueno'      => 4,
            'regular'    => 3,
            'malo'       => 1,
            default     => 0,
        };

        $scoreBateria = match (true) {
            $bateria >= 90 => 5,
            $bateria >= 80 => 4,
            $bateria >= 70 => 3,
            $bateria >= 60 => 2,
            $bateria > 0   => 1,
            default        => 0,
        };

        $total = $scorePantalla + $scoreBateria + $scoreGeneral;
        if ($total === 0) return [0, '-'];

        $score = (int) round(($total / 15) * 100);

        $calif = match (true) {
            $score >= 85 => 'A',
            $score >= 70 => 'B',
            $score >= 55 => 'C',
            default      => 'D',
        };

        return [$score, $calif];
    }
}
