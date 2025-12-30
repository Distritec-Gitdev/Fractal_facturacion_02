<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacturacionResource\Pages;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RadioCard;
use Filament\Tables\Filters\SelectFilter;
use App\Services\ValidacionCarteraService;
use Filament\Notifications\Notification;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

// MODELOS DE LA BASE DE DATOS 
use App\Models\Asesor;
use App\Models\AaPrin;
use App\Models\SocioDistritec;
use App\Models\Sede;
use App\Models\YTipoVenta;
use App\Models\YMedioPago;
use App\Models\Dependencias;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Support\Facades\Http;
use Filament\Forms\Components\Actions;

// IMPORTAR LAS SECCIONES DE CADA MODULO DEL FORMULARIO
use App\Filament\Resources\FacturacionResource\Forms\DetallesClienteStep;
use App\Filament\Resources\FacturacionResource\Forms\InformacionClienteStep;
use App\Filament\Resources\FacturacionResource\Forms\TipoFacturaStep;
use App\Filament\Resources\FacturacionResource\Forms\SeleccionProductosStep;
use App\Filament\Resources\FacturacionResource\Forms\MediosPagoStep;
use App\Filament\Resources\FacturacionResource\Forms\PagoFacturaStep;



class FacturacionResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static ?string $navigationIcon  = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Facturación';
    protected static ?string $navigationGroup = 'Facturación';

    protected static function canAll(string $permission): bool
    {
        $u = auth()->user();
        if (! $u) return false;

        $super = config('filament-shield.super_admin.name', 'super_admin');

        return $u->hasAnyRole([$super, 'admin']) || $u->can($permission);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAll('view_any_gestion_clientes');
    }

    public static function canViewAny(): bool
    {
        return static::canAll('view_any_gestion_clientes');
    }

    public static function canCreate(): bool
    {
        return static::canAll('create_gestion_clientes');
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()->can('view', $record);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can('delete', $record);
    }

    public static function canDeleteAny(): bool
    {
        $u = auth()->user();
        $super = config('filament-shield.super_admin.name', 'super_admin');
        return $u?->hasAnyRole([$super, 'admin']) ?? false;
    }

    /**
     * Eager load del nombre completo para evitar N+1
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('clientesNombreCompleto');
    }

    public static function form(Form $form): Form
    {
        return $form

            ->schema([
                Wizard::make([
                    DetallesClienteStep::make(),        // PASO 1
                    InformacionClienteStep::make(),     // PASO 2
                    TipoFacturaStep::make(),            // PASO 3
                    SeleccionProductosStep::make(),     // PASO 4
                    MediosPagoStep::make(),             // PASO 5
                    PagoFacturaStep::make(),            // PASO 6
                ])
                //->startOnStep(request()->has('cedula') ? 2 : 1)
                ->nextAction(
                    fn (Action $action) => $action
                        ->disabled(function (Get $get): bool {
                            return $get('puede_avanzar') === false;
                        })
                        ->extraAttributes(function (Get $get) {
                            if ($get('puede_avanzar') === false) {
                                return [
                                    'title' => 'El total de la factura supera el límite de consignación',
                                    'class' => 'cursor-not-allowed'
                                ];
                            }
                            return [];
                        })
                )
                ->maxWidth('full')
                ->columnSpanFull(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        $compactCell = fn () => ['class' => 'py-1 px-2 text-[11px] whitespace-nowrap'];
        $compactHead = fn () => ['class' => 'py-1 px-2 text-[10px] uppercase tracking-wide'];

        return $table
            ->defaultSort('id_cliente', 'desc')
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex()
                    ->extraCellAttributes(function () use ($compactCell) {
                        $a = $compactCell();
                        $a['class'] .= ' text-center';
                        return $a;
                    })
                    ->extraHeaderAttributes(function () use ($compactHead) {
                        $a = $compactHead();
                        $a['class'] .= ' text-center';
                        return $a;
                    })
                    ->alignCenter(),

                TextColumn::make('id_cliente')
                    ->label('ID Cliente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable()
                    ->extraCellAttributes(function () use ($compactCell) {
                        $a = $compactCell();
                        $a['class'] .= ' text-center';
                        return $a;
                    })
                    ->extraHeaderAttributes(function () use ($compactHead) {
                        $a = $compactHead();
                        $a['class'] .= ' text-center';
                        return $a;
                    })
                    ->alignCenter(),

                //  Nombre desde accessor getNombreCompletoAttribute
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->placeholder('N/A')
                    // No sortable / searchable a nivel SQL porque no es columna real
                    ->sortable(false)
                    ->searchable(false)
                    ->extraCellAttributes(function () use ($compactCell) {
                        $a = $compactCell();
                        $a['class'] .= ' text-center';
                        return $a;
                    })
                    ->extraHeaderAttributes(function () use ($compactHead) {
                        $a = $compactHead();
                        $a['class'] .= ' text-center';
                        return $a;
                    })
                    ->alignCenter(),

                //  Cédula directo desde `clientes`
                TextColumn::make('cedula')
                    ->label('Cédula')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->hidden(false)
                    ->extraCellAttributes(function () use ($compactCell) {
                        $a = $compactCell();
                        $a['class'] .= ' text-center';
                        return $a;
                    })
                    ->extraHeaderAttributes(function () use ($compactHead) {
                        $a = $compactHead();
                        $a['class'] .= ' text-center';
                        return $a;
                    })
                    ->alignCenter(),
                    
                IconColumn::make('facturar')
                    ->label('Facturar')
                    ->getStateUsing(fn (Cliente $record) => true)
                    ->icon('heroicon-o-credit-card')
                    ->color('primary')
                    ->tooltip('Facturar')
                    ->alignCenter()
                    ->url(fn (Cliente $record) => static::getUrl('create', [
                        'cedula' => $record->cedula,
                        'cliente_id' => $record->id_cliente,  // mandamos el ID del cliente
                ])),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFacturacions::route('/'),
            'create' => Pages\CreateFacturacion::route('/create'),
            'edit'   => Pages\EditFacturacion::route('/{record}/edit'),
        ];
    }
}
