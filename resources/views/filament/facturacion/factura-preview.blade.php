<!-- {{-- resources/views/filament/facturacion/recibo-preview.blade.php --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Recibo</title>

    <style>
        :root{
            --ink:#0b1220;
            --muted:#55657a;
            --line:#d9e2ef;
            --soft:#f5f7fb;

            --accent:#63b300;
            --accent-2:#2f8f00;

            --radius:16px;
            --radius-sm:12px;

            --shadow: 0 12px 28px rgba(11,18,32,.10);
            --shadow-soft: 0 10px 24px rgba(11,18,32,.08);
        }

        *{ box-sizing:border-box; }

        body{
            margin:0;
            background:#eef2f7;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
            color:var(--ink);
            -webkit-font-smoothing:antialiased;
            -moz-osx-font-smoothing:grayscale;
        }

        .page{
            padding:22px;
            display:flex;
            justify-content:center;
        }

        .sheet{
            width: 940px;
            max-width: 100%;
            background:#fff;
            border:1px solid rgba(217,226,239,.95);
            border-radius:var(--radius);
            overflow:hidden;
            box-shadow: var(--shadow);
        }

        .recibo-wrap{
            position: relative;
            background:#fff;
        }

        .watermark{
            position:absolute;
            inset:0;
            z-index: 40;
            pointer-events:none;
            display:flex;
            align-items:center;
            justify-content:center;
            transform: rotate(-12deg);
            text-align:center;
            font-weight: 1000;
            text-transform: uppercase;
            letter-spacing: 2px;
            line-height: 1.1;
            color: rgba(15, 23, 42, .10);
            font-size: 44px;
            padding: 0 18px;
            white-space: pre-line;
        }
        @media (max-width:640px){
            .watermark{ font-size: 26px; }
        }

        .content{
            position:relative;
            z-index:10;
        }

        .header{
            position:relative;
            padding:18px 20px 14px;
            background:linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
            border-bottom:1px solid rgba(217,226,239,.95);
        }

        .header::before{
            content:"";
            position:absolute;
            left:0; top:0; right:0;
            height:10px;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
        }

        .header-top{
            display:flex;
            justify-content:space-between;
            gap:16px;
            padding-top:8px;
        }

        .brand{
            display:flex;
            gap:12px;
            align-items:flex-start;
            min-width: 280px;
        }

        .logo{
            width:46px;
            height:46px;
            border-radius:14px;
            border:1px solid rgba(99,179,0,.25);
            background:linear-gradient(180deg, rgba(99,179,0,.12), rgba(99,179,0,.04));
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:1000;
            color:var(--accent);
            letter-spacing:.4px;
            user-select:none;
            overflow:hidden;
            box-shadow: 0 10px 18px rgba(11,18,32,.08);
        }

        .logo img{
            width:100%;
            height:100%;
            object-fit:cover;
            display:block;
        }

        .brand h1{
            margin:0;
            font-size:14px;
            font-weight:1000;
            letter-spacing:.2px;
        }

        .brand p{
            margin:4px 0 0;
            font-size:11px;
            color:var(--muted);
            line-height:1.45;
        }

        .doc-meta{
            text-align:right;
            font-size:12px;
            color:var(--muted);
            line-height:1.6;
            padding:10px 12px;
            border-radius:14px;
            border:1px solid rgba(217,226,239,.95);
            background: #f7f9fd;
            box-shadow: 0 10px 22px rgba(11,18,32,.06);
            white-space:nowrap;
        }
        .doc-meta strong{ color:var(--ink); }

        .doc-title{
            margin-top:12px;
            display:flex;
            justify-content:space-between;
            align-items:flex-end;
            gap:16px;
            flex-wrap:wrap;
        }

        .doc-title .name{
            font-size:18px;
            font-weight:1100;
            letter-spacing:.2px;
            margin:0;
        }

        .pad{ padding:18px 20px 20px; }

        .summary{
            display:grid;
            grid-template-columns: 1.4fr .8fr .8fr;
            gap:12px;
            margin-top:14px;
        }

        .box{
            border:1px solid rgba(217,226,239,.95);
            border-radius:var(--radius-sm);
            padding:12px;
            background:#fff;
            box-shadow: var(--shadow-soft);
        }

        .k{
            font-size:11px;
            color:var(--muted);
            text-transform:none;
            letter-spacing:.2px;
            margin-bottom:6px;
            font-weight:900;
        }

        .v{
            font-size:13px;
            font-weight:1000;
            color:var(--ink);
            word-break: break-word;
        }

        .sub{
            margin-top:4px;
            font-size:12px;
            color:var(--muted);
            font-weight:800;
            word-break: break-word;
        }

        .big-money{
            font-size:18px;
            font-weight:1200;
            color:var(--ink);
            letter-spacing:.2px;
        }

        .section-title{
            margin:18px 0 10px;
            font-size:12px;
            font-weight:1100;
            color:var(--ink);
            text-transform:none;
            letter-spacing:.2px;
            display:flex;
            align-items:center;
            gap:10px;
        }

        .section-title::after{
            content:"";
            height:1px;
            flex:1;
            background:var(--line);
        }

        table{
            width:100%;
            border-collapse:separate;
            border-spacing:0;
            border:1px solid rgba(217,226,239,.95);
            border-radius:var(--radius-sm);
            overflow:hidden;
            background:#fff;
            box-shadow: var(--shadow-soft);
        }

        th, td{
            padding:10px 12px;
            border-bottom:1px solid rgba(217,226,239,.95);
            font-size:12px;
            vertical-align: top;
        }

        th{
            background:linear-gradient(180deg, #f6f8fb, #ffffff);
            text-align:left;
            color:#4b5563;
            font-weight:1100;
            text-transform:none;
            letter-spacing:.2px;
            font-size:11px;
        }

        tr:last-child td{ border-bottom:none; }
        tbody tr:nth-child(even) td{ background:#fcfdff; }
        .right{ text-align:right; }

        .totals{
            margin-top:14px;
            display:flex;
            justify-content:flex-end;
        }

        .totals .box{
            width: 360px;
            max-width:100%;
            padding:0;
            overflow:hidden;
        }

        .totals .row{
            display:flex;
            justify-content:space-between;
            padding:10px 12px;
            font-size:12px;
            border-bottom:1px solid rgba(217,226,239,.95);
            background:#fff;
        }

        .totals .row:last-child{ border-bottom:none; }

        .totals .label{
            color:var(--muted);
            font-weight:900;
            text-transform:none;
            letter-spacing:.2px;
            font-size:11px;
        }

        .totals .value{
            font-weight:1100;
            font-size:13px;
            letter-spacing:.2px;
        }

        .totals .grand{
            background:linear-gradient(90deg, rgba(99,179,0,.10), rgba(99,179,0,.02));
            border-top:1px solid rgba(99,179,0,.20);
        }

        .foot{
            margin-top:14px;
            padding:14px 16px 16px;
            color:var(--muted);
            font-size:11px;
            border:1px dashed rgba(91,103,122,.35);
            border-radius:var(--radius-sm);
            line-height:1.5;
            background:#fff;
            box-shadow: 0 10px 18px rgba(11,18,32,.05);
        }
        .foot strong{ color:var(--ink); }

        @media (max-width: 860px){
            .header-top{ flex-direction:column; align-items:flex-start; }
            .doc-meta{ white-space:normal; text-align:left; }
            .summary{ grid-template-columns:1fr; }
        }

        @media print{
            body{ background:#fff; }
            .page{ padding:0; }
            .sheet{ box-shadow:none; border-radius:0; border:none; width:100%; }
            .watermark{ display:none; }
        }

        /* MODO IFRAME / MODAL */
        body.in-iframe{ background: transparent !important; }
        body.in-iframe .page{ padding: 12px !important; }
        body.in-iframe .sheet{
            width: 100% !important;
            max-width: 980px !important;
            box-shadow: 0 12px 28px rgba(11,18,32,.10) !important;
        }
        body.in-iframe .sheet:hover{ transform:none !important; }
    </style>
</head>

<body>
@php
    $clienteNombre = trim((string)($data['cliente_nombre'] ?? ''));
    $nit           = (string)($data['nit'] ?? '—');
    if ($clienteNombre === '') $clienteNombre = 'Cliente ' . $nit;

    $asesorNombre = trim((string)($data['asesor_nombre'] ?? $data['vendedor_nombre'] ?? '—'));
    if ($asesorNombre === '') $asesorNombre = '—';

    $sedeNombre = trim((string)($data['sede_nombre'] ?? '—'));
    if ($sedeNombre === '') $sedeNombre = '—';

    $prefijo = (string)($data['prefijo'] ?? 'RCB');
    $numero  = (string)($data['numero'] ?? '0');
    $fecha   = (string)($data['fecha'] ?? '');

    $productosRaw  = $data['productos'] ?? [];
    $mediosPagoRaw = $data['medios_pago'] ?? [];

    $productos  = is_array($productosRaw) ? $productosRaw : (method_exists($productosRaw, 'toArray') ? $productosRaw->toArray() : []);
    $mediosPago = is_array($mediosPagoRaw) ? $mediosPagoRaw : (method_exists($mediosPagoRaw, 'toArray') ? $mediosPagoRaw->toArray() : []);

    $productos  = array_values(array_filter(array_map(fn($x) => (array)$x, $productos)));
    $mediosPago = array_values(array_filter(array_map(fn($x) => (array)$x, $mediosPago)));

    $totalProductos = (float)($data['total_productos'] ?? 0);
    $totalFactura   = (float)($data['total'] ?? 0);

    if ($totalProductos <= 0 && !empty($productos)) {
        $tp = 0;
        foreach ($productos as $p) $tp += (float)($p['subtotal'] ?? 0);
        $totalProductos = (float)$tp;
    }
    if ($totalFactura <= 0) $totalFactura = $totalProductos > 0 ? $totalProductos : 0;

    $watermarkText = (string)($data['watermark_text'] ?? "NO ES FACTURA DE VENTA\nDOCUMENTO INFORMATIVO");
    $watermarkFont = trim((string)($data['watermark_font'] ?? 'Calibri'));
    if ($watermarkFont === '') $watermarkFont = 'Calibri';

    $money = function($n){
        return '$' . number_format((float)$n, 0, ',', '.');
    };

    $empresaNombre    = trim((string)($data['empresa_nombre'] ?? 'Distribuciones Tecnológicas de Colombia S.A.S.'));
    $empresaNit       = trim((string)($data['empresa_nit'] ?? 'NIT: 901042503-1'));
    $empresaDireccion = trim((string)($data['empresa_direccion'] ?? 'Dirección: Calle 22 # /8-22'));
    $empresaCiudad    = trim((string)($data['empresa_ciudad'] ?? 'Pereira, Colombia'));
    $empresaTel       = trim((string)($data['empresa_telefono'] ?? 'Tel: 3136200202'));
    $empresaEmail     = trim((string)($data['empresa_email'] ?? 'Email: info@empresa.com'));
    $empresaLogoUrl   = trim((string)($data['empresa_logo_url'] ?? ''));

    $generadoEn = (string)($data['generado_en'] ?? now()->format('Y-m-d H:i:s'));

    $medioNombreMap = [];
    $codigos = collect($mediosPago)->map(fn($m) => trim((string)($m['codigo'] ?? '')))->filter()->unique()->values()->all();

    if (!empty($codigos)) {
        try {
            $rows = \App\Models\YMedioPago::query()
                ->whereIn('Codigo_forma_Pago', $codigos)
                ->orWhereIn('codigo_forma_pago', $codigos)
                ->get();

            foreach ($rows as $row) {
                $code = trim((string)($row->Codigo_forma_Pago ?? $row->codigo_forma_pago ?? ''));
                $name = trim((string)(
                    $row->Nombre_Medio_Pago
                    ?? $row->Nombre
                    ?? $row->nombre
                    ?? $row->Medio_pago
                    ?? $row->medio_pago
                    ?? ''
                ));
                if ($code !== '' && $name !== '') $medioNombreMap[$code] = $name;
            }
        } catch (\Throwable $e) {}

        try {
            $pls = \App\Models\ZPlataformaCredito::query()
                ->whereIn('mediosPagoCodigos', $codigos)
                ->orWhereIn('MediosPagoCodigos', $codigos)
                ->get();

            foreach ($pls as $pl) {
                $code = trim((string)($pl->mediosPagoCodigos ?? $pl->MediosPagoCodigos ?? ''));
                $name = trim((string)(
                    $pl->plataforma
                    ?? $pl->Plataforma
                    ?? $pl->Nombre
                    ?? $pl->nombre
                    ?? $pl->Descripcion
                    ?? $pl->descripcion
                    ?? ''
                ));
                if ($code !== '' && $name !== '' && !isset($medioNombreMap[$code])) $medioNombreMap[$code] = $name;
            }
        } catch (\Throwable $e) {}
    }

    $resolveMedioPagoNombre = function(array $m) use ($medioNombreMap) {
        $nombre = trim((string)($m['nombre'] ?? ''));
        if ($nombre !== '') return $nombre;

        $codigo = trim((string)($m['codigo'] ?? ''));
        if ($codigo === '') return 'N/D';

        return $medioNombreMap[$codigo] ?? $codigo;
    };
@endphp

<div class="page">
    <div class="sheet" id="recibo-print">
        <div class="recibo-wrap">

            @if(trim($watermarkText) !== '')
                <div class="watermark" style="font-family: {{ e($watermarkFont) }}, Arial, Helvetica, sans-serif;">
                    {!! nl2br(e($watermarkText)) !!}
                </div>
            @endif

            <div class="content">
                <div class="header">
                    <div class="header-top">
                        <div class="brand">
                            <div class="logo">
                                @if($empresaLogoUrl !== '')
                                    <img src="{{ $empresaLogoUrl }}" alt="Logo">
                                @else
                                    {{ mb_substr($empresaNombre, 0, 2) }}
                                @endif
                            </div>
                            <div>
                                <h1>{{ $empresaNombre }}</h1>
                                <p>
                                    {{ $empresaNit }} · {{ $empresaDireccion }} · {{ $empresaCiudad }}<br>
                                    {{ $empresaTel }} · {{ $empresaEmail }}
                                </p>
                            </div>
                        </div>

                        <div class="doc-meta">
                            <div><strong>Recibo:</strong> {{ $prefijo }}-{{ $numero }}</div>
                            <div><strong>Fecha:</strong> {{ $fecha ?: '—' }}</div>
                            <div><strong>Sede:</strong> {{ $sedeNombre }}</div>
                        </div>
                    </div>

                    <div class="doc-title">
                        <p class="name">Recibo</p>
                    </div>
                </div>

                <div class="pad">
                    <div class="summary">
                        <div class="box">
                            <div class="k">Cliente</div>
                            <div class="v">{{ $clienteNombre }}</div>
                            <div class="sub">NIT/CC: {{ $nit }}</div>
                            <div class="sub">Vendedor: {{ $asesorNombre }}</div>
                        </div>

                        <div class="box">
                            <div class="k">Total recibido</div>
                            <div class="big-money">{{ $money($totalFactura) }}</div>
                            <div class="sub">Total productos: {{ $money($totalProductos) }}</div>
                        </div>

                        <div class="box">
                            <div class="k">Resumen</div>
                            <div class="v">{{ count($productos) }} ítem(s)</div>
                            <div class="sub">{{ count($mediosPago) }} medio(s) de pago</div>
                        </div>
                    </div>

                    <div class="section-title">Productos</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Variante</th>
                                <th class="right">Cant.</th>
                                <th class="right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($productos as $p)
                                <tr>
                                    <td>{{ (string)($p['producto'] ?? '—') }}</td>
                                    <td>{{ (string)($p['variante'] ?? 'No aplica') }}</td>
                                    <td class="right">{{ (int)($p['cantidad'] ?? 0) }}</td>
                                    <td class="right">{{ $money((float)($p['subtotal'] ?? 0)) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="text-align:center; color:#55657a; font-weight:900;">
                                        No hay productos para mostrar
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="totals">
                        <div class="box">
                            <div class="row">
                                <div class="label">Total productos</div>
                                <div class="value">{{ $money($totalProductos) }}</div>
                            </div>
                            <div class="row grand">
                                <div class="label">Total recibido</div>
                                <div class="value">{{ $money($totalFactura) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="section-title">Medios de pago</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Medio</th>
                                <th class="right">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($mediosPago as $m)
                                <tr>
                                    <td>{{ $resolveMedioPagoNombre((array)$m) }}</td>
                                    <td class="right">{{ $money((float)($m['valor'] ?? 0)) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" style="text-align:center; color:#55657a; font-weight:900;">
                                        No hay medios de pago para mostrar
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="foot">
                        Documento generado para soporte interno y trazabilidad.
                        <br><br>
                        <strong>Generado:</strong> {{ $generadoEn }}.
                        En caso de soporte, comparta la referencia <strong>{{ $prefijo }}-{{ $numero }}</strong>.
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    try {
        if (window.self !== window.top) document.body.classList.add('in-iframe');
    } catch (e) {
        document.body.classList.add('in-iframe');
    }
})();
</script>

</body>
</html> -->
