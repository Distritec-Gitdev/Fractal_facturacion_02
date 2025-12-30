<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><span class="negrita">POLÍTICAS DE ENTREGA DE PRODUCTOS</span></title>
    <style>
        @page { margin: 0; }

        /* ====== LAYOUT BASE ====== */
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #111;
            line-height: 1.5;                 /* legible y compacto */
            text-align: justify;               /* TODO el texto justificado */
            margin: 0;
            padding: 140px 55px 110px 55px;    /* +15px abajo para dar más “piso” a la firma */
            background: #fff;                  /* fondo blanco */
        }

        header {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 110px;
            background: #fff;                  /* fondo blanco */
            z-index: 100;                      /* por encima del contenido */
        }
        header img {
            width: 809px;
            max-height: 100px;                 /* evita que sobrepase la caja */
            height: auto;
            margin: 20px auto 0 auto;          /* BAJADO un poco más y centrado */
            display: block;
        }

        footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 80px;
            background: #fff;                  /* fondo blanco */
            z-index: 100;                      /* por encima del contenido */
        }
        footer img {
            width: 809px;
            max-height: 52px;                  /* evita que sobrepase la caja */
            height: auto;
            margin: 16px auto;
            display: block;
        }

        main {
            position: relative;
            background: #fff;                  /* fondo blanco */
        }

        /* ====== TIPOGRAFÍA / ESPACIADO ====== */
        .titulo {
            text-align: justify;               /* justificar título */
            font-weight: bold;
            font-size: 12px;                   /* destaca título principal */
            margin: 10px 0 12px 0;
            text-transform: uppercase;
        }
        .subtitulo {
            display: block;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 16px;
            margin-bottom: 6px;
            font-size: 11px;
        }
        /* Cualquier span subrayado usado como subtítulo se comporta como bloque */
        span[style*="text-decoration: underline;"] {
            display: block;
            margin-top: 16px;
            margin-bottom: 6px;
        }

        .negrita { font-weight: bold; }

        .linea {
            border-bottom: 1px solid #111;
            margin: 10px 0;
        }
        .datos-principales { margin-bottom: 8px; }

        /* ====== TABLAS / LISTAS ====== */
        .referencias-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .referencias-table td {
            padding: 1px 3px;
            font-size: 10.5px;
            vertical-align: top;
        }

        ul { margin: 0 0 0 16px; padding: 0; }
        ul li {
            margin-bottom: 3px;                /* aire entre bullets */
            font-size: 10.7px;
        }

        /* ====== BLOQUES DESTACADOS ====== */
        .nota {
            background: #fff;                  /* quitar azul: fondo totalmente blanco */
            padding: 10px 14px;
            border-radius: 4px;
            margin: 12px 0;
            font-size: 10.8px;
            page-break-inside: avoid;
        }

        .credito {
            margin-top: 14px;
            font-size: 11px;                   /* corregido el ; */
        }

        /* ====== FIRMA ====== */
        .firma-bloque {
    width: 100%;
    text-align: center;      /* centrado horizontal */
    font-size: 11px;
    margin-top: 60px;        /* BAJAR más el bloque (ajústalo según necesites) */
    margin-bottom: 8px;
    page-break-inside: avoid;
}

.firma {
    display: inline-block;   /* para centrar mejor dentro del bloque */
    text-align: center;
}


        /* ====== ESPACIADO ESPECÍFICO GARANTÍA ====== */
        .garantia-text { 
            margin: 8px 0 10px 0;              /* separa el párrafo de la lista */
        }
        .garantia-list { 
            margin-top: 8px;                    /* espacio sobre la lista */
            page-break-inside: avoid;
        }
        .garantia-list li { 
            margin-bottom: 6px;                 /* más aire entre bullets */
            line-height: 1.55;                  /* mejora legibilidad en PDF */
        }

        /* ====== ESPACIADO ESPECÍFICO AUTORIZACIÓN ====== */
        .autorizacion-title {
            display: block;
            margin-top: 18px;                   /* más espacio sobre el título */
            margin-bottom: 10px;                /* más espacio debajo del título */
            font-weight: bold;
            text-decoration: underline;
        }
        .autorizacion-text p {
            margin: 0 0 10px 0;                 /* separación entre párrafos */
            line-height: 1.55;
        }
    </style>
