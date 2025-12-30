<?php

namespace App\Filament\Resources\FacturacionResource\Forms;

use App\Http\Controllers\Api\EnvioFacturacionController;
use App\Models\AaPrin;
use App\Models\Asesor;
use App\Models\InfTrab;
use App\Models\Sede;
use App\Models\SocioDistritec;
use App\Models\YMedioPago;
use App\Models\YTipoVenta;
use App\Models\ZComision;
use App\Models\ZPlataformaCredito;
use Carbon\Carbon;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PagoFacturaStep
{
    public static function make(): Step
    {
        return Step::make('Pago factura')
            ->icon('heroicon-o-receipt-refund')
            ->schema([

                Section::make('Resumen del pago')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('total_factura')
                                ->label('Total factura')
                                ->prefix('$')
                                ->readOnly()
                                ->dehydrated(false)
                                ->default(fn (Get $get) => (float) ($get('total_factura') ?? $get('valor_total') ?? 0))
                                ->extraAttributes(['class' => 'font-semibold text-sm']),
                        ]),

                        Grid::make(2)->schema([
                            Placeholder::make('unidad_negocio_resumen')
                                ->label('Código unidad de negocio')
                                ->content(fn (Get $get) => $get('unidad_negocio') ?: '—')
                                ->extraAttributes(['class' => 'font-semibold text-xs']),

                            Placeholder::make('unidad_negocio_nombre_resumen')
                                ->label('Nombre unidad de negocio')
                                ->content(function (Get $get) {
                                    $unidadId = $get('Unidad_negocio') ?? $get('unidad_negocio');
                                    if (! $unidadId) return '—';

                                    return ZComision::query()
                                        ->whereKey($unidadId)
                                        ->value('comision') ?: '—';
                                })
                                ->extraAttributes(['class' => 'font-semibold text-xs']),
                        ]),

                        Grid::make(2)->schema([
                            Placeholder::make('patron_contable_codigo')
                                ->label('Código patrón contable')
                                ->content(function (Get $get) {
                                    $tipoVentaId = $get('Tipo_venta');
                                    if (! $tipoVentaId) return '-';

                                    return YTipoVenta::query()
                                        ->whereKey($tipoVentaId)
                                        ->value('Cod_Patron_Contable') ?: '-';
                                }),

                            Placeholder::make('patron_contable_nombre')
                                ->label('Nombre tipo de venta')
                                ->content(function (Get $get) {
                                    $tipoVentaId = $get('Tipo_venta');
                                    if (! $tipoVentaId) return '-';

                                    return YTipoVenta::query()
                                        ->whereKey($tipoVentaId)
                                        ->value('Nombre_Tipo_Venta') ?: '-';
                                }),
                        ]),

                        Grid::make(2)->schema([
                            Placeholder::make('codigo_bodega_resumen')
                                ->label('Código bodega')
                                ->content(fn (Get $get) => $get('codigo_bodega_factura') ?: '—')
                                ->extraAttributes(['class' => 'font-semibold text-xs']),

                            Placeholder::make('nombre_bodega_resumen')
                                ->label('Nombre bodega')
                                ->content(fn (Get $get) => $get('nombre_bodega_factura') ?: '—')
                                ->extraAttributes(['class' => 'font-semibold text-xs']),
                        ]),

                        Grid::make(2)->schema([
                            Placeholder::make('producto_codigo_resumen')
                                ->label('Código producto')
                                ->content(function (Get $get) {
                                    $items = $get('items') ?: [];
                                    $first = $items[0] ?? null;
                                    return $first['codigo_producto'] ?? '—';
                                })
                                ->extraAttributes(['class' => 'font-semibold text-xs']),

                            Placeholder::make('producto_nombre_resumen')
                                ->label('Nombre producto')
                                ->content(function (Get $get) {
                                    $items = $get('items') ?: [];
                                    $first = $items[0] ?? null;
                                    return $first['nombre_producto'] ?? '—';
                                })
                                ->extraAttributes(['class' => 'font-semibold text-xs']),
                        ]),
                    ])
                    ->extraAttributes([
                        'class' => 'bg-white dark:bg-gray-900 rounded-xl shadow-sm p-4 sm:p-5 border border-gray-200 dark:border-gray-700',
                    ]),

                Section::make('Datos finales de facturación')
                    ->schema([
                        DateTimePicker::make('fecha_hora_facturacion')
                            ->label('Fecha y hora de facturación')
                            ->seconds(false)
                            ->required(),
                    ])
                    ->extraAttributes([
                        'class' => 'bg-white dark:bg-gray-900 rounded-xl shadow-sm p-4 sm:p-5 border border-gray-200 dark:border-gray-700 mt-4',
                    ]),

                Section::make('Confirmación')
                    ->schema([
                        Textarea::make('observaciones_pago')
                            ->label('Observaciones de pago')
                            ->helperText('Notas internas sobre el pago o la forma de cancelación.')
                            ->maxLength(500)
                            ->dehydrated(true)
                            ->extraAttributes(['class' => 'text-sm'])
                            ->columnSpanFull(),
                    ])
                    ->extraAttributes([
                        'class' => 'bg-white dark:bg-gray-900 rounded-xl shadow-sm p-4 sm:p-5 border border-gray-200 dark:border-gray-700 mt-4',
                    ]),

                Actions::make([
                    Action::make('crear_factura')
                        ->label('Confirmar factura')
                        ->icon('heroicon-o-document-check')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->submit(false)
                        ->action(function (HasForms $livewire, Get $get, Set $set) {

                            $memo = [
                                'tipoVenta'   => [],
                                'medioPago'   => [],
                                'plataforma'  => [],
                                'sede'        => [],
                                'comision'    => [],
                            ];

                            $state = $livewire->form->getState();

                            $v = function (string $key, $default = null) use ($state, $get) {
                                return $state[$key] ?? $get($key) ?? $default;
                            };

                            $notify = function (string $type, string $title, ?string $body = null, bool $persistent = true) {
                                $n = Notification::make()->title($title);
                                if ($body) $n->body($body);
                                if ($persistent) $n->persistent();

                                match ($type) {
                                    'success' => $n->success(),
                                    'warning' => $n->warning(),
                                    'info'    => $n->info(),
                                    default   => $n->danger(),
                                };

                                $n->send();
                            };

                            $failFast = function (string $title, string $body) use ($notify) {
                                $notify('danger', $title, $body);
                            };

                            $normalizeApi = function ($resp): array {
                                $raw = is_array($resp) ? $resp : ['raw' => $resp];

                                $esExitosa1 = data_get($raw, 'infoOperacion.esExitosa');
                                $esExitosa2 = data_get($raw, 'infoOperacion.esExitoso');
                                $esExitosa3 = data_get($raw, 'esExitoso');

                                $isOk = null;
                                if (is_bool($esExitosa1)) $isOk = $esExitosa1;
                                if ($isOk === null && is_bool($esExitosa2)) $isOk = $esExitosa2;
                                if ($isOk === null && is_bool($esExitosa3)) $isOk = $esExitosa3;

                                if ($isOk === null) {
                                    $success = data_get($raw, 'success');
                                    $ok      = data_get($raw, 'ok');
                                    $estado  = data_get($raw, 'estado');

                                    if (is_bool($success)) $isOk = $success;
                                    if ($isOk === null && is_bool($ok)) $isOk = $ok;
                                    if ($isOk === null && is_string($estado)) {
                                        $isOk = !in_array(strtolower($estado), ['error', 'failed', 'fail', 'ko'], true);
                                    }
                                }

                                if ($isOk === null) $isOk = true;

                                $msg =
                                    data_get($raw, 'infoOperacion.excepcion.detalleErrorFiltrado')
                                    ?? data_get($raw, 'infoOperacion.excepcion.detalleError')
                                    ?? data_get($raw, 'infoOperacion.mensaje')
                                    ?? data_get($raw, 'message')
                                    ?? data_get($raw, 'mensaje')
                                    ?? data_get($raw, 'error')
                                    ?? null;

                                return ['ok' => (bool) $isOk, 'message' => $msg, 'raw' => $raw];
                            };

                            $money = function ($value): int {
                                if ($value === null) return 0;
                                if (is_int($value)) return $value;
                                if (is_float($value)) return (int) round($value);
                                if (is_numeric($value)) return (int) $value;

                                $s = (string) $value;
                                $s = preg_replace('/[^0-9]/', '', $s);
                                return $s === '' ? 0 : (int) $s;
                            };

                            // ======================
                            // TOTAL (sin puntos)
                            // ======================
                            $totalFinal = $money($v('total_factura') ?? $v('valor_total') ?? 0);

                            $itemsForm = $state['items'] ?? $get('items') ?? [];

                            if ($totalFinal <= 0 && !empty($itemsForm)) {
                                $totalTmp = 0;
                                foreach ($itemsForm as $it) {
                                    $cantidad = (int) ($it['cantidad'] ?? 0);
                                    $precio   = $money($it['precio_unitario_raw'] ?? $it['precio_unitario'] ?? 0);
                                    if ($cantidad > 0 && $precio > 0) $totalTmp += ($cantidad * $precio);
                                }
                                $totalFinal = $totalTmp;
                            }

                            if ($totalFinal <= 0) {
                                $failFast('Total inválido', 'El total de la factura está en 0.');
                                return;
                            }

                            $set('total_factura', $totalFinal);

                            // ======================
                            // CABECERAS (SEDE / VENDEDOR)
                            // ======================
                            $user = auth()->user();

                            $prefijoSede        = null;
                            $codigoSucursalSede = null;
                            $codigoCajaSede     = null;
                            $centroCostosSede   = null;
                            $codigoVendedor     = '';

                            $sedeObj = null;

                            if ($user) {
                                if ($user->hasRole('socio')) {

                                    $cedula = $user->cedula ?? null;
                                    $socio  = $cedula ? SocioDistritec::query()->where('Cedula', $cedula)->first() : null;

                                    $idSede = $get('detallesCliente.0.sede_pago')
                                        ?? $get('detallesCliente.0.idsede')
                                        ?? data_get($state, 'detallesCliente.0.sede_pago')
                                        ?? data_get($state, 'detallesCliente.0.idsede');

                                    if ($idSede) {
                                        $sedeObj = $memo['sede'][$idSede] ??= Sede::find($idSede);
                                    }

                                    if ($sedeObj) {
                                        $prefijoSede        = $sedeObj->Prefijo ?? null;
                                        $codigoSucursalSede = $sedeObj->Codigo_de_sucursal ?? null;
                                        $codigoCajaSede     = $sedeObj->Codigo_caja ?? null;
                                        $centroCostosSede   = $sedeObj->Centro_costos ?? null;
                                    }

                                    $codigoVendedor = (string) (
                                        $get('detallesCliente.0.nombre_asesor_select')
                                        ?? $get('detallesCliente.0.codigo_asesor')
                                        ?? data_get($state, 'detallesCliente.0.nombre_asesor_select')
                                        ?? data_get($state, 'detallesCliente.0.codigo_asesor')
                                        ?? ($socio?->soc_cod_vendedor ?? '')
                                    );

                                } else {

                                    $cedula = $user->cedula ?? null;
                                    $asesor = $cedula ? Asesor::query()->where('Cedula', $cedula)->first() : null;

                                    $aaPrin = $asesor
                                        ? AaPrin::query()
                                            ->where('ID_Asesor', $asesor->ID_Asesor)
                                            ->where('ID_Estado', '!=', 3)
                                            ->orderByDesc('ID_Inf_trab')
                                            ->with('sede')
                                            ->first()
                                        : null;

                                    $infTrab = $aaPrin ? InfTrab::find($aaPrin->ID_Inf_trab) : null;

                                    $codigoVendedor = (string) ($infTrab?->Codigo_vendedor ?? '');

                                    $sedeObj = $aaPrin?->sede;
                                    if ($sedeObj) {
                                        $prefijoSede        = $sedeObj->Prefijo ?? null;
                                        $codigoSucursalSede = $sedeObj->Codigo_de_sucursal ?? null;
                                        $codigoCajaSede     = $sedeObj->Codigo_caja ?? null;
                                        $centroCostosSede   = $sedeObj->Centro_costos ?? null;
                                    }
                                }
                            }

                            // ======================
                            // DATOS BASE
                            // ======================
                            $nit         = (int) ($state['cedula'] ?? $get('cedula'));
                            $tipoVentaId = (int) ($state['Tipo_venta'] ?? $get('Tipo_venta') ?? 0);
                            $medioPagoId = (int) ($state['medio_pago'] ?? $get('medio_pago') ?? 0);

                            $codigoUnidadNegocio = $state['Codigo_Unidad']
                                ?? $state['unidad_negocio']
                                ?? $get('unidad_negocio');

                            if ($nit <= 0) {
                                $failFast('Cliente inválido', 'No se encontró la cédula/NIT del cliente.');
                                return;
                            }

                            // ======================
                            // FORMA PAGO CABECERA
                            // ======================
                            $codigoFormaPagoCabecera = null;

                            if ($medioPagoId > 0) {
                                if ($tipoVentaId === 3) {
                                    $plataforma = $memo['plataforma'][$medioPagoId] ??= ZPlataformaCredito::find($medioPagoId);
                                    $codigoFormaPagoCabecera = $plataforma?->mediosPagoCodigos ?: null;
                                } else {
                                    $medio = $memo['medioPago'][$medioPagoId] ??= YMedioPago::find($medioPagoId);
                                    if ($medio) {
                                        $codigoFormaPagoCabecera = $medio->Codigo_forma_Pago ?: null;
                                        if (! $codigoFormaPagoCabecera && !empty($codigoCajaSede)) {
                                            $codigoFormaPagoCabecera = (string) $codigoCajaSede;
                                        }
                                    }
                                }

                                if (! $codigoFormaPagoCabecera) {
                                    $codigoFormaPagoCabecera = (string) $medioPagoId;
                                }
                            }

                            if (! $codigoFormaPagoCabecera) {
                                $failFast('Forma de pago inválida', 'No se pudo determinar la forma de pago.');
                                return;
                            }

                            // ======================
                            // FECHA
                            // ======================
                            $fechaForm = $state['fecha_hora_facturacion'] ?? $get('fecha_hora_facturacion');
                            $fechaHoraFacturacion = $fechaForm ? Carbon::parse($fechaForm, 'America/Bogota') : null;

                            if (! $fechaHoraFacturacion) {
                                $failFast('Fecha inválida', 'Debes seleccionar la fecha y hora de facturación.');
                                return;
                            }

                            $utc       = $fechaHoraFacturacion->copy()->timezone('UTC');
                            $fechaIsoZ = $utc->format('Y-m-d\TH:i:s') . '.000Z';

                            $fechaDocumentoIso   = $fechaHoraFacturacion->format('Y-m-d\TH:i:s.vP');
                            $fechaVencimientoIso = $fechaHoraFacturacion->format('Y-m-d\TH:i:s.vP');
                            $fechaCreacionIso    = $fechaIsoZ;
                            $horaCreacionIso     = $fechaIsoZ;

                            // ======================
                            // TIPO VENTA
                            // ======================
                            $tipoVenta = $tipoVentaId ? ($memo['tipoVenta'][$tipoVentaId] ??= YTipoVenta::find($tipoVentaId)) : null;
                            if (! $tipoVenta) {
                                $failFast('Tipo de venta inválido', 'Selecciona un tipo de venta válido.');
                                return;
                            }

                            $codPatronContable = (string) $tipoVenta->Cod_Patron_Contable;
                            $tipoDocumento = substr($codPatronContable, 0, 3);

                            // ======================
                            // BODEGA (cabecera)
                            // ======================
                            $codigoBodega = (string) ($state['codigo_bodega_factura'] ?? $get('codigo_bodega_factura') ?? '');
                            if ($codigoBodega === '') {
                                $failFast('Bodega requerida', 'No se pudo determinar la bodega para facturar.');
                                return;
                            }

                            // ======================
                            // ✅ ITEMS (con variantes cuando existan)
                            // ======================
                            $itemsDoc   = [];
                            $yearActual = (int) $fechaHoraFacturacion->format('Y');
                            $mesActual  = (int) $fechaHoraFacturacion->format('m');

                            $prefijoClasificacion = 'CGT';

                            $readVariantesFromItem = function (array $item): array {
                                if (!empty($item['variantes_json']) && is_string($item['variantes_json'])) {
                                    $decoded = json_decode($item['variantes_json'], true);
                                    if (is_array($decoded)) return $decoded;
                                }

                                if (!empty($item['variantes_seleccionadas']) && is_string($item['variantes_seleccionadas'])) {
                                    $decoded = json_decode($item['variantes_seleccionadas'], true);
                                    if (is_array($decoded)) return $decoded;
                                }

                                $v = $item['variantes'] ?? [];
                                return is_array($v) ? $v : [];
                            };

                            foreach (($itemsForm ?? []) as $idx => $item) {
                                $codigoProducto = (string) ($item['codigo_producto'] ?? '');
                                $cantidad       = (int) ($item['cantidad'] ?? 0);
                                $precioUnit     = $money($item['precio_unitario_raw'] ?? $item['precio_unitario'] ?? 0);

                                if ($codigoProducto === '' || $cantidad <= 0) {
                                    continue;
                                }

                                $almacenItem = (string) (
                                    $item['codigo_bodega']
                                    ?? $item['almacen']
                                    ?? $codigoBodega
                                    ?? ''
                                );

                                $centroCostoItem = (string) (
                                    $centroCostosSede
                                    ?? $item['centroDeCosto']
                                    ?? $item['centro_de_costo']
                                    ?? ''
                                );

                                $docItem = [
                                    'producto'       => $codigoProducto,
                                    'cantidad'       => $cantidad,
                                    'precioUnitario' => $precioUnit,
                                    'precioTotal'    => $cantidad * $precioUnit,
                                    'almacen'        => $almacenItem,
                                    'centroDeCosto'  => $centroCostoItem,
                                ];

                                $variantesItem = $readVariantesFromItem($item);

                                $requiereVariantesUI = (bool) ($item['tiene_variantes_disponibles'] ?? false);
                                $configuradasUI      = (bool) ($item['variantes_configuradas'] ?? false);

                                if (($requiereVariantesUI || $configuradasUI) && empty($variantesItem)) {
                                    $failFast(
                                        'Faltan variantes',
                                        "El producto {$codigoProducto} requiere variantes (IMEI/Serial) y no se seleccionaron."
                                    );
                                    return;
                                }

                                if (!empty($variantesItem) && count($variantesItem) !== $cantidad) {
                                    $failFast(
                                        'Variantes incompletas',
                                        "El producto {$codigoProducto} requiere {$cantidad} variante(s). Seleccionaste " . count($variantesItem) . "."
                                    );
                                    return;
                                }

                                if (!empty($variantesItem)) {
                                    $clasif = [];

                                    foreach ($variantesItem as $v) {
                                        $codigoClasificacion = (string) ($v['codigo'] ?? $v['imei'] ?? $v['serial'] ?? '');
                                        if ($codigoClasificacion === '') continue;

                                        $clasif[] = [
                                            'codigoClasificacion' => $codigoClasificacion,
                                            'prefijo'             => $prefijoClasificacion,
                                            'producto'            => $codigoProducto,
                                            'cantidad'            => 1,
                                            'tipoDocumento'       => (string) $tipoDocumento,
                                            'numero'              => 0,
                                            'numeroItem'          => (int) $idx,
                                            'año'                 => (string) $yearActual,
                                            'mes'                 => str_pad((string) $mesActual, 2, '0', STR_PAD_LEFT),
                                        ];
                                    }

                                    if (!empty($clasif)) {
                                        $docItem['mtoDetalladoClasificacion'] = $clasif;
                                    }
                                }

                                $itemsDoc[] = $docItem;
                            }

                            if (!$itemsDoc) {
                                $failFast('Sin productos', 'No hay ítems válidos para facturar.');
                                return;
                            }

                            $almacenHeader = (string) ($itemsDoc[0]['almacen'] ?? $codigoBodega);

                            $uniqueAlmacenes = array_values(array_unique(array_map(
                                fn($x) => (string) ($x['almacen'] ?? ''),
                                $itemsDoc
                            )));

                            if (count($uniqueAlmacenes) > 1) {
                                Log::warning('Factura con múltiples bodegas en items. Encabezado tomará la primera.', [
                                    'almacenes' => $uniqueAlmacenes,
                                    'almacen_header' => $almacenHeader,
                                ]);
                            }

                            // ======================
                            // API
                            // ======================
                            $apiFacturacion = app(EnvioFacturacionController::class);

                            // ======================
                            // CREAR FACTURA
                            // ======================
                            $payloadCrearFactura = [
                                'documento' => [
                                    'tipoDocumento'               => (string) $tipoDocumento,
                                    'prefijo'                     => (string) ($prefijoSede ?? ''),
                                    'numero'                      => 0,
                                    'fechaDocumento'              => (string) $fechaDocumentoIso,
                                    'fechaVencimiento'            => (string) $fechaVencimientoIso,
                                    'accionesSobreNuevoDocumento' => 'CNN',
                                    'codigoTercero1'              => (int) $nit,
                                    'numeroDeCaja'                => (string) ($codigoSucursalSede ?? ''),
                                    'precioTotal'                 => (float) $totalFinal,
                                    'codigoFormaPago'             => (string) $codigoFormaPagoCabecera,
                                    'codigoVendedor'              => (string) $codigoVendedor,
                                    'almacenOrigenEncabezado'     => $almacenHeader,
                                    'codigoUnidadNegocio'         => (string) ($codigoUnidadNegocio ?? ''),
                                    'codigoPatronContable'        => (string) ($codPatronContable ?? ''),
                                    'fechaCreacion'               => (string) $fechaCreacionIso,
                                    'horaCreacion'                => (string) $horaCreacionIso,
                                    'numeroCaja'                  => (string) ($codigoSucursalSede ?? ''),
                                    'items'                       => $itemsDoc,
                                    'observaciones'               => (string) ($state['observaciones_pago'] ?? ''),
                                ],
                            ];

                            $numeroFacturaGenerada = 0;

                            try {
                                Log::info('EnvioFacturacionController@crearFactura: Enviando payload a API_CREAR_FACTURA', [
                                    'payload' => $payloadCrearFactura,
                                ]);

                                $respuestaCrear = $apiFacturacion->crearFactura($payloadCrearFactura);
                                $nCrear = $normalizeApi($respuestaCrear);

                                Log::info('crearFactura → respuesta API (normalizada)', $nCrear);

                                if (! $nCrear['ok']) {
                                    $failFast('Error', 'No se pudo crear factura: ' . ($nCrear['message'] ?? 'Error desconocido'));
                                    return;
                                }

                                $numeroFacturaGenerada = (int) (
                                    data_get($respuestaCrear, 'datos.numero')
                                    ?? data_get($respuestaCrear, 'tag.numero')
                                    ?? data_get($respuestaCrear, 'documento.numero')
                                    ?? data_get($respuestaCrear, 'numeroFactura')
                                    ?? 0
                                );

                                if ($numeroFacturaGenerada <= 0) {
                                    Log::warning('crearFactura: La API respondió pero no se encontró el número de factura', [
                                        'respuesta' => $respuestaCrear,
                                    ]);

                                    $failFast('Error', 'La API no devolvió el número de factura.');
                                    return;
                                }

                                $set('numero_factura_generada', $numeroFacturaGenerada);
                            } catch (\Throwable $e) {
                                Log::error('crearFactura error', ['error' => $e->getMessage()]);
                                $failFast('Error', 'Error creando factura: ' . $e->getMessage());
                                return;
                            }

                            // ======================
                            // PAGAR FACTURA
                            // ======================
                            $numeroFactura = (int) $numeroFacturaGenerada;
                            $totalFactura  = (int) $totalFinal;

                            // ✅ Helper: si un medio requiere banco (según tu Step)
                            $medioRequiereBanco = function (int $idMedio) use ($tipoVentaId): bool {
                                // Si es tipo venta plataformas, no manejamos banco aquí
                                if ($tipoVentaId === 3) return false;
                                return in_array($idMedio, [2, 3, 10], true);
                            };

                            $pagosForm = [];

                            $principalId    = (int) ($state['medio_pago'] ?? $get('medio_pago') ?? 0);
                            $principalValor = $money($state['plataforma_principal'] ?? $get('plataforma_principal') ?? 0);

                            // ✅ banco principal (texto ya viene del Step MediosPagoStep)
                            $bancoPrincipalText = (string) (
                                $state['banco_transferencia_principal_text']
                                ?? $get('banco_transferencia_principal_text')
                                ?? ''
                            );
                            $bancoPrincipalText = trim($bancoPrincipalText);

                            if ($principalId > 0 && $principalValor > 0) {
                                $pagosForm[] = [
                                    'medio_pago_id' => $principalId,
                                    'valor'         => $principalValor,
                                    'banco'         => ($medioRequiereBanco($principalId) ? ($bancoPrincipalText !== '' ? $bancoPrincipalText : null) : null),
                                ];
                            }

                            $metodos = $state['metodos_pago'] ?? $get('metodos_pago') ?? [];
                            if (!is_array($metodos)) $metodos = [];

                            foreach ($metodos as $m) {
                                $valor = $money($m['monto'] ?? 0);
                                if ($valor <= 0) continue;

                                $idMedio = 0;
                                if (!empty($m['plataforma_id'])) $idMedio = (int) $m['plataforma_id'];
                                elseif (!empty($m['medio_id']))  $idMedio = (int) $m['medio_id'];

                                if ($idMedio <= 0) continue;

                                // ✅ banco por item del repeater (texto ya viene del Step)
                                $bancoItemText = (string) ($m['banco_transferencia_text'] ?? '');
                                $bancoItemText = trim($bancoItemText);

                                $pagosForm[] = [
                                    'medio_pago_id' => $idMedio,
                                    'valor'         => $valor,
                                    'banco'         => ($medioRequiereBanco($idMedio) ? ($bancoItemText !== '' ? $bancoItemText : null) : null),
                                ];
                            }

                            if (!$pagosForm) {
                                $pagosForm = [[
                                    'medio_pago_id' => $principalId,
                                    'valor'         => $totalFactura,
                                    'banco'         => ($medioRequiereBanco($principalId) ? ($bancoPrincipalText !== '' ? $bancoPrincipalText : null) : null),
                                ]];
                            }

                            $sumaPagos = 0;
                            foreach ($pagosForm as $pf) $sumaPagos += (int) ($pf['valor'] ?? 0);

                            if ($sumaPagos <= 0) {
                                $failFast('Pagos inválidos', 'Los valores de pago están en 0.');
                                return;
                            }

                            if ($sumaPagos !== $totalFactura) {
                                $failFast(
                                    'Pagos no cuadran',
                                    'Total factura: $' . number_format($totalFactura, 0, ',', '.') .
                                    ' | Total pagos: $' . number_format($sumaPagos, 0, ',', '.')
                                );
                                return;
                            }

                            $resolveMedioPagoNombreById = function (int $idMedio) use (&$memo, $tipoVentaId): string {
                                if ($tipoVentaId === 3) {
                                    $pl = $memo['plataforma'][$idMedio] ??= ZPlataformaCredito::find($idMedio);
                                    return $pl
                                        ? (string) ($pl->plataforma ?? $pl->Plataforma ?? $pl->Nombre ?? $pl->nombre ?? ('Plataforma #' . $idMedio))
                                        : ('Plataforma #' . $idMedio);
                                }

                                $m = $memo['medioPago'][$idMedio] ??= YMedioPago::find($idMedio);
                                return $m
                                    ? (string) ($m->Nombre_Medio_Pago ?? $m->Nombre ?? $m->nombre ?? $m->Medio_pago ?? $m->medio_pago ?? ('Medio #' . $idMedio))
                                    : ('Medio #' . $idMedio);
                            };

                            $resolveCodigoFormaPagoById = function (int $idMedio) use (&$memo, $tipoVentaId, $codigoCajaSede): string {
                                if ($tipoVentaId === 3) {
                                    $pl = $memo['plataforma'][$idMedio] ??= ZPlataformaCredito::find($idMedio);
                                    return (string) ($pl?->mediosPagoCodigos ?? $idMedio);
                                }

                                $m = $memo['medioPago'][$idMedio] ??= YMedioPago::find($idMedio);
                                $cod = (string) ($m?->Codigo_forma_Pago ?? $idMedio);

                                if (($cod === '' || $cod === '0') && !empty($codigoCajaSede)) {
                                    $cod = (string) $codigoCajaSede;
                                }

                                return $cod;
                            };

                            $pagos = [];
                            $mediosPagoRecibo = [];
                            $index = 1;

                            foreach ($pagosForm as $pf) {
                                $idMedio = (int) ($pf['medio_pago_id'] ?? 0);
                                $valor   = (int) ($pf['valor'] ?? 0);

                                if ($idMedio <= 0 || $valor <= 0) continue;

                                $codigoForma = $resolveCodigoFormaPagoById($idMedio);
                                $nombreMedio = $resolveMedioPagoNombreById($idMedio);

                                $pagos[] = [
                                    'valorPagadoSinPropina' => $valor,
                                    'numeroFactura'         => $numeroFactura,
                                    'año'                   => $yearActual,
                                    'mes'                   => $mesActual,
                                    'valorPagado'           => $valor,
                                    'fecha'                 => (string) $fechaCreacionIso,
                                    'objFormaPago'          => ['formaPago' => (string) $codigoForma],
                                    'index'                 => $index++,
                                    'valorPagadoOriginal'   => $totalFactura,
                                    'tipoTransaccion'       => (string) $tipoDocumento,
                                    'prefijo'               => (string) ($prefijoSede ?? ''),
                                    'codigoFormaPago'       => (string) $codigoForma,
                                    'caja'                  => (string) ($codigoCajaSede ?? ''),
                                ];

                                // ✅ ahora sí: medio + banco al recibo
                                $mediosPagoRecibo[] = [
                                    'codigo' => (string) $codigoForma,
                                    'nombre' => (string) $nombreMedio,
                                    'banco'  => $pf['banco'] ?? null,
                                    'valor'  => (int) $valor,
                                ];
                            }

                            if (!$pagos) {
                                $failFast('Pagos inválidos', 'No hay medios de pago válidos para procesar.');
                                return;
                            }

                            try {
                                $respuestaPago = $apiFacturacion->pagarFactura(['pagos' => $pagos]);
                                $nPago = $normalizeApi($respuestaPago);

                                if (! $nPago['ok']) {
                                    $failFast('Error', 'No se pudo pagar factura: ' . ($nPago['message'] ?? ''));
                                    return;
                                }
                            } catch (\Throwable $e) {
                                Log::error('pagarFactura error', ['error' => $e->getMessage()]);
                                $failFast('Error', 'Error pagando factura: ' . $e->getMessage());
                                return;
                            }

                            $notify('success', 'Factura creada y pagada', "Factura: {$prefijoSede}-{$numeroFactura}\nTotal: {$totalFactura}", false);

                            // ======================
                            // RECIBO (productos)
                            // ======================
                            $productosRecibo = [];
                            $totalProductos = 0;

                            foreach (($itemsForm ?? []) as $item) {
                                if (empty($item['codigo_producto']) && empty($item['nombre_producto'])) continue;

                                $nombreProducto = (string) ($item['nombre_producto'] ?? $item['codigo_producto'] ?? 'No definido');

                                $variantesItem = [];
                                if (!empty($item['variantes_json']) && is_string($item['variantes_json'])) {
                                    $decoded = json_decode($item['variantes_json'], true);
                                    if (is_array($decoded)) $variantesItem = $decoded;
                                }
                                if (empty($variantesItem) && !empty($item['variantes_seleccionadas']) && is_string($item['variantes_seleccionadas'])) {
                                    $decoded = json_decode($item['variantes_seleccionadas'], true);
                                    if (is_array($decoded)) $variantesItem = $decoded;
                                }
                                if (empty($variantesItem) && !empty($item['variantes']) && is_array($item['variantes'])) {
                                    $variantesItem = $item['variantes'];
                                }

                                $textoVariante = 'No aplica';
                                if (is_array($variantesItem) && !empty($variantesItem)) {
                                    $codigos = [];
                                    foreach ($variantesItem as $var) {
                                        $c = $var['codigo'] ?? $var['imei'] ?? $var['serial'] ?? null;
                                        if ($c) $codigos[] = (string) $c;
                                    }
                                    if ($codigos) $textoVariante = implode(', ', $codigos);
                                }

                                $cantidad       = (int) ($item['cantidad'] ?? 0);
                                $precioUnitario = $money($item['precio_unitario_raw'] ?? $item['precio_unitario'] ?? 0);
                                $subtotal       = $cantidad * $precioUnitario;

                                $totalProductos += $subtotal;

                                $productosRecibo[] = [
                                    'producto' => $nombreProducto,
                                    'variante' => $textoVariante,
                                    'cantidad' => $cantidad,
                                    'subtotal' => $subtotal,
                                ];
                            }

                            // ======================
                            // RECIBO (cliente)
                            // ======================
                            $tipoIdCliente = strtoupper(trim((string) (
                                $state['tipo_identificacion_cliente']
                                ?? $get('tipo_identificacion_cliente')
                                ?? ''
                            )));

                            $clienteNombre = '';

                            if ($tipoIdCliente === 'NIT') {
                                $clienteNombre = trim((string) ($state['nombre_cliente'] ?? $get('nombre_cliente') ?? ''));
                            } else {
                                $n1 = trim((string) ($state['nombre1_cliente'] ?? $get('nombre1_cliente') ?? ''));
                                $n2 = trim((string) ($state['nombre2_cliente'] ?? $get('nombre2_cliente') ?? ''));
                                $a1 = trim((string) ($state['apellido1_cliente'] ?? $get('apellido1_cliente') ?? ''));
                                $a2 = trim((string) ($state['apellido2_cliente'] ?? $get('apellido2_cliente') ?? ''));

                                $clienteNombre = trim(implode(' ', array_filter([$n1, $n2, $a1, $a2])));
                            }

                            if ($clienteNombre === '') {
                                $clienteNombre = trim((string) ($state['nombre_comercial_cliente'] ?? $get('nombre_comercial_cliente') ?? ''));
                            }
                            if ($clienteNombre === '') $clienteNombre = "Cliente {$nit}";

                            // ======================
                            // RECIBO (sede)
                            // ======================
                            $sedeNombre = trim((string) (
                                $get('detallesCliente.0.nombre_sede')
                                ?? data_get($state, 'detallesCliente.0.nombre_sede')
                                ?? ''
                            ));

                            if ($sedeNombre === '' && $sedeObj) {
                                $sedeNombre = trim((string) (
                                    $sedeObj->Name_Sede
                                    ?? $sedeObj->Nombre_sede
                                    ?? $sedeObj->Nombre
                                    ?? $sedeObj->nombre_sede
                                    ?? $sedeObj->nombre
                                    ?? ''
                                ));
                            }

                            if ($sedeNombre === '') $sedeNombre = (string) ($prefijoSede ?: '—');

                            // ======================
                            // RECIBO (asesor)
                            // ======================
                            $asesorNombre = trim((string) (
                                $get('detallesCliente.0.nombre_asesor_select')
                                ?? data_get($state, 'detallesCliente.0.nombre_asesor_select')
                                ?? ''
                            ));

                            if ($asesorNombre === '' || ctype_digit($asesorNombre)) {
                                $asesorNombre = trim((string) (
                                    auth()->user()?->name
                                    ?? auth()->user()?->nombre
                                    ?? $codigoVendedor
                                    ?? '—'
                                ));
                            }

                            $medioPagoPrincipalNombre = $mediosPagoRecibo[0]['nombre'] ?? '—';
                            $medioPagoPrincipalCodigo = $mediosPagoRecibo[0]['codigo'] ?? '—';

                            // ======================
                            // CACHE + ABRIR MODAL
                            // ======================
                            $clienteIdCarpeta = (string) (
                                $state['cliente_id']
                                ?? $state['id_cliente']
                                ?? $state['idcliente']
                                ?? $nit
                            );

                            $cacheKey = 'recibo_factura_' . $clienteIdCarpeta . '_' . ($prefijoSede ?? '') . '_' . $numeroFactura . '_' . Str::random(10);

                            Cache::put($cacheKey, [
                                'cliente_id'      => $clienteIdCarpeta,
                                'cliente_nombre'  => $clienteNombre,
                                'asesor_nombre'   => $asesorNombre,
                                'vendedor_nombre' => $asesorNombre,
                                'sede_nombre'     => $sedeNombre,
                                'prefijo'         => (string) ($prefijoSede ?? ''),
                                'numero'          => (int) $numeroFactura,
                                'total'           => (int) $totalFactura,
                                'total_productos' => (int) $totalProductos,
                                'fecha'           => $fechaHoraFacturacion->format('d/m/Y H:i'),
                                'nit'             => (int) $nit,
                                'productos'       => $productosRecibo,

                                // ✅ aquí ya va banco por cada medio
                                'medios_pago'     => $mediosPagoRecibo,

                                'medio_pago_principal_nombre' => $medioPagoPrincipalNombre,
                                'medio_pago_principal_codigo' => $medioPagoPrincipalCodigo,

                                // ✅ opcional: por si lo quieres usar directo
                                'banco_principal' => $bancoPrincipalText !== '' ? $bancoPrincipalText : null,

                                'watermark_text'  => "NO ES FACTURA DE VENTA\nDOCUMENTO INFORMATIVO",
                                'watermark_font'  => 'Calibri',
                            ], now()->addMinutes(10));

                            $urlFactura = route('facturas.factura.show', ['key' => $cacheKey]);

                            Log::info('🧾 Factura cacheada y abriendo modal', [
                                'cache_key' => $cacheKey,
                                'url'       => $urlFactura,
                            ]);

                            if (method_exists($livewire, 'dispatch')) {
                                $livewire->dispatch('open-recibo-modal', url: $urlFactura);
                            } else {
                                if (method_exists($livewire, 'dispatchBrowserEvent')) {
                                    $livewire->dispatchBrowserEvent('open-recibo-modal', ['url' => $urlFactura]);
                                }
                            }

                            return;
                        }),
                ])->extraAttributes(['class' => 'mt-4 flex justify-end']),
            ]);
    }
}
