<?php

namespace App\Filament\Resources\FacturacionResource\Forms;

use Closure;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Actions\Action;
use Filament\Notifications\Notification;

use App\Models\YTipoVenta;
use App\Models\YMedioPago;
use App\Models\ZComision;
use App\Models\SocioDistritec;
use App\Models\Sede;
use App\Models\Asesor;
use App\Models\AaPrin;
use App\Models\Dependencias;
use App\Models\Agentes;
use App\Models\TipoDocumento;

class TipoFacturaStep
{
    /**
     * Caches en memoria por request para mejorar rendimiento
     */
    protected static ?array $mediosPagoTodos = null;
    protected static ?array $mediosPagoCodigos = null;
    protected static ?array $dependenciasContraentrega = null;
    protected static ?array $comisionesClienteFinal = null;
    protected static ?array $comisionesTodas = null;
    protected static ?array $tiposDocumento = null;
    protected static array $tercerosPorTipo = [];

    // nuevos caches globales
    protected static ?int $tipoVentaPlataformasId = null;
    protected static array $detallesClienteCache = [];
    protected static array $sedeFromCedulaCache = [];

    public static function make(): Step
    {
        return Step::make('Tipo venta')
            ->icon('heroicon-o-document-currency-dollar')
            ->schema([
            Hidden::make('bloquear_tipo_venta')
            ->default(fn () => request()->has('cliente_id'))
            ->dehydrated(false),

                // TIPO VENTA
                Select::make('Tipo_venta')
                    ->label('Tipo venta')
                    ->preload()
                    ->options(function () {


                        Log::info('antes del log');

                        // ids que quieres permitir en general
                        $query = YTipoVenta::whereIn('ID_Tipo_Venta', [1, 4, 3, 5])
                            ->orderBy('Nombre_Tipo_Venta');

                        Log::info('TipoFacturaStep.Tipo_venta.options.query_iniciado');
                        

                        // si NO viene desde cliente (botón "generar factura"),
                        // saco PLATAFORMAS Y CONVENIOS de la lista
                        if (! request()->has('cliente_id')) {
                            $tipoPlataformasId = self::getTipoPlataformasId();

                            if ($tipoPlataformasId) {
                                $query->where('ID_Tipo_Venta', '!=', $tipoPlataformasId);
                            }
                        }

                        return $query
                            ->pluck('Nombre_Tipo_Venta', 'ID_Tipo_Venta')
                            ->toArray();
                    })
                    ->default(function () {
                        // solo pongo por defecto PLATAFORMAS Y CONVENIOS
                        // cuando viene desde cliente
                        if (! request()->has('cliente_id')) {
                            return null;
                        }

                        return self::getTipoPlataformasId();
                    })
                    ->searchable()
                    ->reactive()
                    ->required()
                    ->disabled(function (Get $get) {
                        $bloquear = (bool) $get('bloquear_tipo_venta');

                        if (! $bloquear) {
                            return false;
                        }

                        $tipoPlataformasId = self::getTipoPlataformasId();
                        $idTipoVenta       = (int) ($get('Tipo_venta') ?? 0);

                        return $idTipoVenta === (int) $tipoPlataformasId;
                    })

                    

                    ->afterStateHydrated(function ($state, Set $set, Get $get) {
                        $idTipoVenta = (int) ($state ?? 0);

                        if (! $idTipoVenta) {
                            $set('recompra', 'NO');
                            return;
                        }

                        self::resolverRecompra($set, $get, $idTipoVenta);
                    })
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        $idTipoVenta = (int) ($state ?? 0);

                        // reset masivo
                        $set('medio_pago', null);
                        $set('dependencia_contraentrega', null);
                        $set('dependencia_medio_pago', null);
                        $set('codigo_caja', null);
                        $set('Unidad_negocio', null);
                        $set('unidad_negocio', null);
                        $set('nombre_tercero', null);
                        $set('recompra', 'NO');
                        self::resetCartera($set);
                        $set('codigo_dependencia', null);

                        if (! $idTipoVenta) {
                            return;
                        }

                        // RECARGAS (4) → TIENDA FÍSICA
                        if ($idTipoVenta === 4) {
                            $comision = ZComision::where('comision', 'TIENDA FÍSICA')->first();
                            if ($comision) {
                                $set('Unidad_negocio', $comision->id);
                                $set('unidad_negocio', $comision->Cod_Unidad_neg);
                            }
                        }

                        // DISTRIBUCIÓN (5) → TIENDA FÍSICA fija
                        if ($idTipoVenta === 5) {
                            $comision = ZComision::find(6); // TIENDA FÍSICA
                            if ($comision) {
                                $set('Unidad_negocio', $comision->id);
                                $set('unidad_negocio', $comision->Cod_Unidad_neg);
                            }

                            $set('recompra', 'NO');
                            self::resetCartera($set);
                            return;
                        }

                        // Recompra para Cliente final y Plataformas
                        self::resolverRecompra($set, $get, $idTipoVenta);
                        self::resetCartera($set);
                    }),

              
                // MEDIO DE PAGO
                Select::make('medio_pago')
                    ->label('Medio de pago')
                    ->live()
                    ->default(function (Get $get) {
                        $clienteId = self::getClienteId();
                        if (! $clienteId) {
                            return null;
                        }

                        $tipoPlataformasId = self::getTipoPlataformasId();
                        $idTipoVenta       = (int) ($get('Tipo_venta') ?? 0);

                        if ($idTipoVenta !== (int) $tipoPlataformasId) {
                            return null;
                        }

                        return self::getDetalleClienteCampo($clienteId, 'idplataforma');
                    })
                    ->options(function (Get $get) {
                        $idTipoVenta = (int) ($get('Tipo_venta') ?? 0);
                        if (! $idTipoVenta) {
                            return [];
                        }

                        $tipoPlataformasId = self::getTipoPlataformasId();

                        // Plataformas y convenios
                        if ((int) $tipoPlataformasId === $idTipoVenta) {
                            return DB::table('z_plataforma_credito')
                                ->orderBy('plataforma')
                                ->pluck('plataforma', 'idplataforma')
                                ->toArray();
                        }

                        self::ensureMediosPagoCache();

                        // Cliente final (1) y Recargas (4)
                        if (in_array($idTipoVenta, [1, 4], true)) {
                            $mediosPermitidos = [1, 2, 3, 10];
                            if ($idTipoVenta === 1) {
                                $mediosPermitidos[] = 5; // Contraentrega
                            }

                            return array_intersect_key(
                                self::$mediosPagoTodos,
                                array_flip($mediosPermitidos)
                            );
                        }

                        // Distribución (5)
                        if ($idTipoVenta === 5) {
                            $mediosPermitidos = [1, 2, 3, 8, 10];
                            return array_intersect_key(
                                self::$mediosPagoTodos,
                                array_flip($mediosPermitidos)
                            );
                        }

                        return self::$mediosPagoTodos;
                    })
                    ->searchable()
                    ->reactive()
                    ->required()
                    ->disabled(function (Get $get) {
                        // si no hay tipo de venta, obviamente no debe tocarse
                        if (blank($get('Tipo_venta'))) {
                            return true;
                        }

                        // mismo flag que usas para bloquear Tipo_venta
                        $bloquear = (bool) $get('bloquear_tipo_venta');

                        if (! $bloquear) {
                            // creación normal, medio de pago editable
                            return false;
                        }

                        // si vengo desde cliente y es PLATAFORMAS Y CONVENIOS -> lo bloqueo
                        $tipoPlataformasId = self::getTipoPlataformasId();
                        $idTipoVenta       = (int) ($get('Tipo_venta') ?? 0);

                        return $idTipoVenta === (int) $tipoPlataformasId;
                    })
                    ->rule(function (Get $get) {
                        return function (string $attribute, $value, Closure $fail) use ($get) {
                            $idTipoVenta  = (int) ($get('Tipo_venta') ?? 0);
                            $codigoAlerta = $get('cartera_alerta_codigo');
                            $motivo       = $get('cartera_alerta_mensaje');

                            if ($idTipoVenta === 5 && (int) ($value ?? 0) === 8 && $codigoAlerta) {
                                $mensajeNotificacion = $motivo
                                    ?: 'Hay una alerta de cartera. Comunícate con el área de cartera.';

                                Notification::make()
                                    ->title('Alerta de cartera')
                                    ->body(nl2br($mensajeNotificacion))
                                    ->danger()
                                    ->duration(8000)
                                    ->send();

                                $fail('Hay una alerta de cartera');
                            }
                        };
                    })
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        $idMedio = (int) $state;
                        $idTipo  = (int) ($get('Tipo_venta') ?? 0);

                        $tipoPlataformasId = self::getTipoPlataformasId();

                        Log::info('TipoFacturaStep.medio_pago.afterStateUpdated', [
                            'Tipo_venta' => $idTipo,
                            'medio_pago' => $state,
                            'cedula'     => $get('cedula'),
                        ]);

                // inicio . se utiliza para medios de pago , para refrescar bancos y codigos

                         if (in_array($idMedio, [2, 3, 10], true)) {
                            $bancoId = null;

                            if ($idMedio === 3) {
                                // WOMPI → banco 1
                                $bancoId = 1;
                            } elseif ($idMedio === 10) {
                                // LLAVE BRE-B → banco 5
                                $bancoId = 5;
                            }

                            Log::info('TipoFacturaStep.medio_pago.banco_principal_set', [
                                'medio_pago' => $idMedio,
                                'banco_id'   => $bancoId,
                            ]);

                            $set('banco_transferencia_principal_id', $bancoId);
                        } else {
                            Log::info('TipoFacturaStep.medio_pago.banco_principal_clear', [
                                'medio_pago' => $idMedio,
                            ]);

                            $set('banco_transferencia_principal_id', null);
                        }
                // fin . se utiliza para medios de pago , para refrescar bancos y codigos

                        // PLATAFORMAS Y CONVENIOS
                        if ((int) $tipoPlataformasId === $idTipo) {
                            if ($idMedio) {
                                $codigoPlataforma = DB::table('z_plataforma_credito')
                                    ->where('idplataforma', $idMedio)
                                    ->value('mediosPagoCodigos');

                                $set('codigo_caja', $codigoPlataforma ?: null);
                            } else {
                                $set('codigo_caja', null);
                            }

                            $clienteId = self::getClienteId();
                            if ($clienteId) {
                                $idDependencia = self::getDetalleClienteCampo($clienteId, 'Id_Dependencia');

                                $set('dependencia_medio_pago', $idDependencia ?: null);
                                $set('codigo_dependencia', self::getCodigoDependencia($idDependencia));
                            } else {
                                $set('dependencia_medio_pago', null);
                                $set('codigo_dependencia', null);
                            }

                            $set('dependencia_contraentrega', null);
                            self::resetCartera($set);

                            return;
                        }

                        // Resto de tipos de venta
                        self::ensureMediosPagoCache();

                        if (! $idMedio || ! isset(self::$mediosPagoTodos[$idMedio])) {
                            $set('codigo_caja', null);
                            $set('dependencia_contraentrega', null);
                            $set('dependencia_medio_pago', null);
                            $set('codigo_dependencia', null);
                            self::resetCartera($set);
                            return;
                        }

                        $codigoMedio = self::$mediosPagoCodigos[$idMedio] ?? null;

                        Hidden::make('medio_pago_nombre')->dehydrated(false);

                        Log::info('TipoFacturaStep.medio_pago.branch_check', [
                            'ID_Tipo_Venta' => $idTipo,
                            'ID_Medio_Pago' => $idMedio,
                        ]);

                        if ($idTipo === 5 && $idMedio === 8) {
                            self::resolverSeguimientoCartera($set, $get);
                        } else {
                            self::resetCartera($set);
                        }

                        if ($idMedio === 5) {
                            $set('codigo_caja', $codigoMedio);
                            $set('dependencia_medio_pago', null);
                            $set('dependencia_contraentrega', null);
                            $set('codigo_dependencia', null);
                            return;
                        }

                        if ($idMedio === 8) {
                            $set('dependencia_contraentrega', null);
                            $set('dependencia_medio_pago', null);
                            $set('codigo_dependencia', null);
                            $set('codigo_caja', $codigoMedio);
                            return;
                        }

                        $set('dependencia_contraentrega', null);

                        if (! in_array($idMedio, [1, 2, 3, 10], true)) {
                            $set('codigo_caja', null);
                            return;
                        }

                        $user   = auth()->user();
                        $cedula = $user->cedula ?? null;

                        if (! $cedula) {
                            $set('codigo_caja', null);
                            return;
                        }

                        $sede = self::getSedeFromUser($cedula);

                        if ($sede) {
                            $set('codigo_caja', $sede->Codigo_caja);
                            return;
                        }

                        $set('codigo_caja', null);
                    }),

                // DEPENDENCIA CONTRAENTREGA
                Select::make('dependencia_contraentrega')
                    ->label('Dependencia')
                    ->options(function () {
                        if (self::$dependenciasContraentrega === null) {
                            self::$dependenciasContraentrega = Dependencias::whereIn('Id_Dependencia', [1, 2, 3])
                                ->orderBy('Codigo_Dependencia', 'desc')
                                ->get()
                                ->mapWithKeys(fn ($dep) => [
                                    $dep->Id_Dependencia => $dep->Nombre_Dependencia,
                                ])
                                ->toArray();
                        }

                        return self::$dependenciasContraentrega;
                    })
                    ->reactive()
                    ->required(fn (Get $get) => (int) $get('medio_pago') === 5)
                    ->hidden(function (Get $get) {
                        $idTipo = (int) ($get('Tipo_venta') ?? 0);
                        if (in_array($idTipo, [4, 5], true)) {
                            return true;
                        }

                        return (int) $get('medio_pago') !== 5;
                    }),

                // DEPENDENCIA MEDIO DE PAGO
                Select::make('dependencia_medio_pago')
                    ->label('Dependencia')
                    ->options(function (Get $get) {
                        // cache de TODAS las dependencias
                        static $todas = null;
                        static $soloDomicilioNoAplica = null;

                        if ($todas === null) {
                            $todas = Dependencias::orderBy('Nombre_Dependencia')
                                ->get()
                                ->mapWithKeys(function ($dep) {
                                    return [
                                        $dep->Id_Dependencia => $dep->Nombre_Dependencia,
                                    ];
                                })
                                ->toArray();
                        }

                        if ($soloDomicilioNoAplica === null) {
                            $soloDomicilioNoAplica = Dependencias::whereIn('Id_Dependencia', [9, 11])
                                ->orderBy('Nombre_Dependencia')
                                ->get()
                                ->mapWithKeys(function ($dep) {
                                    return [
                                        $dep->Id_Dependencia => $dep->Nombre_Dependencia,
                                    ];
                                })
                                ->toArray();
                        }

                        $medio            = (int) $get('medio_pago');
                        $idTipo           = (int) ($get('Tipo_venta') ?? 0);
                        $tipoPlataformasId = self::getTipoPlataformasId();

                        // Si es PLATAFORMAS Y CONVENIOS → dejo TODAS las dependencias
                        if ($idTipo === (int) $tipoPlataformasId) {
                            return $todas;
                        }

                        // Si el medio de pago es de caja (1=Efectivo, 2=Transferencia, 3=Wompi, 10=BRE-B)
                        if (in_array($medio, [1, 2, 3, 10], true)) {
                            // solo DOMICILIO y NO APLICA
                            return $soloDomicilioNoAplica;
                        }

                        // En cualquier otro caso no muestro nada
                        return [];
                    })
                    ->default(function (Get $get) {
                        if (! request()->has('cliente_id')) {
                            return null;
                        }

                        $clienteId        = request()->get('cliente_id');
                        $tipoPlataformasId = self::getTipoPlataformasId();
                        $idTipo           = (int) ($get('Tipo_venta') ?? 0);

                        if ($idTipo !== (int) $tipoPlataformasId) {
                            return null;
                        }

                        // Para plataformas, leo la dependencia desde detalles_cliente
                        return DB::table('detalles_cliente')
                            ->where('id_cliente', $clienteId)
                            ->value('Id_Dependencia');
                    })
                    ->reactive()
                    ->required(function (Get $get) {
                        $medio            = (int) $get('medio_pago');
                        $idTipo           = (int) ($get('Tipo_venta') ?? 0);
                        $tipoPlataformasId = self::getTipoPlataformasId();

                        if ($idTipo === (int) $tipoPlataformasId) {
                            return true;
                        }

                        return in_array($medio, [1, 2, 3, 10], true);
                    })
                    ->hidden(function (Get $get) {
                        $idTipo           = (int) ($get('Tipo_venta') ?? 0);
                        $medio            = (int) $get('medio_pago');
                        $tipoPlataformasId = self::getTipoPlataformasId();

                        if (in_array($idTipo, [4, 5], true)) {
                            return true;
                        }

                        if ($idTipo === (int) $tipoPlataformasId) {
                            return false;
                        }

                        return ! in_array($medio, [1, 2, 3, 10], true);
                    })
                    ->disabled(function (Get $get) {
                        // mismo flag que usaste para Tipo_venta
                        $bloquear = (bool) $get('bloquear_tipo_venta');

                        if (! $bloquear) {
                            // creación normal, editable
                            return false;
                        }

                        $tipoPlataformasId = self::getTipoPlataformasId();
                        $idTipoVenta       = (int) ($get('Tipo_venta') ?? 0);

                        // si vengo desde cliente y es PLATAFORMAS Y CONVENIOS → no dejo cambiar dependencia
                        return $idTipoVenta === (int) $tipoPlataformasId;
                    })
                    ->afterStateHydrated(function ($state, Set $set) {
                        $set('codigo_dependencia', self::getCodigoDependencia($state));
                    })
                    ->afterStateUpdated(function ($state, Set $set) {
                        $set('codigo_dependencia', self::getCodigoDependencia($state));
                    }),

                // DÍAS ATRASO
                TextInput::make('dias_atraso')
                    ->label('Días de atraso cartera')
                    ->disabled(fn () => true)
                    ->dehydrated(true)
                    ->extraInputAttributes(['readonly' => true])
                    ->reactive()
                    ->hidden(function (Get $get) {
                        $idTipo = (int) ($get('Tipo_venta') ?? 0);
                        $medio  = (int) $get('medio_pago');
                        return ! ($idTipo === 5 && $medio === 8);
                    }),

                // SEGUIMIENTO CARTERA
                Textarea::make('seguimiento_cartera')
                    ->label('Seguimiento de cartera')
                    ->rows(6)
                    ->disabled(fn () => true)
                    ->dehydrated(true)
                    ->extraInputAttributes(['readonly' => true])
                    ->reactive()
                    ->hidden(function (Get $get) {
                        $idTipo = (int) ($get('Tipo_venta') ?? 0);
                        $medio  = (int) $get('medio_pago');
                        return ! ($idTipo === 5 && $medio === 8);
                    }),

                // UNIDAD DE NEGOCIO
                Select::make('Unidad_negocio')
                    ->label('Unidad de negocio')
                    ->default(function (Get $get) {
                        $clienteId = self::getClienteId();
                        if (! $clienteId) {
                            return null;
                        }

                        $tipoPlataformasId = self::getTipoPlataformasId();
                        $idTipoVenta       = (int) ($get('Tipo_venta') ?? 0);

                        if ($idTipoVenta !== (int) $tipoPlataformasId) {
                            return null;
                        }

                        return self::getDetalleClienteCampo($clienteId, 'idcomision');
                    })
                    ->options(function (Get $get) {
                        $idTipo = (int) ($get('Tipo_venta') ?? 0);
                        if (! $idTipo) {
                            return [];
                        }

                        if (self::$comisionesClienteFinal === null) {
                            self::$comisionesClienteFinal = ZComision::whereIn('id', [1, 2, 3, 4, 5, 6])
                                ->orderBy('id')
                                ->pluck('comision', 'id')
                                ->toArray();
                        }

                        if (self::$comisionesTodas === null) {
                            self::$comisionesTodas = ZComision::orderBy('comision')
                                ->pluck('comision', 'id')
                                ->toArray();
                        }

                        if ($idTipo === 1) {
                            return self::$comisionesClienteFinal;
                        }

                        if ($idTipo === 5) {
                            $comision = ZComision::find(6);
                            return $comision
                                ? [$comision->id => $comision->comision]
                                : [];
                        }

                        if ($idTipo === 4) {
                            $comision = ZComision::where('comision', 'TIENDA FÍSICA')->first();
                            return $comision
                                ? [$comision->id => $comision->comision]
                                : [];
                        }

                        return self::$comisionesTodas;
                    })
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->disabled(function (Get $get) {
                        $idTipo = (int) ($get('Tipo_venta') ?? 0);

                        // sigue deshabilitada para RECARGAS (4) y DISTRIBUCIÓN (5) como antes
                        if (in_array($idTipo, [5, 4], true)) {
                            return true;
                        }

                        // mismo flag que usamos para bloquear cuando viene de cliente
                        $bloquear = (bool) $get('bloquear_tipo_venta');
                        if (! $bloquear) {
                            // creación normal, editable
                            return false;
                        }

                        $tipoPlataformasId = self::getTipoPlataformasId();

                        // si viene desde cliente y es PLATAFORMAS Y CONVENIOS → bloqueo
                        return $idTipo === (int) $tipoPlataformasId;
                    })
                    ->afterStateUpdated(function ($state, Set $set) {
                        $comision = ZComision::find($state);
                        $set('unidad_negocio', $comision->Cod_Unidad_neg ?? null);
                        $set('nombre_tercero', null);
                    }),

                // NOMBRE TERCERO
                Select::make('nombre_tercero')
                    ->label(function (Get $get) {
                        $tipo = (int) $get('Unidad_negocio');

                        $labels = [
                            1 => 'Nombre del referido',
                            2 => 'Nombre del aliado',
                            5 => 'Nombre del pregonero',
                        ];

                        return $labels[$tipo] ?? 'Nombre del tercero';
                    })
                    ->options(function (Get $get) {
                        $tipoComision = (int) $get('Unidad_negocio');

                        $map = [
                           
                            2 => 4, // Aliado
                            1 => 5, // Referido
                            5 => 6, // Pregonero
                        ];

                        if (! array_key_exists($tipoComision, $map)) {
                            return [];
                        }

                        $idTipoTercero = $map[$tipoComision];

                        if (! isset(self::$tercerosPorTipo[$idTipoTercero])) {
                            self::$tercerosPorTipo[$idTipoTercero] = DB::table('terceros')
                                ->where('id_tipo_tercero', $idTipoTercero)
                                ->where('estado', 1)
                                ->orderBy('nombre_tercero')
                                ->pluck('nombre_tercero', 'id_tercero')
                                ->toArray();
                        }

                        return self::$tercerosPorTipo[$idTipoTercero];
                    })
                    ->placeholder('Seleccione el tercero')
                    ->searchable()
                    ->reactive()
                    ->hidden(function (Get $get) {
                        $idTipo = (int) ($get('Tipo_venta') ?? 0);
                        if ($idTipo !== 1) {
                            return true;
                        }

                        return ! in_array((int) $get('Unidad_negocio'), [1, 2, 5], true);
                    })
                    ->required(function (Get $get) {
                        $idTipo = (int) ($get('Tipo_venta') ?? 0);
                        if ($idTipo !== 1) {
                            return false;
                        }

                        return in_array((int) $get('Unidad_negocio'), [1, 2, 5], true);
                    })
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        if (! $state) {
                            return;
                        }

                        $unidadNegocio = (int) ($get('Unidad_negocio') ?? 0);

                        // Ahora actuamos para: REFERIDO (1), ALIADO (2),  y PREGONERO (5)
                        if (! in_array($unidadNegocio, [1, 2, 5], true)) {
                            return;
                        }

                        $nombreTercero = DB::table('terceros')
                            ->where('id_tercero', $state)
                            ->value('nombre_tercero');

                        if (! $nombreTercero) {
                            return;
                        }

                        $prefijo = match ($unidadNegocio) {
                            1       => 'Referido: ',
                            2       => 'Aliado: ',
                            5       => 'Pregonero: ',
                            default => '',
                        };

                        // Se llena el campo del step "Pago factura"
                        $set('observaciones_pago', $prefijo . $nombreTercero);
                    })
                    ->suffixAction(
                        Action::make('crear_tercero')
                            ->icon('heroicon-o-user-plus')
                            ->label('')
                            ->modalHeading('Crear tercero')
                            ->modalWidth('md')
                            ->visible(function (Get $get) {
                                $unidadId = (int) ($get('Unidad_negocio') ?? 0);
                                if (! $unidadId) {
                                    return false;
                                }

                                if (in_array($unidadId, [4, 6], true)) {
                                    return false;
                                }

                                $comision = ZComision::find($unidadId);
                                if (! $comision) {
                                    return true;
                                }

                                $nombre = strtoupper($comision->comision);
                                if (in_array($nombre, ['AGENTE', 'TIENDA FISICA', 'TIENDA FÍSICA'], true)) {
                                    return false;
                                }

                                return true;
                            })
                            ->disabled(fn (Get $get) => blank($get('Unidad_negocio')))
                            ->form([
                                Hidden::make('id_sede_para_tercero')
                                    ->default(function (Get $get) {
                                        $sedePago = $get('detallesCliente.0.sede_pago');
                                        $sede     = $get('detallesCliente.0.idsede');

                                        if ($sedePago ?? $sede) {
                                            return $sedePago ?? $sede;
                                        }

                                        $user   = auth()->user();
                                        $cedula = $user?->cedula;

                                        if (! $cedula) {
                                            return null;
                                        }

                                        $sedeModel = self::getSedeFromUser($cedula);
                                        return $sedeModel?->ID_Sede;
                                    }),

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
                                    ->options(function () {
                                        if (self::$tiposDocumento === null) {
                                            self::$tiposDocumento = TipoDocumento::pluck('desc_identificacion', 'ID_Identificacion_Tributaria')
                                                ->toArray();
                                        }

                                        return self::$tiposDocumento;
                                    })
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('numero_documento')
                                    ->label('Número de documento')
                                    ->maxLength(30)
                                    ->required(),

                                TextInput::make('correo')
                                    ->label('Correo')
                                    ->email()
                                    ->maxLength(150),

                                TextInput::make('telefono')
                                    ->label('Teléfono')
                                    ->maxLength(30),
                            ])
                            ->action(function (array $data, Get $get, Set $set) {
                                try {
                                    $unidadNegocio = (int) ($get('Unidad_negocio') ?? 0);

                                    if (! $unidadNegocio) {
                                        Notification::make()
                                            ->title('Unidad de negocio no definida')
                                            ->body('Debo tener una unidad de negocio seleccionada antes de crear el tercero.')
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    $map = [
                                        3 => 3,
                                        2 => 4,
                                        1 => 5,
                                        5 => 6,
                                    ];

                                    if (! array_key_exists($unidadNegocio, $map)) {
                                        Notification::make()
                                            ->title('No se puede determinar el tipo de tercero')
                                            ->body('La unidad de negocio seleccionada no tiene un tipo de tercero asociado para creación automática.')
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    $idTipoTercero = $map[$unidadNegocio];

                                    $permitidos = [1, 2, 4, 5, 6, 3];
                                    if (! in_array($idTipoTercero, $permitidos, true)) {
                                        Notification::make()
                                            ->title('Tipo de tercero no permitido')
                                            ->body('Solo puedo crear terceros de tipo 1, 2, 4, 5 y 6.')
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    $idSede = $data['id_sede_para_tercero'] ?? null;

                                    if (! $idSede) {
                                        Notification::make()
                                            ->title('Sede no definida')
                                            ->body('No encontré una sede seleccionada previamente para asignar al tercero.')
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    $tercero = Agentes::create([
                                        'nombre_pv'        => $data['nombre_pv'] ?? null,
                                        'nombre_tercero'   => $data['nombre_tercero'] ?? null,
                                        'tipo_documento'   => $data['tipo_documento'] ?? null,
                                        'numero_documento' => $data['numero_documento'] ?? null,
                                        'correo'           => $data['correo'] ?? null,
                                        'telefono'         => $data['telefono'] ?? null,
                                        'estado'           => 1,
                                        'id_sede'          => $idSede,
                                        'id_tipo_tercero'  => $idTipoTercero,
                                    ]);

                                    if ($tercero && isset($tercero->id_tercero)) {
                                        $set('nombre_tercero', $tercero->id_tercero);
                                    }

                                    Notification::make()
                                        ->title('Tercero creado')
                                        ->body('Creé y guardé el tercero correctamente.')
                                        ->success()
                                        ->send();

                                } catch (\Throwable $e) {
                                    Log::error('Error creando tercero desde TipoFacturaStep', [
                                        'data'  => $data,
                                        'error' => $e->getMessage(),
                                    ]);

                                    Notification::make()
                                        ->title('Error al crear el tercero')
                                        ->body('No pude guardar el tercero. Revisa la información e intenta nuevamente.')
                                        ->danger()
                                        ->send();
                                }
                            })
                    ),

                // CODIGO UNIDAD DE NEGOCIO
                TextInput::make('unidad_negocio')
                    ->label('Codigo unidad de negocio')
                    ->disabled()
                    ->required()
                    ->dehydrated(true)
                    ->default(function () {
                        $clienteId = self::getClienteId();
                        if (! $clienteId) {
                            return null;
                        }

                        $idComision = self::getDetalleClienteCampo($clienteId, 'idcomision');
                        if (! $idComision) {
                            return null;
                        }

                        return ZComision::where('id', $idComision)->value('Cod_Unidad_neg');
                    }),

                // CODIGO MEDIO DE PAGO
                TextInput::make('codigo_caja')
                    ->label('Codigo medio  de pago')
                    ->disabled()
                    ->dehydrated(true)
                    ->reactive()
                    ->default(function () {
                        $clienteId = self::getClienteId();
                        if (! $clienteId) {
                            return null;
                        }

                        $idPlataforma = self::getDetalleClienteCampo($clienteId, 'idplataforma');
                        if (! $idPlataforma) {
                            return null;
                        }

                        $codigoMedio = DB::table('z_plataforma_credito')
                            ->where('idplataforma', $idPlataforma)
                            ->value('mediosPagoCodigos');

                        Log::info('TipoFacturaStep.codigo_caja.default', [
                            'cliente_id'   => $clienteId,
                            'idPlataforma' => $idPlataforma,
                            'codigoMedio'  => $codigoMedio,
                        ]);

                        return $codigoMedio ?: null;
                    }),

                // CODIGO DEPENDENCIA
                TextInput::make('codigo_dependencia')
                    ->label('Código dependencia')
                    ->disabled()
                    ->dehydrated(true)
                    ->reactive()
                    ->default(function (Get $get) {
                        $idDep = (int) $get('dependencia_medio_pago');

                        if ($idDep) {
                            return self::getCodigoDependencia($idDep);
                        }

                        $clienteId = self::getClienteId();
                        if ($clienteId) {
                            $idDependencia = self::getDetalleClienteCampo($clienteId, 'Id_Dependencia');
                            if ($idDependencia) {
                                return self::getCodigoDependencia($idDependencia);
                            }
                        }

                        return null;
                    })
                    ->hidden(fn (Get $get) => blank($get('dependencia_medio_pago'))),

                // CAMPOS OCULTOS CARTERA
                Hidden::make('cartera_alerta_codigo')
                    ->dehydrated(false),

                Hidden::make('cartera_alerta_mensaje')
                    ->dehydrated(false),

                Hidden::make('cartera_cupo_disponible') // <--- NUEVO
                    ->dehydrated(true)
                    ->default(0),

                // RECOMPRA
                Select::make('recompra')
                    ->label('Recompra')
                    ->options([
                        'SI' => 'Sí',
                        'NO' => 'No',
                    ])
                    ->default(function (Get $get) {
                        $idTipoVenta = (int) ($get('Tipo_venta') ?? 0);
                        $esRecompra  = self::calcularRecompra($get, $idTipoVenta);

                        if ($esRecompra === null) {
                            return 'NO';
                        }

                        return $esRecompra ? 'SI' : 'NO';
                    })
                    ->disabled()
                    ->dehydrated(true)
                    ->reactive()
                    ->hidden(function (Get $get) {
                        $idTipo            = (int) ($get('Tipo_venta') ?? 0);
                        $tipoPlataformasId = self::getTipoPlataformasId();

                        return ! in_array($idTipo, [1, (int) $tipoPlataformasId], true);
                    }),
            ])
            ->columns(2);
    }

    // ================== HELPERS / CACHES ==================

    /*protected static function getTipoPlataformasId(): ?int
    {
        if (self::$tipoVentaPlataformasId === null) {
            self::$tipoVentaPlataformasId = YTipoVenta::where('Nombre_Tipo_Venta', 'PLATAFORMAS Y CONVENIOS')
                ->value('ID_Tipo_Venta');
        }

        return self::$tipoVentaPlataformasId ? (int) self::$tipoVentaPlataformasId : null;
    }*/

    protected static function getTipoPlataformasId(): ?int
    {
        if (self::$tipoVentaPlataformasId === null) {
            self::$tipoVentaPlataformasId = YTipoVenta::where('Nombre_Tipo_Venta', 'PLATAFORMAS Y CONVENIOS')
                ->value('ID_Tipo_Venta');
        }

        return self::$tipoVentaPlataformasId ? (int) self::$tipoVentaPlataformasId : null;
    }

    protected static function getClienteId(): ?int
    {
        if (! request()->has('cliente_id')) {
            return null;
        }

        return (int) request()->get('cliente_id');
    }

    protected static function getDetalleClienteCampo(int $clienteId, string $campo): mixed
    {
        if (! isset(self::$detallesClienteCache[$clienteId])) {
            self::$detallesClienteCache[$clienteId] = DB::table('detalles_cliente')
                ->where('id_cliente', $clienteId)
                ->select('idplataforma', 'Id_Dependencia', 'idcomision')
                ->first() ?: null;
        }

        $row = self::$detallesClienteCache[$clienteId] ?? null;

        if (! $row) {
            return null;
        }

        return $row->{$campo} ?? null;
    }

    /**
     * Helper para cargar/cachéar medios de pago.
     */
    protected static function ensureMediosPagoCache(): void
    {
        if (self::$mediosPagoTodos !== null && self::$mediosPagoCodigos !== null) {
            return;
        }

        $rows = YMedioPago::orderBy('Nombre_Medio_Pago')
            ->get(['Id_Medio_Pago', 'Nombre_Medio_Pago', 'Codigo_forma_Pago']);

        self::$mediosPagoTodos   = $rows->pluck('Nombre_Medio_Pago', 'Id_Medio_Pago')->toArray();
        self::$mediosPagoCodigos = $rows->pluck('Codigo_forma_Pago', 'Id_Medio_Pago')->toArray();
    }

    /**
     * Helper para limpiar campos de cartera.
     */
    protected static function resetCartera(Set $set): void
    {
        $set('dias_atraso', null);
        $set('seguimiento_cartera', null);
        $set('cartera_alerta_codigo', null);
        $set('cartera_alerta_mensaje', null);
        $set('cartera_cupo_disponible', null); 
    }

    /**
     * Helper para obtener el código de dependencia.
     */
    protected static function getCodigoDependencia($idDep): ?string
    {
        if (! $idDep) {
            return null;
        }

        return Dependencias::where('Id_Dependencia', $idDep)
            ->value('Codigo_Dependencia') ?: null;
    }

    /**
     * Helper para obtener la sede del usuario (socio o asesor) con cache por cédula.
     */
    protected static function getSedeFromUser(?string $cedula): ?Sede
    {
        if (! $cedula) {
            return null;
        }

        if (array_key_exists($cedula, self::$sedeFromCedulaCache)) {
            return self::$sedeFromCedulaCache[$cedula];
        }

        $user = auth()->user();

        // Socio
        if ($user && $user->hasRole('socio')) {
            $socio = SocioDistritec::where('Cedula', $cedula)->first();

            if ($socio) {
                $sede = Sede::where('ID_Socio', $socio->ID_Socio)
                    ->orderByDesc('ID_Sede')
                    ->first();

                return self::$sedeFromCedulaCache[$cedula] = $sede ?: null;
            }
        }

        // Asesor
        $asesor = Asesor::where('Cedula', $cedula)->first();

        if ($asesor) {
            $aaPrin = AaPrin::where('ID_Asesor', $asesor->ID_Asesor)
                ->orderByDesc('ID_Inf_trab')
                ->first();

            if ($aaPrin) {
                $sede = Sede::find($aaPrin->ID_Sede);
                return self::$sedeFromCedulaCache[$cedula] = $sede ?: null;
            }
        }

        return self::$sedeFromCedulaCache[$cedula] = null;
    }

    /**
     * Calcula si aplica recompra.
     */
    protected static function calcularRecompra(Get $get, int $idTipoVenta): ?bool
    {
        $tipoPlataformasId = self::getTipoPlataformasId();

        if (! in_array($idTipoVenta, [1, (int) $tipoPlataformasId], true)) {
            return null;
        }

        $documento = $get('cedula') ?: request()->get('cedula');

        if (! $documento) {
            return null;
        }

        return self::consultarRecompraApi($documento);
    }

    protected static function resolverRecompra(Set $set, Get $get, int $idTipoVenta): void
    {
        $esRecompra = self::calcularRecompra($get, $idTipoVenta);

        if ($esRecompra === null) {
            $set('recompra', 'NO');
            return;
        }

        $set('recompra', $esRecompra ? 'SI' : 'NO');
    }

    protected static function consultarRecompraApi(string $documento): bool
    {
        try {
            $response = Http::asJson()->post(
                route('api.recompra.validar'),
                ['documento' => $documento]
            );

            Log::info('Recompra API (form)', [
                'documento' => $documento,
                'status'    => $response->status(),
            ]);

            if (! $response->successful()) {
                return false;
            }

            return (bool) data_get($response->json(), 'es_recompra', false);
        } catch (\Throwable $e) {
            Log::error('Recompra API error (form)', [
                'documento' => $documento,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected static function resolverSeguimientoCartera(Set $set, Get $get): void
{
    Log::info('resolverSeguimientoCartera: inicio', [
        'Tipo_venta' => $get('Tipo_venta'),
        'medio_pago' => $get('medio_pago'),
        'cedula'     => $get('cedula'),
    ]);

    $idTipoVenta = (int) ($get('Tipo_venta') ?? 0);
    if ($idTipoVenta !== 5) {
        Log::info('resolverSeguimientoCartera: no es distribución, limpio campos');
        self::resetCartera($set);
        return;
    }

    $medio = (int) $get('medio_pago');
    if ($medio !== 8) {
        Log::info('resolverSeguimientoCartera: medio de pago no es crédito, limpio campos');
        self::resetCartera($set);
        return;
    }

    $nit = $get('cedula');
    if (! $nit) {
        Log::warning('resolverSeguimientoCartera: nit vacío, no llamo API seguimiento');
        self::resetCartera($set);
        return;
    }

    try {
        $url = route('api.cartera.seguimiento');

        Log::info('resolverSeguimientoCartera: llamando API interna seguimiento', [
            'url' => $url,
            'nit' => $nit,
        ]);

        $response = Http::asJson()->post($url, [
            'nit' => (int) $nit,
        ]);

        Log::info('resolverSeguimientoCartera: respuesta API interna seguimiento', [
            'status' => $response->status(),
        ]);

        if (! $response->successful()) {
            $mensaje = 'No se pudo consultar el seguimiento de cartera. Comunícate con el área de cartera.';

            Notification::make()
                ->title('Error al consultar cartera')
                ->body($mensaje)
                ->danger()
                ->duration(8000)
                ->send();

            self::resetCartera($set);
            $set('cartera_alerta_codigo', 'ERROR_SEGUIMIENTO');
            $set('cartera_alerta_mensaje', $mensaje);
            return;
        }

        // AQUÍ recién existe $data
        $data = $response->json();

        // =========================
        // CUP0 DISPONIBLE AQUÍ
        // =========================
        $cupoDisponible = (int) data_get($data, 'saldo_disponible', 0);
        $set('cartera_cupo_disponible', $cupoDisponible);
        // si quieres usar valor_total aquí:
        $valorVenta = (int) ($get('valor_total') ?? 0);
        // =========================

        $resumen = data_get($data, 'resumen');
        if (! $resumen) {
            $resumen = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $maxDias = (int) data_get($data, 'max_dias_atraso_factura', 0);

        $codigoAlerta     = data_get($data, 'alerta.codigo');
        $mensajeAlertaApi = data_get($data, 'alerta.mensaje');

        $set('seguimiento_cartera', $resumen);
        $set('dias_atraso', $maxDias > 0 ? $maxDias : 0);
        $set('cartera_alerta_codigo', null);
        $set('cartera_alerta_mensaje', null);

        $success = (bool) data_get($data, 'success', false);
        if (! $success && ! $codigoAlerta) {
            $mensaje = 'La API de cartera respondió con un error. Comunícate con el área de cartera.';

            Notification::make()
                ->title('Error en validación de cartera')
                ->body($mensaje)
                ->danger()
                ->duration(8000)
                ->send();

            $set('cartera_alerta_codigo', 'ERROR_VALIDACION');
            $set('cartera_alerta_mensaje', $mensaje);
            return;
        }

        if ($codigoAlerta) {
            $titulo  = 'Alerta de cartera';
            $nivel   = 'warning';
            $mensaje = $mensajeAlertaApi ?: 'Existe una alerta en el flujo de cartera.';

            switch ($codigoAlerta) {
                case 'SIN_CONTROL_CUPO':
                    $titulo  = 'Sin control de cupo';
                    $nivel   = 'warning';
                    $mensaje = 'El cliente no tiene control de cupo configurado.';
                    break;

                case 'SIN_CUPO':
                    $titulo  = 'Cupo de crédito inactivo';
                    $nivel   = 'warning';
                    $mensaje = 'El cliente no tiene cupo de crédito activo.';
                    break;

                case 'SUPERA_DIAS_ATRASO':
                    $titulo  = 'Días de atraso excedidos';
                    $nivel   = 'danger';
                    $mensaje = 'Los días de atraso superan o igualan el máximo permitido.';
                    break;

                case 'SIN_SALDO_DISPONIBLE':
                    $titulo  = 'Cupo disponible agotado';
                    $nivel   = 'danger';
                    $mensaje = 'El cliente no tiene saldo disponible en el cupo de crédito.';
                    break;

                case 'SUPERA_DIAS_CONSIGNACION':
                    $titulo  = 'Días de consignación excedidos';
                    $nivel   = 'danger';
                    $mensaje = 'Los días en consignación superan los días de atraso permitidos.';
                    break;

                case 'SUPERA_VALOR_CONSIGNACION':
                    $titulo  = 'Valor de consignación excedido';
                    $nivel   = 'danger';
                    $mensaje = 'El valor total en consignación supera el cupo disponible.';
                    break;

                case 'ERROR_SEGUIMIENTO':
                    $titulo  = 'Error al consultar cartera';
                    $nivel   = 'danger';
                    $mensaje = 'No se pudo obtener la información de cartera.';
                    break;
            }

            $mensajeFinal = $mensaje . "\nComunícate con el área de cartera.";

            $set('cartera_alerta_codigo', $codigoAlerta);
            $set('cartera_alerta_mensaje', $mensajeFinal);

            $notification = Notification::make()
                ->title($titulo)
                ->body(nl2br($mensajeFinal))
                ->duration(8000);

            if ($nivel === 'danger') {
                $notification->danger();
            } else {
                $notification->warning();
            }

            $notification->send();
        } else {
            $set('cartera_alerta_codigo', null);
            $set('cartera_alerta_mensaje', null);
        }

        // Esta parte la puedes dejar si quieres notificación inmediata por valor_total actual:
        if ($valorVenta > 0 && $cupoDisponible > 0 && $valorVenta > $cupoDisponible) {
            $mensaje = "El valor de la venta supera el cupo disponible.\n" .
                "Cupo disponible: {$cupoDisponible}\n" .
                "Valor de la venta: {$valorVenta}\n" .
                "Comunícate con el área de cartera.";

            Notification::make()
                ->title('Cupo disponible excedido')
                ->body(nl2br($mensaje))
                ->danger()
                ->duration(8000)
                ->send();
        }

    } catch (\Throwable $e) {
        Log::error('resolverSeguimientoCartera: excepción al llamar API interna seguimiento', [
            'nit'   => $nit,
            'error' => $e->getMessage(),
        ]);

        $mensaje = 'Ocurrió un error inesperado al consultar la cartera. Comunícate con el área de cartera.';

        Notification::make()
            ->title('Error al consultar cartera')
            ->body($mensaje)
            ->danger()
            ->duration(8000)
            ->send();

        self::resetCartera($set);
        $set('cartera_alerta_codigo', 'ERROR_SEGUIMIENTO');
        $set('cartera_alerta_mensaje', $mensaje);
    }
}
}