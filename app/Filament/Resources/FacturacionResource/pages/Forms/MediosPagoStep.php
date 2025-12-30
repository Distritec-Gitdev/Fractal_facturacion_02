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

//MODELOS 
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
                        // Botón "Agregar método" alineado a la derecha
                        Actions::make([
                            Action::make('agregarMetodoPago')
                                ->label('Agregar Método')
                                ->icon('heroicon-o-plus')
                                ->color('primary')
                                ->modalHeading('Agregar Método de Pago')
                                ->modalDescription('Selecciona un método para agregarlo a la lista de pagos')
                                ->modalWidth('md')
                                ->modalSubmitActionLabel('Agregar') // <- texto del botón del modal

                                // Copiamos el estado del formulario principal al modal
                                ->mountUsing(function (Forms\ComponentContainer $form, Get $get) {
                                    $form->fill([
                                        'principal_id'      => $get('medio_pago'),
                                        'metodos_usados'    => $get('metodos_pago'),
                                        'codigo_caja_padre' => $get('codigo_caja'),
                                        'tipo_venta_id'     => $get('Tipo_venta'),
                                    ]);
                                })

                                ->form([
                                    // Campos ocultos SOLO del modal, con copia del formulario principal
                                    Hidden::make('principal_id'),
                                    Hidden::make('metodos_usados'),
                                    Hidden::make('codigo_caja_padre'),
                                    Hidden::make('tipo_venta_id'),

                                    // Buscador de métodos de pago en el modal
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
                                            // Término de búsqueda para filtrar opciones
                                            $busqueda = mb_strtoupper(trim((string) ($get('buscar_medio_pago') ?? '')));

                                            // ------------------- DATOS BASE (copiados al modal) -------------------
                                            $codigoCaja       = $get('codigo_caja_padre');           // sedes.Codigo_caja
                                            $medioPrincipalId = (int) ($get('principal_id') ?? 0);
                                            $tipoVentaId      = (int) ($get('tipo_venta_id') ?? 0);  // Tipo_venta del otro formulario

                                            $idsProhibidos           = [];
                                            $codigosProhibidos       = []; // SOLO Codigo_forma_Pago reales
                                            $nombresProhibidosUpper  = [];
                                            $plataformaIdsProhibidos = []; // <-- plataformas ya usadas

                                            // *** ID del tipo de venta "PLATAFORMAS Y CONVENIOS"
                                            $TIPO_PLATAFORMAS = 3;      

                                            // ⬇ BLOQUE AGREGADO: SEDE + CONTROL CAJA RIFA POR ENUM ver_CajaRifa
                                            $mostrarCajaRifa = false;
                                            try {
                                                $idSede = null;

                                                // Intentar tomar la sede desde el otro step (detallesCliente) o idsede directo
                                                $idSedeForm = $get('detallesCliente.0.idsede') ?? $get('idsede');
                                                if ($idSedeForm) {
                                                    $idSede = (int) $idSedeForm;
                                                }

                                                // Si no hay sede en formulario, replicar lógica de Hidden::idsede para asesores
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
                                                        $raw = $sede->ver_CajaRifa; // ENUM: FALSE / TRUE / NULL
                                                        $valorNormalizado = strtoupper(trim((string) $raw));
                                                        // Solo cuando sea exactamente 'TRUE' debe aparecer CAJA RIFA
                                                        $mostrarCajaRifa = ($valorNormalizado === 'TRUE');
                                                    }
                                                }
                                            } catch (\Throwable $e) {
                                                // Silencioso, no rompemos nada si algo falla
                                            }
                                            // ⬆ FIN BLOQUE CAJA RIFA

                                             // 1) Plataforma principal
                                            if ($medioPrincipalId > 0) {
                                                if ($tipoVentaId === $TIPO_PLATAFORMAS) {
                                                    // *** AQUÍ: cuando el tipo de venta es PLATAFORMAS Y CONVENIOS,
                                                    //     principal_id viene de ZPlataformaCredito, NO de YMedioPago
                                                    $plataformaPrincipal = ZPlataformaCredito::find($medioPrincipalId);

                                                    if ($plataformaPrincipal) {
                                                        // No permitir volver a listar esta plataforma en los convenios
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
                                                    // *** Resto de tipos de venta: principal es medio normal (YMedioPago)
                                                    $medioPrincipal = YMedioPago::where('Id_Medio_Pago', $medioPrincipalId)->first();

                                                    if ($medioPrincipal) {
                                                        // ID prohibido
                                                        $idsProhibidos[] = (int) $medioPrincipal->Id_Medio_Pago;

                                                        // Nombre prohibido (en mayúsculas)
                                                        $nombrePrincipalUpper = mb_strtoupper(
                                                            trim((string) $medioPrincipal->Nombre_Medio_Pago)
                                                        );
                                                        if ($nombrePrincipalUpper !== '') {
                                                            $nombresProhibidosUpper[] = $nombrePrincipalUpper;
                                                        }

                                                        // Código de referencia PROHIBIDO (solo Codigo_forma_Pago real)
                                                        $codigoPrincipal = $medioPrincipal->Codigo_forma_Pago;
                                                        if ($codigoPrincipal !== null && $codigoPrincipal !== '') {
                                                            $codigosProhibidos[] = (string) $codigoPrincipal;
                                                        }
                                                    }
                                                }
                                            }

                                            // 2) Métodos ya agregados en el repeater (copia en el modal)
                                            $metodos = $get('metodos_usados') ?? [];
                                            foreach ($metodos as $metodo) {
                                                // Medios de pago normales
                                                if (!empty($metodo['medio_id'])) {
                                                    $idUsado = (int) $metodo['medio_id'];
                                                    $idsProhibidos[] = $idUsado;

                                                    $medioUsado = YMedioPago::where('Id_Medio_Pago', $idUsado)->first();

                                                    if ($medioUsado) {
                                                        // Nombre prohibido
                                                        $nombreUsadoUpper = mb_strtoupper(
                                                            trim((string) $medioUsado->Nombre_Medio_Pago)
                                                        );
                                                        if ($nombreUsadoUpper !== '') {
                                                            $nombresProhibidosUpper[] = $nombreUsadoUpper;
                                                        }

                                                        // Código de referencia PROHIBIDO (solo Codigo_forma_Pago real)
                                                        $codigoUsado = $medioUsado->Codigo_forma_Pago;
                                                        if ($codigoUsado !== null && $codigoUsado !== '') {
                                                            $codigosProhibidos[] = $codigoUsado;
                                                        }
                                                    }
                                                }

                                                // Plataformas ya agregadas
                                                if (!empty($metodo['plataforma_id'])) {
                                                    $plataformaIdsProhibidos[] = (int) $metodo['plataforma_id'];
                                                }
                                            }

                                            // Normalizar arrays
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

                                            // ------------------- ARMAR OPCIONES -------------------
                                            $opciones = [];

                                            // Si el tipo de venta es 1,4 o 5 SOLO se permiten Id_Medio_Pago 1,2,3,10
                                            $soloBasicos = in_array($tipoVentaId, [1, 4, 5], true);
                                            $mediosPermitidosBasicos = [1, 2, 3, 10];

                                            // Medios de pago (YMedioPago)
                                            $medios = YMedioPago::orderBy('Nombre_Medio_Pago')->get();

                                            foreach ($medios as $medio) {
                                                /** @var \App\Models\YMedioPago $medio */
                                                $idMedio     = (int) $medio->Id_Medio_Pago;
                                                $nombre      = (string) $medio->Nombre_Medio_Pago;
                                                $nombreTrim  = trim($nombre);
                                                $nombreUpper = mb_strtoupper($nombreTrim);
                                                $codigoForma = $medio->Codigo_forma_Pago;

                                                //  Regla especial: Tipo_venta = 3 (PLATAFORMAS Y CONVENIOS)
                                                // NO mostramos los Id_Medio_Pago 5 y 8
                                                if ($tipoVentaId === 3 && in_array($idMedio, [5, 8], true)) {
                                                    continue;
                                                }

                                                //  Regla nueva: Tipo_venta 1, 4 o 5 -> solo 1,2,3,10
                                                if ($soloBasicos && !in_array($idMedio, $mediosPermitidosBasicos, true)) {
                                                    continue;
                                                }

                                                //  Control específico: CAJA RIFA (Id_Medio_Pago = 6)
                                                // Solo se muestra cuando ver_CajaRifa = 'TRUE' para la sede
                                                if ($idMedio === 6 && ! $mostrarCajaRifa) {
                                                    continue;
                                                }

                                                //  Ocultar medios "vacíos" o N/A sin código
                                                if (
                                                    ($nombreTrim === '' || $nombreUpper === 'N/A') &&
                                                    ($codigoForma === null || $codigoForma === '')
                                                ) {
                                                    continue;
                                                }

                                                // A) No mostrar medios cuyo ID ya está usado
                                                if (in_array($idMedio, $idsProhibidos, true)) {
                                                    continue;
                                                }

                                                // B) No mostrar medios cuyo NOMBRE ya está usado
                                                if ($nombreUpper !== '' && in_array($nombreUpper, $nombresProhibidosUpper, true)) {
                                                    continue;
                                                }

                                                // C) No mostrar medios cuyo Codigo_forma_Pago REAL ya está usado
                                                if (
                                                    $codigoForma !== null &&
                                                    $codigoForma !== '' &&
                                                    in_array((string) $codigoForma, $codigosProhibidos, true)
                                                ) {
                                                    continue;
                                                }

                                                // Si pasa todos los filtros, se agrega
                                                $opciones[$idMedio] = $nombreTrim;
                                            }

                                            //  Plataformas de crédito (ZPlataformaCredito)
                                            // Solo se muestran si NO es tipo de venta 1,4 o 5
                                            if (!$soloBasicos) {
                                                $plataformas = ZPlataformaCredito::orderBy('plataforma')->get();

                                                foreach ($plataformas as $plataforma) {
                                                    /** @var \App\Models\ZPlataformaCredito $plataforma */
                                                    $idPlat       = (int) $plataforma->idplataforma;
                                                    $nombre       = (string) $plataforma->plataforma;
                                                    $nombreTrim   = trim($nombre);
                                                    $nombreUpper  = mb_strtoupper($nombreTrim);
                                                    $codigoPlat   = $plataforma->mediosPagoCodigos ?? null;
                                                    $tipoPlat     = mb_strtoupper(trim((string) ($plataforma->TipoPlataforma ?? '')));

                                                    //  Solo mostrar las plataformas con TipoPlataforma = 'CONVENIO'
                                                    if ($tipoPlat !== 'CONVENIO') {
                                                        continue;
                                                    }

                                                    //  Ocultar plataformas "vacías" o N/A sin código
                                                    if (
                                                        ($nombreTrim === '' || $nombreUpper === 'N/A') &&
                                                        ($codigoPlat === null || $codigoPlat === '')
                                                    ) {
                                                        continue;
                                                    }

                                                    // Si ya fue agregada, no la mostramos
                                                    if (in_array($idPlat, $plataformaIdsProhibidos, true)) {
                                                        continue;
                                                    }

                                                    // Usamos una clave distinta para no chocar con Id_Medio_Pago
                                                    $key = 'PLAT-' . $idPlat;

                                                    $opciones[$key] = $nombreTrim;
                                                }
                                            }

                                            // Filtro por texto de búsqueda
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
                                    // Agrega una nueva fila al arreglo dinámico (form principal)
                                    $metodos = $get('metodos_pago') ?? [];

                                    // Número visual (2 en adelante, porque 1 es plataforma principal)
                                    $numero = count($metodos) + 2;

                                    $codigoCaja = $get('codigo_caja');
                                    $tipo       = $data['tipo'];

                                    // Detectar si es plataforma de crédito o medio normal
                                    if (is_string($tipo) && str_starts_with($tipo, 'PLAT-')) {
                                        // ---- Plataforma de z_plataforma_credito ----
                                        $idPlataforma = (int) substr($tipo, 5);
                                        $plataforma   = ZPlataformaCredito::find($idPlataforma);

                                        $codigoRef = $plataforma?->mediosPagoCodigos ?? null;
                                        $nombre    = $plataforma?->plataforma ?? 'Plataforma';

                                        $metodos[] = [
                                            'medio_id'           => null,           // no es un YMedioPago
                                            'plataforma_id'      => $idPlataforma,
                                            'plataforma_nombre'  => $nombre,
                                            'monto'              => 0,
                                            'numero'             => $numero,
                                            'codigo_referencia'  => $codigoRef,
                                        ];
                                    } else {
                                        // ---- Medio de pago normal (YMedioPago) – lo de siempre ----
                                        $medio     = YMedioPago::where('Id_Medio_Pago', (int) $tipo)->first();
                                        $codigoRef = self::getCodigoReferencia($medio, $codigoCaja);

                                        $medioId = (int) $tipo;

                                        // Banco por defecto según tipo de medio
                                        $bancoId = null;
                                        if ($medioId === 3) {
                                            // WOMPI -> banco 1
                                            $bancoId = 1;
                                        } elseif ($medioId === 10) {
                                            // LLAVE BRE-B -> banco 5
                                            $bancoId = 5;
                                        }

                                        $metodos[] = [
                                            'medio_id'           => (int) $tipo,   // Id_Medio_Pago seleccionado
                                            'plataforma_id'      => null,
                                            'plataforma_nombre'  => null,
                                            'monto'              => 0,
                                            'numero'             => $numero,
                                            'codigo_referencia'  => $codigoRef,
                                            'banco_transferencia_id'=> $bancoId,

                                        ];
                                    }

                                    $set('metodos_pago', $metodos);

                                    // Recalcula plataforma principal
                                    self::actualizarPlataformaPrincipal($get, $set);
                                }),
                        ])
                            ->alignment('right')
                            ->extraAttributes([
                                'class' => 'flex justify-end mb-2',
                            ]),

                        // HEADER: SALDO PENDIENTE (diferencia) grande y dinámico
                        Placeholder::make('monto_factura_total_header')
                            ->label('')
                            ->content(function (Get $get) {
                                $montoOriginal    = 2000000;
                                $totalDistribuido = self::calcularTotalDistribuido($get);
                                $diferencia       = $montoOriginal - $totalDistribuido; // 0=ok, >0 falta, <0 exceso

                                $montoAbs   = '$' . number_format(abs($diferencia), 0, ',', '.');
                                $montoTexto = $diferencia < 0 ? '-' . $montoAbs : $montoAbs;

                                return 'SALDO PENDIENTE: ' . $montoTexto;
                            })
                            ->extraAttributes(function (Get $get) {
                                $montoOriginal    = 2000000;
                                $totalDistribuido = self::calcularTotalDistribuido($get);
                                $diferencia       = $montoOriginal - $totalDistribuido;

                                $baseClass = 'block text-center font-extrabold tracking-wide mb-4';
                                $baseStyle = 'font-size:22px;';

                                // Pago cuadrado -> verde
                                if ($diferencia === 0.0) {
                                    return [
                                        'class' => $baseClass,
                                        'style' => $baseStyle . 'color:#82cc0e;',
                                    ];
                                }

                                // Falta por pagar -> amarillo
                                if ($diferencia > 0) {
                                    return [
                                        'class' => $baseClass,
                                        'style' => $baseStyle . 'color:#f1c40f;',
                                    ];
                                }

                                // Exceso -> rojo
                                return [
                                    'class' => $baseClass,
                                    'style' => $baseStyle . 'color:#ff0000;',
                                ];
                            }),

                        // Título "Pago de Factura"
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
                                        $montoOriginal    = 2000000;
                                        $totalDistribuido = self::calcularTotalDistribuido($get);
                                        $diferencia       = $montoOriginal - $totalDistribuido;

                                        // Pago cuadrado
                                        if ($diferencia === 0.0) {
                                            return 'PUEDE FACTURAR';
                                        }

                                        // Falta por pagar
                                        if ($diferencia > 0) {
                                            $faltante = $diferencia;

                                            $linea1 = 'NO PUEDE FACTURAR. FALTA POR PAGAR: $' .
                                                number_format($faltante, 0, ',', '.');
                                            $linea2 = 'LOS VALORES INGRESADOS SON MENORES AL MONTO TOTAL.';

                                            return $linea1 . "\n" . $linea2;
                                        }

                                        // Exceso
                                        $exceso = abs($diferencia);

                                        $linea1 = 'NO PUEDE FACTURAR. EXCESO DE $' .
                                            number_format($exceso, 0, ',', '.');
                                        $linea2 = 'LOS VALORES INGRESADOS SUPERAN AL MONTO TOTAL.';

                                        return $linea1 . "\n" . $linea2;
                                    })
                                    ->extraAttributes(function (Get $get) {
                                        $montoOriginal    = 2000000;
                                        $totalDistribuido = self::calcularTotalDistribuido($get);
                                        $diferencia       = $montoOriginal - $totalDistribuido;

                                        $base =
                                            'w-full max-w-xl mx-auto px-8 py-4 rounded-lg text-base font-semibold ' .
                                            'mt-2 md:mt-0 border shadow-sm text-center';

                                        // Falta por pagar -> amarillo
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

                                        // Pago cuadrado -> verde
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

                                        // Exceso -> rojo
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

                        // FILA 1: Plataforma Principal (siempre visible)
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
                                                        $tipoVenta  = (int) ($get('Tipo_venta') ?? 0); // mismo campo que usas en el otro archivo

                                                        if (! $rawMedio) {
                                                            return 'Plataforma Principal';
                                                        }

                                                        // ⚠ Ajusta este valor si el ID real de "PLATAFORMAS Y CONVENIOS" no es 3.
                                                        $TIPO_PLATAFORMAS = 3;

                                                        // Cuando el tipo de venta es "plataformas y convenios",
                                                        // el valor de medio_pago viene de z_plataforma_credito.idplataforma
                                                        if ($tipoVenta === $TIPO_PLATAFORMAS) {
                                                            $idPlataforma = (int) $rawMedio;

                                                            if (! $idPlataforma) {
                                                                return 'Plataforma Principal';
                                                            }

                                                            $nombrePlataforma = ZPlataformaCredito::where('idplataforma', $idPlataforma)
                                                                ->value('plataforma');

                                                            return $nombrePlataforma ?: 'Plataforma Principal';
                                                        }

                                                        // Para el resto de tipos de venta, medio_pago = Id_Medio_Pago (YMedioPago)
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
                                                // Mostrar SIEMPRE el saldoPendiente = montoOriginal - totalOtros
                                                $montoOriginal = 2000000;
                                                $totalOtros    = self::calcularTotalOtros($get);
                                                $saldo         = $montoOriginal - $totalOtros;
                                                if ($saldo < 0) {
                                                    $saldo = 0;
                                                }

                                                return number_format($saldo, 0, ',', '.');
                                            })
                                            ->dehydrateStateUsing(function ($state, Get $get) {
                                                // Al guardar, también mandar el saldo real
                                                $montoOriginal = 2000000;
                                                $totalOtros    = self::calcularTotalOtros($get);
                                                $saldo         = $montoOriginal - $totalOtros;
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
                                                $montoOriginal = 2000000;
                                                $totalOtros    = self::calcularTotalOtros($get);
                                                $saldo         = $montoOriginal - $totalOtros;
                                                if ($saldo < 0) {
                                                    $saldo = 0;
                                                }
                                                return '$' . number_format($saldo, 0, ',', '.');
                                            })
                                            ->extraAttributes(function (Get $get) {
                                                $montoOriginal    = 2000000;
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

                                     // Select de banco para TRANSFERENCIA como medio de pago principal
                        Select::make('banco_transferencia_principal_id')
                            ->label('Banco')
                            ->options(function (Get $get) {
                                $medio = (int) ($get('medio_pago') ?? 0);

                                $query = YCuentaBanco::query()
                                    ->orderBy('Cuenta_Banco');

                                if ($medio === 3) {
                                    // WOMPI → solo banco 1
                                    $query->where('ID_Banco', 1);
                                } elseif ($medio === 10) {
                                    // LLAVE BRE-B → solo banco 5
                                    $query->where('ID_Banco', 5);
                                }
                                // TRANSFERENCIA (2) → todos los bancos

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

                        // Código medio de pago solo lectura (plataforma principal)
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
                                Hidden::make('codigo_referencia')
                                    ->dehydrated(false),
                                Hidden::make('plataforma_id')
                                    ->dehydrated(false),
                                Hidden::make('plataforma_nombre')
                                    ->dehydrated(false),

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
                                                                // Primero intentamos con nombre de plataforma (si existe)
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
                                                    // VALIDACIÓN: cada método de pago debe tener monto > 0
                                                    ->rules([
                                                        fn () => function (string $attribute, $value, Closure $fail) {
                                                            if (MediosPagoStep::parseMonto($value) <= 0) {
                                                                $fail('El monto del método de pago es obligatorio y debe ser mayor a 0.');
                                                            }
                                                        },
                                                    ])
                                                    ->dehydrateStateUsing(function ($state) {
                                                        // si está vacío, no lo fuerces a 0, mándalo como null
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
                                                             // si está 'lockeado', no dejamos escribir para evitar pisadas
                                                             if (input.dataset.locked === '1') {
                                                                 return;
                                                             }
                                                             let valorLimpio = input.value.replace(/[^0-9]/g, '');
                                                             if (valorLimpio === '') {
                                                                 // cuando borran todo, fijamos 0 y bloqueamos hasta que Livewire termine de recalcular
                                                                 input.dataset.locked = '1';
                                                                 input.value = '0';

                                                                 // cancelar timeout previo si existe
                                                                 if (input.dataset.unlockTimeoutId) {
                                                                     try {
                                                                         clearTimeout(parseInt(input.dataset.unlockTimeoutId));
                                                                     } catch (e) {}
                                                                 }

                                                                 let timeoutId = setTimeout(function () {
                                                                     input.dataset.locked = '0';
                                                                     try {
                                                                         input.setSelectionRange(input.value.length, input.value.length);
                                                                     } catch (e) {}
                                                                 }, 1000); // un poco más que el debounce de Livewire

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

                                        // Select de banco SOLO cuando medio_id = 2 (TRANSFERENCIA)
                                       Select::make('banco_transferencia_id')
                                            ->label('Banco')
                                            ->options(function (Get $get) {
                                                $medio = (int) ($get('medio_id') ?? 0);

                                                $query = YCuentaBanco::query()
                                                    ->orderBy('Cuenta_Banco');

                                                if ($medio === 3) {
                                                    // WOMPI → solo banco 1
                                                    $query->where('ID_Banco', 1);
                                                } elseif ($medio === 10) {
                                                    // LLAVE BRE-B → solo banco 5
                                                    $query->where('ID_Banco', 5);
                                                }
                                                // TRANSFERENCIA (2) → todos los bancos

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

                                                // Respaldo: si por alguna razón viene vacío y es WOMPI o LLAVE BRE-B, rellenamos
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
                                                // WOMPI y LLAVE BRE-B no se pueden modificar
                                                return in_array($medio, [3, 10], true);
                                            })
                                            ->reactive()
                                            ->extraAttributes([
                                                'class' => 'mt-2 w-full',
                                            ]),




                                        // Código medio de pago por método
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

                        // Validación para bloquear "Siguiente" si hay exceso
                        Hidden::make('validar_montos_pago')
                            ->dehydrated(false)
                            ->rules([
                                fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                                    // La validación de exceso se maneja ahora en afterValidation.
                                    // Esta regla se deja sin llamar a $fail para no duplicar mensajes.
                                },
                            ]),
                    ])
                    ->extraAttributes([
                        'class' =>
                            'w-full max-w-4xl mx-auto bg-transparent border-none shadow-none relative',
                    ]),
            ])
            ->afterValidation(function (Get $get, Set $set) {

                // ✅ Si ya confirmó desde la notificación, dejamos pasar una vez y limpiamos la bandera
                if ($get('validar_montos_pago')) {
                    $set('validar_montos_pago', false);
                    return;
                }

                $montoOriginal    = 2000000;
                $totalDistribuido = self::calcularTotalDistribuido($get);
                $diferencia       = $montoOriginal - $totalDistribuido; // saldo pendiente (>=0 bien, >0 debe, <0 exceso)

                $metodos = $get('metodos_pago') ?? [];
                $tieneMetodosAdicionales = is_array($metodos) && count($metodos) > 0;
                $tipoVenta = (int) ($get('Tipo_venta') ?? 0);

                // Detectar si la plataforma principal (cuando Tipo_venta = 3) es de tipo "PLATAFORMA" o "CONVENIO"
                $esTipoPlataformaPlataforma = false;
                if ($tipoVenta === 3) {
                    $idPlataforma = (int) ($get('medio_pago') ?? 0);
                    if ($idPlataforma) {
                        $tipoPlat = ZPlataformaCredito::where('idplataforma', $idPlataforma)
                            ->value('TipoPlataforma');

                        $tipoPlatUpper = mb_strtoupper(trim((string) $tipoPlat));
                        // Solo cuando TipoPlataforma sea exactamente 'PLATAFORMA' queremos mostrar el modal
                        $esTipoPlataformaPlataforma = ($tipoPlatUpper === 'PLATAFORMA');
                    }
                }

                // 1) Si hay saldo pendiente (faltante), no deja seguir
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

                // 2) Si hay exceso, tampoco deja seguir
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

                // 3) Si NO hay saldo pendiente y SOLO existe la plataforma principal,
                //    pedimos confirmación SIEMPRE que le dé a Siguiente,
                //    SOLO cuando:
                //    - Tipo_venta = 3 (PLATAFORMAS Y CONVENIOS)
                //    - TipoPlataforma = 'PLATAFORMA'
                if (
                    $tipoVenta === 3 &&
                    $esTipoPlataformaPlataforma &&
                    $diferencia === 0.0 &&
                    ! $tieneMetodosAdicionales
                ) {
                    // Marcamos bandera para que el siguiente intento SI deje pasar
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
     * Convierte un valor con formato "1.234.567,00" a número flotante.
     */
    protected static function parseMonto($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $str = (string) $value;

        // Quitar decimal fijo ,00 si está
        $str = preg_replace('/,00$/', '', $str);

        // Quitar puntos de miles
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
        $principal  = (float) ($get('plataforma_principal') ?? 0);
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
     * saldoPendiente = montoOriginal - totalOtros
     * Si el resultado es negativo, se fuerza a 0.
     */
    protected static function actualizarPlataformaPrincipal(Get $get, Set $set): void
    {
        $montoOriginal = 2000000;
        $totalOtros    = self::calcularTotalOtros($get);

        $saldoPendiente = $montoOriginal - $totalOtros;

        if ($saldoPendiente < 0) {
            $saldoPendiente = 0;
        }

        $set('plataforma_principal', $saldoPendiente);
    }

    /**
     * Devuelve el "código de referencia" de un medio:
     * - Si tiene Codigo_forma_Pago lo usa.
     * - Si está null/vacío, usa Codigo_caja (de la sede).
     */
    protected static function getCodigoReferencia(?YMedioPago $medio, ?string $codigoCaja): ?string
    {
        if (! $medio) {
            return null;
        }

        // Primero intentamos con Codigo_forma_Pago
        $codigo = $medio->Codigo_forma_Pago;

        // Si no tiene, usamos Codigo_caja de la sede
        if (($codigo === null || $codigo === '') && $codigoCaja !== null && $codigoCaja !== '') {
            $codigo = (string) $codigoCaja;
        }

        return ($codigo !== null && $codigo !== '') ? (string) $codigo : null;
    }
}