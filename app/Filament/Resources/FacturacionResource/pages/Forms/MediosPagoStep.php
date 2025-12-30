<?php

namespace App\Filament\Resources\FacturacionResource\Forms;

use Closure;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\Select;
use Filament\Forms\ComponentContainer;

// MODELOS
use App\Models\YMedioPago;
use App\Models\ZPlataformaCredito;
use App\Models\YCuentaBanco;

// AGREGADO SOLO PARA CAJA RIFA
use App\Models\Sede;
use App\Models\Asesor;
use App\Models\AaPrin;

use Illuminate\Support\Facades\Log;
use Filament\Support\Exceptions\Halt;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;

class MediosPagoStep
{
    public static function make(): Step
    {
        return Step::make('Medios de pago')
            ->icon('heroicon-o-credit-card')
            ->schema([
                Section::make()
                    ->schema([
                        // ✅ Asegurar que existan en el estado del Wizard (por si Filament se pone delicado)
                        Hidden::make('total_factura')->default(0)->dehydrated(true),
                        Hidden::make('valor_total')->default(0)->dehydrated(true),

                        // Botón "Agregar método" alineado a la derecha
                        Actions::make([
                            Action::make('agregarMetodoPago')
                                ->label('Agregar Método')
                                ->icon('heroicon-o-plus')
                                ->color('primary')
                                ->modalHeading('Agregar Método de Pago')
                                ->modalDescription('Selecciona un método para agregarlo a la lista de pagos')
                                ->modalWidth('md')
                                ->modalSubmitActionLabel('Agregar')

                                // Copiamos el estado del formulario principal al modal
                                ->mountUsing(function (ComponentContainer $form, Get $get) {
                                    $form->fill([
                                        'principal_id'      => $get('medio_pago'),
                                        'metodos_usados'    => $get('metodos_pago'),
                                        'codigo_caja_padre' => $get('codigo_caja'),
                                        'tipo_venta_id'     => $get('Tipo_venta'),
                                    ]);
                                })

                                ->form([
                                    Hidden::make('principal_id'),
                                    Hidden::make('metodos_usados'),
                                    Hidden::make('codigo_caja_padre'),
                                    Hidden::make('tipo_venta_id'),

                                    TextInput::make('buscar_medio_pago')
                                        ->label('Buscar método de pago')
                                        ->placeholder('Escribe para filtrar los métodos de pago...')
                                        ->reactive()
                                        ->extraAttributes([
                                            'class' => 'mb-2',
                                        ]),

                                    Radio::make('tipo')
                                        ->label('Selecciona el método de pago')
                                        ->options(function (Get $get) {
                                            $busqueda = mb_strtoupper(trim((string) ($get('buscar_medio_pago') ?? '')));

                                            $codigoCaja       = $get('codigo_caja_padre');
                                            $medioPrincipalId = (int) ($get('principal_id') ?? 0);
                                            $tipoVentaId      = (int) ($get('tipo_venta_id') ?? 0);

                                            $idsProhibidos           = [];
                                            $codigosProhibidos       = [];
                                            $nombresProhibidosUpper  = [];
                                            $plataformaIdsProhibidos = [];

                                            $TIPO_PLATAFORMAS = 3;

                                            // ⬇ Control CAJA RIFA por sede.ver_CajaRifa
                                            $mostrarCajaRifa = false;
                                            try {
                                                $idSede = null;

                                                $idSedeForm = $get('detallesCliente.0.idsede') ?? $get('idsede');
                                                if ($idSedeForm) {
                                                    $idSede = (int) $idSedeForm;
                                                }

                                                if (! $idSede) {
                                                    $user   = auth()->user();
                                                    $cedula = $user?->cedula;

                                                    if ($cedula && ! $user?->hasRole('socio')) {
                                                        $asesor = Asesor::where('Cedula', $cedula)->first();
                                                        if ($asesor) {
                                                            $aaPrin = AaPrin::where('ID_Asesor', $asesor->ID_Asesor)
                                                                ->where('ID_Estado', '!=', 3)
                                                                ->orderByDesc('ID_Inf_trab')
                                                                ->first();

                                                            if ($aaPrin) {
                                                                $idSede = (int) $aaPrin->ID_Sede;
                                                            }
                                                        }
                                                    }
                                                }

                                                if ($idSede) {
                                                    $sede = Sede::find($idSede);
                                                    if ($sede) {
                                                        $raw = $sede->ver_CajaRifa;
                                                        $valorNormalizado = strtoupper(trim((string) $raw));
                                                        $mostrarCajaRifa = ($valorNormalizado === 'TRUE');
                                                    }
                                                }
                                            } catch (\Throwable $e) {
                                                // Silencioso
                                            }
                                            // ⬆ FIN BLOQUE CAJA RIFA

                                            // 1) Plataforma principal / medio principal
                                            if ($medioPrincipalId > 0) {
                                                if ($tipoVentaId === $TIPO_PLATAFORMAS) {
                                                    $plataformaPrincipal = ZPlataformaCredito::find($medioPrincipalId);

                                                    if ($plataformaPrincipal) {
                                                        $plataformaIdsProhibidos[] = (int) $plataformaPrincipal->idplataforma;

                                                        $nombrePlatUpper = mb_strtoupper(
                                                            trim((string) $plataformaPrincipal->plataforma)
                                                        );
                                                        if ($nombrePlatUpper !== '') {
                                                            $nombresProhibidosUpper[] = $nombrePlatUpper;
                                                        }

                                                        $codigoPlat = $plataformaPrincipal->mediosPagoCodigos ?? null;
                                                        if ($codigoPlat !== null && $codigoPlat !== '') {
                                                            $codigosProhibidos[] = (string) $codigoPlat;
                                                        }
                                                    }
                                                } else {
                                                    $medioPrincipal = YMedioPago::where('Id_Medio_Pago', $medioPrincipalId)->first();

                                                    if ($medioPrincipal) {
                                                        $idsProhibidos[] = (int) $medioPrincipal->Id_Medio_Pago;

                                                        $nombrePrincipalUpper = mb_strtoupper(
                                                            trim((string) $medioPrincipal->Nombre_Medio_Pago)
                                                        );
                                                        if ($nombrePrincipalUpper !== '') {
                                                            $nombresProhibidosUpper[] = $nombrePrincipalUpper;
                                                        }

                                                        $codigoPrincipal = $medioPrincipal->Codigo_forma_Pago;
                                                        if ($codigoPrincipal !== null && $codigoPrincipal !== '') {
                                                            $codigosProhibidos[] = (string) $codigoPrincipal;
                                                        }
                                                    }
                                                }
                                            }

                                            // 2) Métodos ya agregados
                                            $metodos = $get('metodos_usados') ?? [];
                                            foreach ($metodos as $metodo) {
                                                if (!empty($metodo['medio_id'])) {
                                                    $idUsado = (int) $metodo['medio_id'];
                                                    $idsProhibidos[] = $idUsado;

                                                    $medioUsado = YMedioPago::where('Id_Medio_Pago', $idUsado)->first();

                                                    if ($medioUsado) {
                                                        $nombreUsadoUpper = mb_strtoupper(
                                                            trim((string) $medioUsado->Nombre_Medio_Pago)
                                                        );
                                                        if ($nombreUsadoUpper !== '') {
                                                            $nombresProhibidosUpper[] = $nombreUsadoUpper;
                                                        }

                                                        $codigoUsado = $medioUsado->Codigo_forma_Pago;
                                                        if ($codigoUsado !== null && $codigoUsado !== '') {
                                                            $codigosProhibidos[] = $codigoUsado;
                                                        }
                                                    }
                                                }

                                                if (!empty($metodo['plataforma_id'])) {
                                                    $plataformaIdsProhibidos[] = (int) $metodo['plataforma_id'];
                                                }
                                            }

                                            $idsProhibidos           = array_values(array_unique($idsProhibidos));
                                            $codigosProhibidos       = array_values(array_unique(array_filter(
                                                $codigosProhibidos,
                                                fn ($c) => $c !== null && $c !== ''
                                            )));
                                            $nombresProhibidosUpper  = array_values(array_unique(array_filter(
                                                $nombresProhibidosUpper,
                                                fn ($n) => $n !== null && $n !== ''
                                            )));
                                            $plataformaIdsProhibidos = array_values(array_unique($plataformaIdsProhibidos));

                                            $opciones = [];

                                            $soloBasicos = in_array($tipoVentaId, [1, 4, 5], true);
                                            $mediosPermitidosBasicos = [1, 2, 3, 10];

                                            $medios = YMedioPago::orderBy('Nombre_Medio_Pago')->get();

                                            foreach ($medios as $medio) {
                                                $idMedio     = (int) $medio->Id_Medio_Pago;
                                                $nombre      = (string) $medio->Nombre_Medio_Pago;
                                                $nombreTrim  = trim($nombre);
                                                $nombreUpper = mb_strtoupper($nombreTrim);
                                                $codigoForma = $medio->Codigo_forma_Pago;

                                                if ($tipoVentaId === 3 && in_array($idMedio, [5, 8], true)) {
                                                    continue;
                                                }

                                                if ($soloBasicos && !in_array($idMedio, $mediosPermitidosBasicos, true)) {
                                                    continue;
                                                }

                                                if ($idMedio === 6 && ! $mostrarCajaRifa) {
                                                    continue;
                                                }

                                                if (
                                                    ($nombreTrim === '' || $nombreUpper === 'N/A') &&
                                                    ($codigoForma === null || $codigoForma === '')
                                                ) {
                                                    continue;
                                                }

                                                if (in_array($idMedio, $idsProhibidos, true)) {
                                                    continue;
                                                }

                                                if ($nombreUpper !== '' && in_array($nombreUpper, $nombresProhibidosUpper, true)) {
                                                    continue;
                                                }

                                                if (
                                                    $codigoForma !== null &&
                                                    $codigoForma !== '' &&
                                                    in_array((string) $codigoForma, $codigosProhibidos, true)
                                                ) {
                                                    continue;
                                                }

                                                $opciones[$idMedio] = $nombreTrim;
                                            }

                                            if (!$soloBasicos) {
                                                $plataformas = ZPlataformaCredito::orderBy('plataforma')->get();

                                                foreach ($plataformas as $plataforma) {
                                                    $idPlat       = (int) $plataforma->idplataforma;
                                                    $nombre       = (string) $plataforma->plataforma;
                                                    $nombreTrim   = trim($nombre);
                                                    $nombreUpper  = mb_strtoupper($nombreTrim);
                                                    $codigoPlat   = $plataforma->mediosPagoCodigos ?? null;
                                                    $tipoPlat     = mb_strtoupper(trim((string) ($plataforma->TipoPlataforma ?? '')));

                                                    if ($tipoPlat !== 'CONVENIO') {
                                                        continue;
                                                    }

                                                    if (
                                                        ($nombreTrim === '' || $nombreUpper === 'N/A') &&
                                                        ($codigoPlat === null || $codigoPlat === '')
                                                    ) {
                                                        continue;
                                                    }

                                                    if (in_array($idPlat, $plataformaIdsProhibidos, true)) {
                                                        continue;
                                                    }

                                                    $key = 'PLAT-' . $idPlat;
                                                    $opciones[$key] = $nombreTrim;
                                                }
                                            }

                                            if ($busqueda !== '') {
                                                $filtradas = [];
                                                foreach ($opciones as $key => $nombreOpcion) {
                                                    $nombreUpperOpt = mb_strtoupper(trim((string) $nombreOpcion));
                                                    if (mb_stripos($nombreUpperOpt, $busqueda) !== false) {
                                                        $filtradas[$key] = $nombreOpcion;
                                                    }
                                                }
                                                $opciones = $filtradas;
                                            }

                                            return $opciones;
                                        })
                                        ->inline(false)
                                        ->required(),
                                ])
                                ->action(function (array $data, Get $get, Set $set) {
                                    $metodos = $get('metodos_pago') ?? [];
                                    $numero  = count($metodos) + 2;

                                    $codigoCaja = $get('codigo_caja');
                                    $tipo       = $data['tipo'];

                                    if (is_string($tipo) && str_starts_with($tipo, 'PLAT-')) {
                                        $idPlataforma = (int) substr($tipo, 5);
                                        $plataforma   = ZPlataformaCredito::find($idPlataforma);

                                        $codigoRef = $plataforma?->mediosPagoCodigos ?? null;
                                        $nombre    = $plataforma?->plataforma ?? 'Plataforma';

                                        $metodos[] = [
                                            'medio_id'              => null,
                                            'plataforma_id'         => $idPlataforma,
                                            'plataforma_nombre'     => $nombre,
                                            'monto'                 => 0,
                                            'numero'                => $numero,
                                            'codigo_referencia'     => $codigoRef,
                                        ];
                                    } else {
                                        $medio     = YMedioPago::where('Id_Medio_Pago', (int) $tipo)->first();
                                        $codigoRef = self::getCodigoReferencia($medio, $codigoCaja);

                                        $medioId = (int) $tipo;

                                        $bancoId = null;
                                        if ($medioId === 3) {
                                            $bancoId = 1;
                                        } elseif ($medioId === 10) {
                                            $bancoId = 5;
                                        }

                                        $metodos[] = [
                                            'medio_id'                => (int) $tipo,
                                            'plataforma_id'           => null,
                                            'plataforma_nombre'       => null,
                                            'monto'                   => 0,
                                            'numero'                  => $numero,
                                            'codigo_referencia'       => $codigoRef,
                                            'banco_transferencia_id'  => $bancoId,
                                        ];
                                    }

                                    $set('metodos_pago', $metodos);

                                    self::actualizarPlataformaPrincipal($get, $set);
                                }),
                        ])
                            ->alignment('right')
                            ->extraAttributes([
                                'class' => 'flex justify-end mb-2',
                            ]),

                        // ✅ HEADER: SALDO PENDIENTE (usa total_factura real)
                        Placeholder::make('monto_factura_total_header')
                            ->label('')
                            ->content(function (Get $get) {
                                $montoOriginal    = self::getMontoFactura($get);
                                $totalDistribuido = self::calcularTotalDistribuido($get);
                                $diferencia       = $montoOriginal - $totalDistribuido;

                                $montoAbs   = '$' . number_format(abs($diferencia), 0, ',', '.');
                                $montoTexto = $diferencia < 0 ? '-' . $montoAbs : $montoAbs;

                                return 'SALDO PENDIENTE: ' . $montoTexto;
                            })
                            ->extraAttributes(function (Get $get) {
                                $montoOriginal    = self::getMontoFactura($get);
                                $totalDistribuido = self::calcularTotalDistribuido($get);
                                $diferencia       = $montoOriginal - $totalDistribuido;

                                $baseClass = 'block text-center font-extrabold tracking-wide mb-4';
                                $baseStyle = 'font-size:22px;';

                                if ($diferencia === 0.0) {
                                    return [
                                        'class' => $baseClass,
                                        'style' => $baseStyle . 'color:#82cc0e;',
                                    ];
                                }

                                if ($diferencia > 0) {
                                    return [
                                        'class' => $baseClass,
                                        'style' => $baseStyle . 'color:#f1c40f;',
                                    ];
                                }

                                return [
                                    'class' => $baseClass,
                                    'style' => $baseStyle . 'color:#ff0000;',
                                ];
                            }),

                        Placeholder::make('titulo_pago')
                            ->label('')
                            ->content('Pago de Factura')
                            ->extraAttributes([
                                'class' => 'text-center text-sm text-gray-500 dark:text-gray-400 mb-3',
                            ]),

                        // Banner principal: PUEDE / NO PUEDE FACTURAR
                        Grid::make()
                            ->columns([
                                'default' => 1,
                                'md'      => 2,
                            ])
                            ->extraAttributes([
                                'class' => 'relative',
                            ])
                            ->schema([
                                Placeholder::make('estado_pago_factura')
                                    ->label('')
                                    ->content(function (Get $get) {
                                        $montoOriginal    = self::getMontoFactura($get);
                                        $totalDistribuido = self::calcularTotalDistribuido($get);
                                        $diferencia       = $montoOriginal - $totalDistribuido;

                                        if ($diferencia === 0.0) {
                                            return 'PUEDE FACTURAR';
                                        }

                                        if ($diferencia > 0) {
                                            $faltante = $diferencia;

                                            $linea1 = 'NO PUEDE FACTURAR. FALTA POR PAGAR: $' .
                                                number_format($faltante, 0, ',', '.');
                                            $linea2 = 'LOS VALORES INGRESADOS SON MENORES AL MONTO TOTAL.';

                                            return $linea1 . "\n" . $linea2;
                                        }

                                        $exceso = abs($diferencia);

                                        $linea1 = 'NO PUEDE FACTURAR. EXCESO DE $' .
                                            number_format($exceso, 0, ',', '.');
                                        $linea2 = 'LOS VALORES INGRESADOS SUPERAN AL MONTO TOTAL.';

                                        return $linea1 . "\n" . $linea2;
                                    })
                                    ->extraAttributes(function (Get $get) {
                                        $montoOriginal    = self::getMontoFactura($get);
                                        $totalDistribuido = self::calcularTotalDistribuido($get);
                                        $diferencia       = $montoOriginal - $totalDistribuido;

                                        $base =
                                            'w-full max-w-xl mx-auto px-8 py-4 rounded-lg text-base font-semibold ' .
                                            'mt-2 md:mt-0 border shadow-sm text-center';

                                        if ($diferencia > 0) {
                                            return [
                                                'class' => $base . ' bg-yellow-50 dark:bg-yellow-900 dark:text-yellow-200 dark:border-yellow-500',
                                                'style' =>
                                                    'color:#a86b00;' .
                                                    'border:1px solid #ffcc00;' .
                                                    'text-transform:uppercase;' .
                                                    'white-space:pre-line;',
                                            ];
                                        }

                                        if ($diferencia === 0.0) {
                                            return [
                                                'class' => $base . ' bg-white dark:bg-gray-900 dark:text-green-400 dark:border-green-400',
                                                'style' =>
                                                    'color:#82cc0e;' .
                                                    'border:1px solid #82cc0e;' .
                                                    'box-shadow:0 0 8px rgba(130,204,14,0.5);' .
                                                    'text-transform:uppercase;' .
                                                    'white-space:pre-line;',
                                            ];
                                        }

                                        return [
                                            'class' => $base . ' bg-red-100 dark:bg-red-900 dark:text-red-200 dark:border-red-500',
                                            'style' =>
                                                'color:#b71c1c;' .
                                                'border:1px solid #ff0000;' .
                                                'text-transform:uppercase;' .
                                                'white-space:pre-line;',
                                        ];
                                    })
                                    ->columnSpan([
                                        'default' => 1,
                                        'md'      => 2,
                                    ]),
                            ]),

                        // FILA 1: Plataforma Principal
                        Section::make()
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Group::make()
                                            ->schema([
                                                Placeholder::make('fila1_numero')
                                                    ->label('')
                                                    ->content('1')
                                                    ->extraAttributes([
                                                        'class' =>
                                                            'flex items-center justify-center ' .
                                                            'w-8 h-8 rounded-full bg-gray-200 text-sm font-semibold text-gray-800 ' .
                                                            'dark:bg-gray-700 dark:text-gray-100',
                                                    ]),
                                                Placeholder::make('fila1_label')
                                                    ->label('')
                                                    ->content(function (Get $get) {
                                                        $rawMedio   = $get('medio_pago');
                                                        $tipoVenta  = (int) ($get('Tipo_venta') ?? 0);

                                                        if (! $rawMedio) {
                                                            return 'Plataforma Principal';
                                                        }

                                                        $TIPO_PLATAFORMAS = 3;

                                                        if ($tipoVenta === $TIPO_PLATAFORMAS) {
                                                            $idPlataforma = (int) $rawMedio;

                                                            if (! $idPlataforma) {
                                                                return 'Plataforma Principal';
                                                            }

                                                            $nombrePlataforma = ZPlataformaCredito::where('idplataforma', $idPlataforma)
                                                                ->value('plataforma');

                                                            return $nombrePlataforma ?: 'Plataforma Principal';
                                                        }

                                                        $idMedio = (int) $rawMedio;
                                                        if (! $idMedio) {
                                                            return 'Plataforma Principal';
                                                        }

                                                        $nombre = YMedioPago::where('Id_Medio_Pago', $idMedio)
                                                            ->value('Nombre_Medio_Pago');

                                                        return $nombre ?: 'Plataforma Principal';
                                                    })
                                                    ->extraAttributes([
                                                        'class' =>
                                                            'ml-3 text-sm font-semibold text-gray-900 dark:text-gray-100',
                                                    ]),
                                            ])
                                            ->extraAttributes([
                                                'class' => 'flex items-center',
                                            ])
                                            ->columnSpan(1),

                                        TextInput::make('plataforma_principal')
                                            ->label('')
                                            ->disabled()
                                            ->numeric()
                                            ->prefix('$')
                                            ->formatStateUsing(function ($state, Get $get) {
                                                $montoOriginal = self::getMontoFactura($get);
                                                $totalOtros    = self::calcularTotalOtros($get);

                                                $saldo = $montoOriginal - $totalOtros;
                                                if ($saldo < 0) {
                                                    $saldo = 0;
                                                }

                                                return number_format($saldo, 0, ',', '.');
                                            })
                                            ->dehydrateStateUsing(function ($state, Get $get) {
                                                $montoOriginal = self::getMontoFactura($get);
                                                $totalOtros    = self::calcularTotalOtros($get);

                                                $saldo = $montoOriginal - $totalOtros;
                                                if ($saldo < 0) {
                                                    $saldo = 0;
                                                }

                                                return (int) $saldo;
                                            })
                                            ->afterStateHydrated(function (TextInput $component, Get $get, Set $set) {
                                                self::actualizarPlataformaPrincipal($get, $set);
                                            })
                                            ->extraAttributes([
                                                'class' =>
                                                    'w-full text-right font-semibold text-gray-500 ' .
                                                    'bg-gray-100 border border-gray-300 rounded-lg ' .
                                                    'dark:text-gray-200 dark:bg-gray-800 dark:border-gray-700',
                                            ])
                                            ->columnSpan(1),

                                        Placeholder::make('fila1_resumen')
                                            ->label('')
                                            ->content(function (Get $get) {
                                                $montoOriginal = self::getMontoFactura($get);
                                                $totalOtros    = self::calcularTotalOtros($get);

                                                $saldo = $montoOriginal - $totalOtros;
                                                if ($saldo < 0) {
                                                    $saldo = 0;
                                                }

                                                return '$' . number_format($saldo, 0, ',', '.');
                                            })
                                            ->extraAttributes(function (Get $get) {
                                                $montoOriginal    = self::getMontoFactura($get);
                                                $totalDistribuido = self::calcularTotalDistribuido($get);

                                                $base = 'text-right text-sm font-semibold ';
                                                if ($totalDistribuido > $montoOriginal) {
                                                    $base .= 'text-red-600 dark:text-red-400';
                                                } else {
                                                    $base .= 'text-gray-900 dark:text-gray-100';
                                                }

                                                return ['class' => $base];
                                            })
                                            ->columnSpan(1),
                                    ]),

                                // Select de banco para transferencia como medio principal
                                Select::make('banco_transferencia_principal_id')
                                    ->label('Banco')
                                    ->options(function (Get $get) {
                                        $medio = (int) ($get('medio_pago') ?? 0);

                                        $query = YCuentaBanco::query()
                                            ->orderBy('Cuenta_Banco');

                                        if ($medio === 3) {
                                            $query->where('ID_Banco', 1);
                                        } elseif ($medio === 10) {
                                            $query->where('ID_Banco', 5);
                                        }

                                        return $query->pluck('Cuenta_Banco', 'ID_Banco')->toArray();
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->visible(fn (Get $get) => in_array((int) ($get('medio_pago') ?? 0), [2, 3, 10], true))
                                    ->required(fn (Get $get) => in_array((int) ($get('medio_pago') ?? 0), [2, 3, 10], true))
                                    ->disabled(fn (Get $get) => in_array((int) ($get('medio_pago') ?? 0), [3, 10], true))
                                    ->reactive()
                                    ->extraAttributes([
                                        'class' => 'mt-2 w-full',
                                    ]),

                                Placeholder::make('fila1_codigo_medio_pago')
                                    ->label('Código medio de pago')
                                    ->content(function (Get $get) {
                                        $codigo = $get('codigo_caja');

                                        if (! $codigo) {
                                            return 'Sin código asignado';
                                        }

                                        return $codigo;
                                    })
                                    ->extraAttributes([
                                        'class' =>
                                            'mt-2 w-full text-right text-xs font-semibold text-gray-600 ' .
                                            'bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 ' .
                                            'dark:text-gray-300 dark:bg-gray-800 dark:border-gray-700',
                                    ]),
                            ])
                            ->extraAttributes([
                                'class' =>
                                    'mt-4 rounded-xl border border-gray-200 bg-white px-4 py-3 ' .
                                    'dark:bg-gray-900 dark:border-gray-700',
                            ]),

                        // MÉTODOS ADICIONALES
                        Repeater::make('metodos_pago')
                            ->label('')
                            ->disableItemCreation()
                            ->inset()
                            ->schema([
                                Hidden::make('medio_id'),
                                Hidden::make('numero'),
                                Hidden::make('codigo_referencia')->dehydrated(false),
                                Hidden::make('plataforma_id')->dehydrated(false),
                                Hidden::make('plataforma_nombre')->dehydrated(false),

                                Group::make()
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                Group::make()
                                                    ->schema([
                                                        Placeholder::make('numero_visual')
                                                            ->label('')
                                                            ->content(function (Get $get) {
                                                                return (string) ($get('numero') ?? '');
                                                            })
                                                            ->extraAttributes([
                                                                'class' =>
                                                                    'flex items-center justify-center ' .
                                                                    'w-8 h-8 rounded-full bg-gray-200 ' .
                                                                    'text-sm font-semibold text-gray-800 ' .
                                                                    'dark:bg-gray-700 dark:text-gray-100',
                                                            ]),
                                                        Placeholder::make('label_medio')
                                                            ->label('')
                                                            ->content(function (Get $get) {
                                                                $plataformaNombre = $get('plataforma_nombre');
                                                                if ($plataformaNombre) {
                                                                    return $plataformaNombre;
                                                                }

                                                                $idMedio = $get('medio_id');

                                                                if (! $idMedio) {
                                                                    return 'Método de pago';
                                                                }

                                                                $nombre = YMedioPago::where('Id_Medio_Pago', $idMedio)
                                                                    ->value('Nombre_Medio_Pago');

                                                                return $nombre ?: 'Método de pago';
                                                            })
                                                            ->extraAttributes([
                                                                'class' =>
                                                                    'ml-3 text-sm font-semibold text-gray-900 dark:text-gray-100',
                                                            ]),
                                                    ])
                                                    ->extraAttributes([
                                                        'class' => 'flex items-center',
                                                    ])
                                                    ->columnSpan(1),

                                                TextInput::make('monto')
                                                    ->label('')
                                                    ->default(0)
                                                    ->prefix('$')
                                                    ->rules([
                                                        fn () => function (string $attribute, $value, Closure $fail) {
                                                            if (MediosPagoStep::parseMonto($value) <= 0) {
                                                                $fail('El monto del método de pago es obligatorio y debe ser mayor a 0.');
                                                            }
                                                        },
                                                    ])
                                                    ->dehydrateStateUsing(function ($state) {
                                                        if ($state === null || $state === '') {
                                                            return null;
                                                        }

                                                        $clean = (string) $state;
                                                        $clean = preg_replace('/,00$/', '', $clean);
                                                        $clean = str_replace('.', '', $clean);

                                                        if ($clean === '') {
                                                            return null;
                                                        }

                                                        return (int) $clean;
                                                    })
                                                    ->extraAttributes([
                                                        'class' =>
                                                            'w-full text-right font-semibold text-gray-900 ' .
                                                            'border border-gray-300 rounded-lg ' .
                                                            'dark:text-gray-100 dark:bg-gray-800 dark:border-gray-700',
                                                        'x-on:input' =>
                                                            "let input = \$event.target;
                                                             if (input.dataset.locked === '1') {
                                                                 return;
                                                             }
                                                             let valorLimpio = input.value.replace(/[^0-9]/g, '');
                                                             if (valorLimpio === '') {
                                                                 input.dataset.locked = '1';
                                                                 input.value = '0';

                                                                 if (input.dataset.unlockTimeoutId) {
                                                                     try { clearTimeout(parseInt(input.dataset.unlockTimeoutId)); } catch (e) {}
                                                                 }

                                                                 let timeoutId = setTimeout(function () {
                                                                     input.dataset.locked = '0';
                                                                     try { input.setSelectionRange(input.value.length, input.value.length); } catch (e) {}
                                                                 }, 1000);

                                                                 input.dataset.unlockTimeoutId = timeoutId;

                                                                 return;
                                                             }
                                                             let valor = valorLimpio.toString();
                                                             let partes = [];
                                                             while (valor.length > 3) {
                                                                 partes.unshift(valor.slice(-3));
                                                                 valor = valor.slice(0, -3);
                                                             }
                                                             partes.unshift(valor);
                                                             input.value = partes.join('.');",
                                                    ])
                                                    ->live(debounce: 800)
                                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                                        self::actualizarPlataformaPrincipal($get, $set);
                                                    })
                                                    ->columnSpan(1),

                                                Placeholder::make('resumen_monto')
                                                    ->label('')
                                                    ->content(function (Get $get) {
                                                        $valor = self::parseMonto($get('monto'));
                                                        return '$' . number_format($valor, 0, ',', '.');
                                                    })
                                                    ->extraAttributes([
                                                        'class' =>
                                                            'text-right text-sm font-semibold text-gray-900 dark:text-gray-100',
                                                    ])
                                                    ->columnSpan(1),
                                            ]),

                                        Select::make('banco_transferencia_id')
                                            ->label('Banco')
                                            ->options(function (Get $get) {
                                                $medio = (int) ($get('medio_id') ?? 0);

                                                $query = YCuentaBanco::query()
                                                    ->orderBy('Cuenta_Banco');

                                                if ($medio === 3) {
                                                    $query->where('ID_Banco', 1);
                                                } elseif ($medio === 10) {
                                                    $query->where('ID_Banco', 5);
                                                }

                                                return $query->pluck('Cuenta_Banco', 'ID_Banco')->toArray();
                                            })
                                            ->searchable()
                                            ->native(false)
                                            ->visible(function (Get $get) {
                                                $medio = (int) ($get('medio_id') ?? 0);
                                                return in_array($medio, [2, 3, 10], true);
                                            })
                                            ->required(function (Get $get) {
                                                $medio = (int) ($get('medio_id') ?? 0);
                                                return in_array($medio, [2, 3, 10], true);
                                            })
                                            ->afterStateHydrated(function (\Filament\Forms\Components\Select $component, Get $get) {
                                                $medio = (int) ($get('medio_id') ?? 0);

                                                if (blank($component->getState())) {
                                                    if ($medio === 3) {
                                                        $component->state(1);
                                                    } elseif ($medio === 10) {
                                                        $component->state(5);
                                                    }
                                                }
                                            })
                                            ->disabled(function (Get $get) {
                                                $medio = (int) ($get('medio_id') ?? 0);
                                                return in_array($medio, [3, 10], true);
                                            })
                                            ->reactive()
                                            ->extraAttributes([
                                                'class' => 'mt-2 w-full',
                                            ]),

                                        Placeholder::make('codigo_medio_pago_item')
                                            ->label('Código medio de pago')
                                            ->content(function (Get $get) {
                                                $codigo = $get('codigo_referencia');

                                                if (! $codigo) {
                                                    return 'Sin código asignado';
                                                }

                                                return $codigo;
                                            })
                                            ->extraAttributes([
                                                'class' =>
                                                    'mt-2 w-full text-right text-xs font-semibold text-gray-600 ' .
                                                    'bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 ' .
                                                    'dark:text-gray-300 dark:bg-gray-800 dark:border-gray-700',
                                            ]),
                                    ])
                                    ->extraAttributes([
                                        'class' =>
                                            'mt-4 rounded-xl border border-gray-200 bg-white px-4 py-3 ' .
                                            'dark:bg-gray-900 dark:border-gray-700',
                                    ]),
                            ])
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                self::actualizarPlataformaPrincipal($get, $set);
                            })
                            ->itemLabel(function (array $state): ?string {
                                if (! empty($state['plataforma_nombre'])) {
                                    return $state['plataforma_nombre'];
                                }

                                if (! isset($state['medio_id'])) {
                                    return 'Medio de pago';
                                }

                                $medio = YMedioPago::where('Id_Medio_Pago', $state['medio_id'])->first();

                                return $medio?->Nombre_Medio_Pago ?? 'Medio de pago';
                            })
                            ->default([])
                            ->live(),

                        Hidden::make('validar_montos_pago')
                            ->dehydrated(false)
                            ->rules([
                                fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                                    // Se maneja en afterValidation
                                },
                            ]),
                    ])
                    ->extraAttributes([
                        'class' =>
                            'w-full max-w-4xl mx-auto bg-transparent border-none shadow-none relative',
                    ]),
            ])
            ->afterValidation(function (Get $get, Set $set) {

                if ($get('validar_montos_pago')) {
                    $set('validar_montos_pago', false);
                    return;
                }

                // ✅ Total real de la factura
                $montoOriginal    = self::getMontoFactura($get);
                $totalDistribuido = self::calcularTotalDistribuido($get);
                $diferencia       = $montoOriginal - $totalDistribuido;

                $metodos = $get('metodos_pago') ?? [];
                $tieneMetodosAdicionales = is_array($metodos) && count($metodos) > 0;
                $tipoVenta = (int) ($get('Tipo_venta') ?? 0);

                $esTipoPlataformaPlataforma = false;
                if ($tipoVenta === 3) {
                    $idPlataforma = (int) ($get('medio_pago') ?? 0);
                    if ($idPlataforma) {
                        $tipoPlat = ZPlataformaCredito::where('idplataforma', $idPlataforma)
                            ->value('TipoPlataforma');

                        $tipoPlatUpper = mb_strtoupper(trim((string) $tipoPlat));
                        $esTipoPlataformaPlataforma = ($tipoPlatUpper === 'PLATAFORMA');
                    }
                }

                if ($diferencia > 0) {
                    Notification::make()
                        ->title('No puedes seguir')
                        ->body(
                            'No puedes seguir por falta de pago. ' .
                            'Faltan $' . number_format($diferencia, 0, ',', '.') . ' para completar el monto total.'
                        )
                        ->danger()
                        ->persistent()
                        ->actions([
                            NotificationAction::make('volver')
                                ->label('Volver')
                                ->color('gray')
                                ->close(),
                        ])
                        ->send();

                    throw new Halt();
                }

                if ($diferencia < 0) {
                    $exceso = abs($diferencia);

                    Notification::make()
                        ->title('No puedes seguir')
                        ->body(
                            'No puedes seguir porque los valores ingresados superan al monto total. ' .
                            'Te estás excediendo en $' . number_format($exceso, 0, ',', '.') . '.'
                        )
                        ->danger()
                        ->persistent()
                        ->actions([
                            NotificationAction::make('volver')
                                ->label('Volver')
                                ->color('gray')
                                ->close(),
                        ])
                        ->send();

                    throw new Halt();
                }

                if (
                    $tipoVenta === 3 &&
                    $esTipoPlataformaPlataforma &&
                    $diferencia === 0.0 &&
                    ! $tieneMetodosAdicionales
                ) {
                    $set('validar_montos_pago', true);

                    Notification::make()
                        ->title('¿Estás seguro que quieres continuar?')
                        ->body(
                            'Solo estás usando la plataforma principal como método de pago. ' .
                            '¿Quieres agregar otro medio  de pago?'
                        )
                        ->warning()
                        ->persistent()
                        ->send();

                    throw new Halt();
                }
            });
    }

    /**
     * ✅ Obtiene el total real de la factura (calculado en SeleccionProductosStep)
     */
    protected static function getMontoFactura(Get $get): float
    {
        $total = $get('total_factura');

        if ($total === null) {
            $total = $get('valor_total');
        }

        return (float) ($total ?? 0);
    }

    /**
     * Convierte un valor con formato "1.234.567,00" a número flotante.
     */
    protected static function parseMonto($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $str = (string) $value;

        $str = preg_replace('/,00$/', '', $str);
        $str = str_replace('.', '', $str);

        if ($str === '') {
            return 0.0;
        }

        return (float) $str;
    }

    /**
     * Calcula el total distribuido:
     * plataforma_principal + todos los otros métodos.
     */
    protected static function calcularTotalDistribuido(Get $get): float
    {
        $principal  = self::parseMonto($get('plataforma_principal') ?? 0);
        $totalOtros = self::calcularTotalOtros($get);

        return $principal + $totalOtros;
    }

    /**
     * Suma todos los métodos de pago adicionales (repeater metodos_pago).
     */
    protected static function calcularTotalOtros(Get $get): float
    {
        $metodos = $get('metodos_pago') ?? [];
        $total   = 0.0;

        foreach ($metodos as $metodo) {
            $total += self::parseMonto($metodo['monto'] ?? 0);
        }

        return $total;
    }

    /**
     * Actualiza el valor de `plataforma_principal` con el saldo pendiente.
     */
    protected static function actualizarPlataformaPrincipal(Get $get, Set $set): void
    {
        $montoOriginal = self::getMontoFactura($get);
        $totalOtros    = self::calcularTotalOtros($get);

        $saldoPendiente = $montoOriginal - $totalOtros;

        if ($saldoPendiente < 0) {
            $saldoPendiente = 0;
        }

        $set('plataforma_principal', $saldoPendiente);
    }

    /**
     * Devuelve el "código de referencia" de un medio.
     */
    protected static function getCodigoReferencia(?YMedioPago $medio, ?string $codigoCaja): ?string
    {
        if (! $medio) {
            return null;
        }

        $codigo = $medio->Codigo_forma_Pago;

        if (($codigo === null || $codigo === '') && $codigoCaja !== null && $codigoCaja !== '') {
            $codigo = (string) $codigoCaja;
        }

        return ($codigo !== null && $codigo !== '') ? (string) $codigo : null;
    }
}