</head>
<body>
    <!-- Header (aparece en todas las páginas) -->
    <header>
        <img src="{{ public_path('imagenes_cabecera_pdf/img1.jpg') }}">
    </header>

    <!-- Footer (aparece en todas las páginas) -->
    <footer>
        <img src="{{ public_path('imagenes_cabecera_pdf/img2.jpg') }}">
    </footer>

    <!-- Contenido principal -->
    <main>

    @php
    $sedeOriginal = trim((string) ($sede ?? ''));
    $sedeMostrar  = $sedeOriginal;

    // Si empieza por "ASESOR EXTERNO" (ignorando mayúsculas/espacios), quita ese prefijo
    if ($sedeOriginal !== '') {
        $sinPrefijo = preg_replace('/^\s*ASESOR\s+EXTERNO\s*/i', '', $sedeOriginal);
        // Usa el valor sin prefijo solo si quedó algo
        if ($sinPrefijo !== null && $sinPrefijo !== '' && $sinPrefijo !== $sedeOriginal) {
            $sedeMostrar = $sinPrefijo;
        }
    }

    $equipoOriginal = trim((string) ($equipo ?? ''));
    $productoConvenio = trim((string) ($producto_convenio ?? ''));

    $equipoMostrar  = $equipoOriginal;
    $productoMostrar = $productoConvenio;

    if ($equipoMostrar === '' && $productoMostrar !== '') {
        $equipoMostrar = $productoMostrar;
    } elseif ($equipoMostrar !== '' && $productoMostrar !== '' && stripos($equipoMostrar, $productoMostrar) === false) {
        $equipoMostrar .= ' - ' . $productoMostrar;
    }


