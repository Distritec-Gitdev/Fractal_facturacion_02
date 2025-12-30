{{-- resources/views/filament/facturacion/recibo-preview.blade.php --}}
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

            /* ✅ COLORES CORPORATIVOS */
            --accent:#82CC0E;
            --accent-2:#0b0f14; /* negro elegante */
            --accent-soft: rgba(130,204,14,.16);
            --accent-soft-2: rgba(130,204,14,.08);

            --radius:16px;
            --radius-sm:12px;

            --shadow: 0 14px 34px rgba(11,18,32,.12);
            --shadow-soft: 0 10px 24px rgba(11,18,32,.08);
        }

        *{ box-sizing:border-box; }

        body{
            margin:0;
            background: linear-gradient(180deg, #eef2f7 0%, #e9edf4 100%);
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
            color: rgba(11, 15, 20, .10);
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

        /* ✅ HEADER MÁS CORPORATIVO */
        .header{
            position:relative;
            padding:18px 20px 14px;
            background:
                radial-gradient(900px 240px at 10% 0%, var(--accent-soft), transparent 55%),
                linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
            border-bottom:1px solid rgba(217,226,239,.95);
        }

        .header::before{
            content:"";
            position:absolute;
            left:0; top:0; right:0;
            height:10px;
            background: linear-gradient(90deg, var(--accent), rgba(130,204,14,.45), var(--accent-2));
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
            border:1px solid rgba(130,204,14,.30);
            background:linear-gradient(180deg, rgba(130,204,14,.16), rgba(130,204,14,.06));
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
            background: linear-gradient(180deg, #f7f9fd, #ffffff);
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

        /* ✅ BOXES CON ACENTO VERDE */
        .box{
            border:1px solid rgba(217,226,239,.95);
            border-radius:var(--radius-sm);
            padding:12px;
            background:
                radial-gradient(700px 180px at 18% 0%, var(--accent-soft-2), transparent 55%),
                #fff;
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

        .section-title::before{
            content:"";
            width:10px;
            height:10px;
            border-radius:999px;
            background: var(--accent);
            box-shadow: 0 0 0 5px rgba(130,204,14,.15);
            display:inline-block;
        }

        .section-title::after{
            content:"";
            height:1px;
            flex:1;
            background:var(--line);
        }

        /* ✅ TABLAS MÁS “PRO” */
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
            background:
                linear-gradient(90deg, rgba(130,204,14,.14), rgba(130,204,14,.05)),
                linear-gradient(180deg, #f6f8fb, #ffffff);
            text-align:left;
            color:#2b3340;
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

        /* ✅ TOTAL FINAL MÁS CORPORATIVO */
        .totals .grand{
            background: linear-gradient(90deg, rgba(130,204,14,.18), rgba(130,204,14,.05));
            border-top:1px solid rgba(130,204,14,.28);
        }
        .totals .grand .value{
            color: #0b0f14;
            font-weight: 1200;
        }

        .foot{
            margin-top:14px;
            padding:14px 16px 16px;
            color:var(--muted);
            font-size:11px;
            border:1px dashed rgba(130,204,14,.35);
            border-radius:var(--radius-sm);
            line-height:1.5;
            background:
                radial-gradient(1000px 220px at 12% 0%, rgba(130,204,14,.10), transparent 55%),
                #fff;
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
            .vmo, .vmm { display:none !important; } /* mini-modal no imprime */
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

        /* ============================
        ✅ BOTÓN + MINI MODAL VARIANTES (CORPORATIVO)
        (NO MODIFICADO)
        ============================ */
        .btn-variants{
            appearance:none;
            border: 1px solid rgba(130, 204, 14, .55);
            background: linear-gradient(180deg, rgba(130, 204, 14, .18), rgba(130, 204, 14, .08));
            color: #0b1220;
            padding: 7px 12px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 1000;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 10px 18px rgba(11,18,32,.08);
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease, border-color .15s ease;
        }
        .btn-variants::before{
            content:"";
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: #82CC0E;
            box-shadow: 0 0 0 4px rgba(130,204,14,.18);
            display:inline-block;
        }
        .btn-variants:hover{
            border-color: rgba(130, 204, 14, .85);
            background: linear-gradient(180deg, rgba(130, 204, 14, .24), rgba(130, 204, 14, .10));
            box-shadow: 0 16px 30px rgba(11,18,32,.14);
            transform: translateY(-1px);
        }
        .btn-variants:active{
            transform: translateY(0);
            box-shadow: 0 10px 18px rgba(11,18,32,.10);
        }

        .vmo{
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.72);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 14px;
            backdrop-filter: blur(6px);
        }
        .vmo.show{ display:flex; }

        .vmm{
            width: min(760px, 96vw);
            max-height: min(78vh, 720px);
            overflow: hidden;
            border-radius: 20px;
            border: 1px solid rgba(130, 204, 14, .25);
            background: radial-gradient(1200px 400px at 15% 0%, rgba(130,204,14,.18), transparent 55%),
                        linear-gradient(180deg, #0b0f14 0%, #070a0e 100%);
            box-shadow: 0 40px 120px rgba(0,0,0,.70);
            display: flex;
            flex-direction: column;
        }

        .vmm-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap: 12px;
            padding: 14px 16px;
            background: linear-gradient(90deg, rgba(130,204,14,.20), rgba(130,204,14,.05));
            border-bottom: 1px solid rgba(255,255,255,.08);
            color: #e5e7eb;
        }
        .vmm-title{ min-width:0; }
        .vmm-title .h1{
            font-weight: 1100;
            font-size: 13px;
            letter-spacing: .2px;
            display:flex;
            align-items:center;
            gap:10px;
        }
        .vmm-title .h1::before{
            content:"";
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #82CC0E;
            box-shadow: 0 0 0 5px rgba(130,204,14,.16);
            flex: 0 0 auto;
        }
        .vmm-title .h2{
            margin-top: 6px;
            font-size: 12px;
            color: rgba(229,231,235,.78);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 64vw;
        }

        .vmm-close{
            appearance:none;
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.06);
            color:#e5e7eb;
            padding: 9px 12px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 1000;
            cursor:pointer;
            transition: transform .12s ease, border-color .12s ease, background .12s ease;
        }
        .vmm-close:hover{
            border-color: rgba(130,204,14,.55);
            background: rgba(130,204,14,.14);
            transform: translateY(-1px);
        }
        .vmm-close:active{ transform: translateY(0); }

        .vmm-body{
            padding: 14px;
            background: transparent;
            overflow: auto;
        }

        .vmm-list{
            background: rgba(255,255,255,.03);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.10);
            overflow: hidden;
            box-shadow: 0 14px 30px rgba(0,0,0,.35);
        }

        .vmm-item{
            padding: 11px 12px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 12px;
            color: rgba(229,231,235,.92);
            background: rgba(0,0,0,.18);
            word-break: break-all;
            display:flex;
            align-items:center;
            gap: 10px;
        }
        .vmm-item::before{
            content:"";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: rgba(130,204,14,.95);
            box-shadow: 0 0 0 5px rgba(130,204,14,.12);
            flex: 0 0 auto;
        }
        .vmm-item:nth-child(even){
            background: rgba(0,0,0,.26);
        }
        .vmm-item:last-child{ border-bottom:none; }

        .vmm-count{
            margin-top: 12px;
            color: rgba(229,231,235,.86);
            font-size: 12px;
            font-weight: 1000;
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding: 10px 12px;
            border-radius: 14px;
            border: 1px solid rgba(130,204,14,.18);
            background: rgba(130,204,14,.08);
        }
    </style>
</head>

<body>
@php
    // ==========================
    // Helpers seguros (sin depender de mbstring)
    // ==========================
    $lower = function(string $s): string {
        $s = (string)$s;
        return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
    };
    $substr2 = function(string $s, int $len): string {
        $s = (string)$s;
        return function_exists('mb_substr') ? mb_substr($s, 0, $len) : substr($s, 0, $len);
    };

    // ==========================
    // Data base (defensivo)
    // ==========================
    $data = is_array($data ?? null) ? $data : [];

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
    $empresaLogoUrl   = trim((string)($data['empresa_logo_url'] ?? 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTF5_J9853KbJEH3Qm-KwmFyU_JghyYA_aHTg&s'));

    $generadoEn = (string)($data['generado_en'] ?? now()->format('Y-m-d H:i:s'));

    // ==========================
    // Map de nombre de medio (SIN collect)
    // ==========================
    $medioNombreMap = [];
    $codigos = [];
    foreach ($mediosPago as $m) {
        $code = trim((string)($m['codigo'] ?? ''));
        if ($code !== '') $codigos[$code] = true;
    }
    $codigos = array_values(array_keys($codigos));

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

    // ✅ Resolver banco (blindado: string, array, llaves múltiples)
    $resolveBancoTexto = function(array $m) use ($lower) {

        // 1) banco como string
        $banco = $m['banco'] ?? null;
        if (is_string($banco)) {
            $t = trim($banco);
            if ($t !== '' && $lower($t) !== 'n/a') return $t;
        }

        // 2) banco como array/obj
        if (is_array($banco)) {
            $t = trim((string)($banco['nombre'] ?? $banco['Nombre_Banco'] ?? $banco['Cuenta_Banco'] ?? ''));
            if ($t !== '' && $lower($t) !== 'n/a') return $t;
        }

        // 3) otros nombres típicos
        $candidatos = [
            $m['banco_transferencia_principal_text'] ?? null,
            $m['banco_transferencia_text'] ?? null,
            $m['banco_text'] ?? null,
            $m['banco_nombre'] ?? null,
            $m['Nombre_Banco'] ?? null,
            $m['nombre_banco'] ?? null,
            $m['cuenta_banco'] ?? null,
            $m['Cuenta_Banco'] ?? null,
        ];

        foreach ($candidatos as $c) {
            $txt = trim((string)($c ?? ''));
            if ($txt !== '' && $lower($txt) !== 'n/a') return $txt;
        }

        return 'No aplica';
    };

    $parseVariantes = function($txt) use ($lower): array {
        $t = trim((string)$txt);
        if ($t === '' || $lower($t) === 'no aplica') return [];

        $arr = array_map('trim', explode(',', $t));
        $arr = array_values(array_filter($arr, fn($x) => $x !== ''));
        $arr = array_values(array_unique($arr));

        return $arr;
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
                                    {{ $substr2($empresaNombre, 2) }}
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
                                @php
                                    $productoNombre = (string)($p['producto'] ?? '—');
                                    $variantesArr = $parseVariantes($p['variante'] ?? 'No aplica');
                                @endphp
                                <tr>
                                    <td>{{ $productoNombre }}</td>

                                    <td>
                                        @if(empty($variantesArr))
                                            {{ (string)($p['variante'] ?? 'No aplica') ?: 'No aplica' }}
                                        @elseif(count($variantesArr) === 1)
                                            {{ $variantesArr[0] }}
                                        @else
                                            <button
                                                type="button"
                                                class="btn-variants"
                                                data-product="{{ e($productoNombre) }}"
                                                data-variants='@json($variantesArr)'
                                                onclick="openVariants(this)"
                                            >
                                                Variantes ({{ count($variantesArr) }})
                                            </button>
                                        @endif
                                    </td>

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
                                <th>Banco</th>
                                <th class="right">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($mediosPago as $m)
                                @php $mArr = (array)$m; @endphp
                                <tr>
                                    <td>{{ $resolveMedioPagoNombre($mArr) }}</td>
                                    <td>{{ $resolveBancoTexto($mArr) }}</td>
                                    <td class="right">{{ $money((float)($mArr['valor'] ?? 0)) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" style="text-align:center; color:#55657a; font-weight:900;">
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

            {{-- ✅ MINI MODAL (dentro del recibo / iframe) --}}
            <div id="variantsOverlay" class="vmo" aria-hidden="true">
                <div class="vmm" role="dialog" aria-modal="true" aria-labelledby="vmmTitle">
                    <div class="vmm-head">
                        <div class="vmm-title">
                            <div class="h1" id="vmmTitle">Variantes</div>
                            <div class="h2" id="vmmProduct">—</div>
                        </div>
                        <button type="button" class="vmm-close" onclick="closeVariants()">Cerrar</button>
                    </div>
                    <div class="vmm-body">
                        <div class="vmm-list" id="vmmList"></div>
                        <div class="vmm-count" id="vmmCount"></div>
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

function openVariants(btn){
    const overlay = document.getElementById('variantsOverlay');
    const productEl = document.getElementById('vmmProduct');
    const listEl = document.getElementById('vmmList');
    const countEl = document.getElementById('vmmCount');

    if (!overlay || !productEl || !listEl || !countEl) return;

    const product = (btn && btn.dataset && btn.dataset.product) ? btn.dataset.product : 'Producto';
    let variants = [];

    try {
        variants = JSON.parse((btn && btn.dataset && btn.dataset.variants) ? btn.dataset.variants : '[]');
        if (!Array.isArray(variants)) variants = [];
    } catch(e){
        variants = [];
    }

    productEl.textContent = product;
    listEl.innerHTML = '';

    variants.forEach((v) => {
        const div = document.createElement('div');
        div.className = 'vmm-item';
        div.textContent = String(v);
        listEl.appendChild(div);
    });

    countEl.textContent = variants.length ? (variants.length + ' variante(s)') : '';

    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');

    overlay.onclick = (e) => {
        if (e.target === overlay) closeVariants();
    };
}

function closeVariants(){
    const overlay = document.getElementById('variantsOverlay');
    if (!overlay) return;

    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden', 'true');
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeVariants();
});
</script>

</body>
</html>
