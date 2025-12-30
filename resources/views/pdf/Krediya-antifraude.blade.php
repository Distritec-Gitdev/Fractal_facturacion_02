<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CARTA ANTIFRAUDE</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            text-transform: uppercase;
            line-height: 1.1;
        }
        .contenedor {
            padding: 32px 80px;
        }
        .logo {
            width: 800px;
            margin: 0 auto 6px auto;
            display: block;
        }
        .titulo {
            text-align: center;
            font-weight: bold;
            margin: 6px 0 12px 0;
        }
        p {
            text-align: justify;
            margin: 14px 0 5px 0;
        }
        .compact-line {
            line-height: 0.6;
            margin-bottom: -4px;
        }
    </style>
</head>
<body>

<div class="contenedor">

    <!-- LOGO -->
    <div style="text-align: center;">
        <img src="{{ public_path('imagenes_cabecera_pdf/cavecera_crediya.jpg') }}" alt="KREDIYA" class="logo">
    </div>

    <!-- TÍTULO -->
    <p class="titulo">CARTA ANTIFRAUDE</p>

    <p><strong>SEÑORES DE</strong><br><strong>KREDIYA S.A.S</strong></p>

    <p>YO, <strong>{{ $nombre }}</strong>, IDENTIFICADO CON CÉDULA DE CIUDADANÍA NUM <strong>{{ $cedula }}</strong> DE <strong> {{ $municipio }}-{{ $departamento }}</strong> Y CON DOMICILIO EN (DIRECCIÓN EXACTA) <strong>{{ $direccion }}</strong> DE LA CIUDAD DE <strong>{{ $municipio_residencia }}-{{ $departamento_residencia }}</strong>, Y CON NÚMERO CELULAR <strong>{{ $tel }}</strong>, ENTIENDO QUE SOY EL ÚNICO RESPONSABLE DE LA COMPRA DEL EQUIPO TERMINAL MÓVIL Y LO ESTOY ADQUIRIENDO BAJO MI ÚNICA VOLUNTAD, RESPONSABILIDAD Y CONSENTIMIENTO.</p>

    <p>ENTIENDO QUE CONTRACTUALMENTE NO PUEDO VENDER EL CELULAR QUE KREDIYA SAS ME ESTÁ FINANCIANDO HASTA QUE COMPLETE EL TOTAL DE LAS CUOTAS A PAGAR. ENTIENDO Y ACEPTO QUE LAS CONDICIONES DEL CRÉDITO EXIGEN QUE A MI NUEVO CELULAR FINANCIADO POR KREDIYA COLOMBIA SE LE INSTALE UNA APLICACIÓN DE SEGURIDAD QUE PERMITE INHABILITAR EL CELULAR EN CASO DE NO PAGO. QUE UNA VEZ PAGADAS TODAS MIS CUOTAS EL CELULAR QUEDARÁ LIBRE Y SOLO AHÍ, PODRÉ DISPONER DE ÉL, UNA VEZ LA APLICACIÓN DE SEGURIDAD HAYA SIDO DESINSTALADA.</p>

    <p>ENTIENDO QUE CUALQUIER INTENTO DE MANIPULAR ESTA APLICACIÓN, ES PROHIBIDO CONTRACTUALMENTE Y PODRÍA SER CONSIDERADO UN DELITO. ENTIENDO QUE ESTE DELITO ESTÁ TIPIFICADO EN CÓDIGO PENAL COMO "VIOLACIÓN AL SISTEMA INFORMÁTICO" POR EL CUAL TENDRÉ QUE ACEPTAR LAS CONSECUENCIAS PENALES Y RESPONDER ANTE LA FISCALÍA POR ESTOS ACTOS DELICTIVOS.</p>

    <p style="margin-bottom:2px;">DOY CONSTANCIA QUE NO HE SIDO CONTACTADO POR UN TERCERO, NI ESTOY ACTUANDO COMO INTERMEDIARIO POR UN TERCERO, PARA TRAMITAR ESTE CRÉDITO, Y SOY CONSCIENTE Y RESPONSABLE DE QUE LAS CONSECUENCIAS DE INCURRIR EN ESTE ACTO ESTÁN CONSIDERADAS COMO <strong>"CONCIERTO PARA DELINQUIR"</strong>, TIPIFICADO EN EL <strong>CÓDIGO PENAL COLOMBIANO ARTÍCULO 340</strong>, Y QUE CUANDO VARIAS PERSONAS SE CONCIERTEN CON EL FIN DE COMETER DELITOS, CADA UNA DE ELLAS PODRÍA SER PENADA, POR ESA SOLA CONDUCTA, <strong>CON PRISIÓN DE CUARENTA Y OCHO (48) A CIENTO OCHO (108) MESES</strong>.</p>
    <p style="margin-top:2px;">CONSECUENTEMENTE COMETIENDO TAMBIÉN EL DELITO DE <strong>"ESTAFA"</strong>; <strong>ARTÍCULO 248 DEL CÓDIGO PENAL</strong>, PARA LOS QUE, CON ÁNIMO DE LUCRO, UTILIZAN EL ENGAÑO PARA PRODUCIR ERROR EN OTRO (KREDIYA COLOMBIA S.A.S.), INDUCIÉNDOLO A REALIZAR UN ACTO EN DISPOSICIÓN EN PERJUICIO PROPIO O AJENO. LA PENA PREVISTA PARA EL DELITO DE ESTAFA OSCILA ENTRE <strong>SEIS (6) MESES Y TREINTA Y SEIS (36) MESES DE PRISIÓN</strong>.</p>

    <!-- BLOQUE FIRMAS + RECUADRO SUBIDO -->
    <div style="margin-top: 8px; display: flex; align-items: flex-start;">

        <!-- TEXTO -->
        <div style="flex: 1; margin-top: 80px;">
            <p class="compact-line">NOMBRE:<strong>{{ $nombre }}</strong></p>
            <p class="compact-line">CÉDULA:<strong>{{ $cedula }}</strong></p>
            <p class="compact-line">DIRECCIÓN:<strong>{{ $direccion }} - {{ $municipio_residencia }} {{ $departamento_residencia }}</strong></p>
            <p><strong>ATENTAMENTE</strong></p>
            <p style="margin-bottom: 2px;"><strong><em>{{ $nombre }}</em></strong><br>FIRMA</p>
            <p style="margin-bottom: 2px;"><strong><em>{{ $token }}</em></strong><br>token</p>
        </div>

        <!-- RECUADRO HUELLA EN ESQUINA INFERIOR DERECHA -->
        <div style="position: fixed; bottom: 250px; right: 150px; text-align: center; width: 130px; z-index: 10;">
            <div style="border: 2px solid black; width: 120px; height: 170px; margin-bottom: 5px;"></div>
            <div style="font-weight: bold;">ÍNDICE DERECHO</div>
        </div>

    </div>
   
</div>

</body>
</html>