@endphp



        <div class="titulo">POLITICAS DE ENTREGA DE PRODUCTOS - DISTRITEC</div>

        <div class="datos-principales">
            FECHA: <span class="negrita">{{ $fechacompleta}}<</span> &nbsp;&nbsp; SEDE: <span class="negrita">{{ $sedeMostrar }}</span>
        </div>

        <span style="font-weight: bold; text-decoration: underline;">DATOS ADICIONALES DEL TITULAR DEL CRÉDITO</span>
        <div>
            No. de contacto alterno del Titular del crédito: <span class="negrita">{{ $tel_alternativo }}</span><br>
            Empresa donde Labora: <span class="negrita">{{ $empresa }}</span> &nbsp;&nbsp; No. De Contacto: <span class="negrita">{{ $empresa_contacto }}</span>
        </div>

        <span style="font-weight: bold; text-decoration: underline;">REFERENCIAS PERSONALES</span>
        <table class="referencias-table">
            <tr>
                <td>1. Nom. Contacto:</td>
                <td class="negrita">{{ $referencia1_nombre }}</td>
                <td>Celular:</td>
                <td class="negrita">{{ $referencia1_celular }}</td>
                <td>Parentesco:</td>
                <td class="negrita">{{ $referencia1_parentesco }}</td>
                <td>Tiempo de Conocerlo</td>
                <td class="negrita">{{ $referencia1_tiempo }}</td>
            </tr>
            <tr>
                <td>2. Nom. Contacto:</td>
                <td class="negrita">{{ $referencia2_nombre }}</td>
                <td>Celular:</td>
                <td class="negrita">{{ $referencia2_celular }}</td>
                <td>Parentesco:</td>
                <td class="negrita">{{ $referencia2_parentesco }}</td>
                <td>Tiempo de Conocerlo</td>
                <td class="negrita">{{ $referencia2_tiempo }}</td>
            </tr>
        </table>
         <div>Otras Referencias u Observaciones: <span class="negrita"> {{ $observacion_equipo }}</span></div>

        <div class="linea"></div>

        <span style="font-weight: bold; text-decoration: underline;">GARANTÍA DE PRODUCTO</span>
        <div class="garantia-text" style="text-align: justify;">
            Por este medio la empresa: DISTRIBUCIONES TECNOLÓGICAS DE COLOMBIA, otorga la presente garantía al señor(a): 
            <span class="negrita">{{ $nombre }}</span>, identificado con cédula de ciudadanía No. 
            <span class="negrita">{{ $cedula }}</span> por la compra del equipo 
            <span class="negrita">{{ $equipoMostrar }}</span> con IMEI <span class="negrita">{{ $imei }}</span>, realizada el día:  
            <span class="negrita">{{ $fecha_registro }}</span>. Esta garantía tiene una validez de 
            <span class="negrita">{{ $tiempo_garantia }}</span>. Transcurrido el periodo de garantía el cliente se comprende y 
            acepta que las reparaciones deberán ser costeadas por el mismo.
        </div>
        <ul class="garantia-list">
            <li>Cuando un tercero no autorizado repara el producto.</li>
            <li>Cuando se comprueba que el daño fue ocasionado de manera deliberada, intencional o por mal uso del equipo.</li>
            <li>El equipo tiene los sellos rotos, batería o equipo mojado, pantalla o táctil quebrado, o existe otro tipo de elementos invasivos.</li>
            <li>Daños ocasionados por virus o instalación de programas que irrumpan el funcionamiento del sistema operativo original del equipo.</li>
            <li>Instalar aplicaciones no permitidas por la(s) plataformas o financieras. (APK o Aplicaciones que están por fuera de Play Store).</li>
            <li>No atender las instrucciones de instalación, uso o mantenimiento indicadas en el manual de usuario del producto.</li>
            <li>El cliente no puede realizar restablecimiento de fabrica o formateo del equipo debido a que puede generar conflicto con la licencia de bloqueo del mismo. Si tiene alguna situación puntual dirigirse a la plataforma correspondiente para su debido soporte.</li>
        </ul>

        <div class="subtitulo">El procedimiento de la garantía deberá seguir unas etapas específicas:</div>
        <ul>
            <li>Se debe presentar el equipo en el área de servicio técnico con la caja y sus correspondientes accesorios, en buen estado y sin modificación alguna.</li>
            <li>El tiempo de respuesta de la Garantía estará dentro de los quince (15) días hábiles siguientes a la recepción de la reclamación. <span style="font-weight: bold; text-decoration: underline;">(Ley 1480 de 2011)</span></li>
            <li>La garantía no cubre la reparación de accesorios.</li>
            <li>Todo producto en servicio <span style="font-weight: bold;">POSVENTA</span> debe ser sometido a diagnóstico, inspección y evaluación técnica por el distribuidor y/o uno de los Centros De Servicios Autorizados.</li>
            <li>La recepción del equipo para garantía estará respaldada con la orden de servicio correspondiente, la cual debe ser presentada por el cliente al momento de la entrega del equipo.</li>
        </ul>

        <div class="credito" style="text-align: justify;">
            <span style="font-weight: bold; text-decoration: underline;">CONDICIONES DEL CRÉDITO ADQUIRIDO</span><br><br>
            Acepto que el equipo de Marca: <span class="negrita">{{ $marca }}</span>
            Modelo <span class="negrita">{{ trim((string) $modelo) !== '' ? $modelo : $equipoMostrar }}</span>,
            fue tomado por la plataforma de crédito <span class="negrita">{{ $plataforma }}</span>,
            con cuota inicial por valor de: <span class="negrita">${{ $cuota_inicial }}</span>.
            El crédito fue financiado a: No. <span class="negrita">{{ $numero_cuotas }}</span> de cuotas,
            <span class="negrita">{{ $d_pago }}</span>, el valor de la cuota aproximada es de:
            <span class="negrita">${{ $valor_cuotas }}</span>.

            @if(strtoupper(trim($plataforma ?? '')) === 'SUCUPO')
                El equipo con sistema operativo <span class="negrita">{{ $sistema_operativo }}</span>,
                se encuentra con un estado de batería del <span class="negrita">{{ $estado_bateria }}%</span>,
                y con un estado de la pantalla <span class="negrita">{{ $estado_pantalla }} de 10.</span>
            @endif
        </div>

        <!-- ====== AUTORIZACIÓN: Título con más aire y párrafos separados ====== -->
        <span class="autorizacion-title">AUTORIZACIÓN DE TRATAMIENTO PROTECCIÓN DE DATOS PERSONALES</span>
        <div class="autorizacion-text">
            <p>
                <span class="negrita">DISTRITEC</span>, actuará como Responsable del Tratamiento de datos personales de los cuales soy titular y que, conjunta o separadamente podrá recolectar, usar y tratar mis datos personales conforme la Política de Tratamiento de Datos Personales. He sido informado(a) de la(s) finalidad (es) de la recolección de los datos personales, la cual consiste en tratar mis datos personales y tomar mi huella y fotografía conforme a su Política de Tratamiento de Datos Personales para los fines relacionados con su objeto y en especial para fines legales, contractuales, misionales descritos en la Política de Tratamiento de Datos Personales de <span class="negrita">DISTRITEC</span>.
            </p>
            <p>
                Además, autorizo ser notificado por cualquiera de los medios de comunicación en cuanto a promociones, descuentos y demás estrategias en materia comercial que pudiera ser de mi interés o ser beneficiario(a). La información obtenida para el Tratamiento de mis datos personales la he suministrado de forma voluntaria y es verídica.
            </p>
        </div>

        <div style="margin-top: 12px; font-weight: bold; text-decoration: underline;">NOTA:</div>
        <div class="nota">
            <ul style="margin-bottom: 0;">
                <li>Se tendrán en cuenta todas las especificaciones contempladas en la normatividad vigente. <span style="font-weight: bold; text-decoration: underline;">Ley 1480 de 2011.</span></li>
                <li>En los casos venta de productos de contado o a crédito <span style="font-weight: bold;">NO SE HARÁ DEVOLUCIÓN DE DINERO Ó CAMBIO POR INCONFORMIDAD</span> después de retirarse el producto del punto de venta o sede <span style="font-weight: bold;">DISTRITEC</span>.</li>
                <li>Al momento de hacer entrega del producto en ventas de contado, a crédito o para garantía, el cliente verificará los accesorios, dado a que en <span class="font-weight: bold;">NINGUN</span> caso se responderá por el faltante después de retirarse del punto de venta o sede <span class="negrita">DISTRITEC</span>.</li>
            </ul>
        </div>

        <div style="margin-top: 12px; text-align: justify;">
            <span style="font-weight: bold; text-decoration: underline;">
                De acuerdo a lo anteriormente indicado, procedo a firmar el reporte de Referencias Personales, 
                Acuerdo de Garantía, Entrega y Protección de Datos Personales entre DISTRITEC y yo, donde se acuerda 
                dar cumplimiento a los tiempos y especificaciones acá indicadas.
            </span>
        </div>

        <div style="margin-top: 12px; font-weight: bold; text-decoration: underline;">FIRMA DIGITAL:</div>
        <div class="nota">
            <ul style="margin-bottom: 0;">
                <li>El presente <span class="negrita">Token</span> constituye la <span class="negrita">firma digital</span> del cliente, con plenos efectos jurídicos y probatorios.</li>
                <li>Dicho mecanismo se encuentra amparado bajo lo dispuesto en la <span class="negrita">Ley 527 de 1999</span>, que regula el uso de mensajes de datos, comercio electrónico y firmas digitales en Colombia.</li>
                <li>El cliente ha sido informado previamente sobre su alcance y validez, aceptando de manera expresa su utilización para todos los efectos legales correspondientes.</li>
            </ul>
        </div>

        <div class="firma-bloque">
            <div class="firma">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>{{ $firma }}</b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;                                                    <b>{{ $token }}</b><br>
                ________________________________   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;                                        _______________<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;FIRMA    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;                                                              FIRMA DIGITAL (TOKEN)
            </div>
            <div>
                Cédula: <span class="negrita">{{ $cedula }}</span> &nbsp;|&nbsp;
                Dirección: <span class="negrita">{{ $direccion }}</span> &nbsp;|&nbsp;
                Correo: <span class="negrita">{{ $correo }}</span>
            </div>
        </div>
    </main>
</body>
</html>