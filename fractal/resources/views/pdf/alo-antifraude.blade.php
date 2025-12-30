<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Carta Antifraude Aló Crédito</title>
  <style>
    /* Página y márgenes para DomPDF */
    @page { size: A4; margin: 16mm 14mm 16mm 14mm; }

    /* Texto general compacto para que quepa en 1 página */
    body{
      margin:0;                 /* usamos los márgenes de @page */
      font-size:10.5pt;
      color:#222;
      font-family:Arial, Helvetica, sans-serif;
      line-height:1.38;
      text-align:justify;
    }

    h2{ text-align:center; font-size:20px; margin:0 0 12px; }
    .seccion{ margin-bottom:12px; }
    .datos{ margin-bottom:8px; font-size:12.5px; }

    /* Fuerza negrilla del destinatario */
    .destinatario{ font-weight:700 !important; }

    /* Evitar cortes de página en bloques clave */
    .no-break, .seccion, .datos, .firmas, .firmas tr, .firmas td { page-break-inside: avoid; }

    /* ===== Encabezado: logo fijo arriba a la DERECHA ===== */
    .header{
      position: relative;
      height: 70px;            /* alto del área de encabezado */
      margin: 0 0 6px 0;       /* espacio con el contenido */
    }
    .header .logo-alo{
      position: absolute;
      right: 0;                /* pegado al borde derecho */
      top: 0;                  /* arriba */
      width: 200px;            /* ajusta el tamaño si quieres */
      height: auto;
      display: block;
    }

    /* ==== Bloque de firmas + huella a la derecha ==== */
    .firmas{
      width:100%;
      margin-top:18px;         /* compacto */
      border-collapse:collapse;
      table-layout:fixed;
    }
    .firmas td{ vertical-align:bottom; padding:0 6px; }

    /* Texto (nombre o token) por encima de la línea */
    .firmante{
      font-size:11px;
      font-weight:700;
      text-align:center;
      margin-bottom:4px;       /* acerca el texto a la línea */
    }
    .linea-firma{
      width:100%;
      height:0;                /* solo la línea */
      border-top:1px solid #000;
      margin-top:0;
    }
    .etiqueta{ font-size:11px; text-align:center; margin-top:5px; }

    /* Celda de huella pegada al borde derecho */
    .huella-cell{ width:24%; text-align:right; padding-right:0; }

    /* Recuadro de Huella (idéntico al ejemplo) */
    .huella-dactilar{
      width:36mm;              /* bloque compacto */
      display:inline-block;
      margin:0;
      text-align:center;
      line-height:1.15;
    }
    .huella-dactilar .caja{
      width:30mm; height:34mm;
      border:1px solid #99a;
      border-radius:4mm;
      margin:0 auto 5px;
    }
    .huella-dactilar .titulo{ font-weight:600; font-size:10.5px; }
    .huella-dactilar .dedo{ font-weight:700; font-size:9.5px; letter-spacing:.3px; }

    /* Marca de agua / Insignia inferior */
    .marca-agua{
      position: fixed; top: 0; left: 0;
      width: 100%; height: 80%;
      z-index: -1; opacity: 0.18;
    }
    .marca-agua img{
      width: 88%; height: 78%;
      object-fit: cover; display:block;
      margin-top:200px; margin-left:45px;
    }
    .insignia{
      position: fixed;
      bottom: -18px;
      width: 200px; height: 140px;
      margin-left:-20px;
    }
    .insignia img{
      width:100%; height:100%; display:block;
      margin-left:-90px; margin-bottom:-80px;
    }
  </style>
</head>
<body>

  <!-- Encabezado: logo arriba a la DERECHA -->
  <div class="header">
    <img class="logo-alo"
         src="{{ public_path('imagenes_cabecera_pdf/alo_logo.png') }}"
         alt="ALÓ Crédito">
  </div>

  <!-- Marca de agua -->
  <div class="marca-agua">
    <img src="{{ public_path('imagenes_cabecera_pdf/marca_agua_alo.jpg') }}" alt="Marca de agua">
  </div>

  <!-- Insignia inferior -->
  <div class="insignia">
    <img src="{{ public_path('imagenes_cabecera_pdf/footer_alo.png') }}" alt="Insignia inferior">
  </div>

  <!-- Contenido -->
  <div class="seccion datos destinatario no-break">
    SEÑORES<br>
    ALÓ CREDIT COLOMBIA S.A.S.<br>
    CIUDAD <b>{{ $municipio_residencia }}-{{ $departamento_residencia }}</b>
  </div>

  <div class="seccion">
    Yo, <b>{{ $nombre }}</b>, identificado con cédula de ciudadanía
    No. <b>{{ $cedula }}</b>, expedida en <b>{{ $municipio }}-{{ $departamento }}</b>, y con domicilio en la dirección exacta
    <b>{{ $direccion }}, {{ $municipio_residencia }}-{{ $departamento_residencia }}</b>, y con número de celular <b>{{ $tel }}</b>, entiendo que soy el
    único responsable de la compra del equipo terminal móvil y lo estoy adquiriendo bajo mi única
    voluntad, responsabilidad y consentimiento.
  </div>

  <div class="seccion">
    Entiendo que contractualmente no puedo vender el celular que ALÓ CREDIT COLOMBIA S.A.S. me
    está financiando hasta que complete el total de las cuotas por pagar. Entiendo y acepto que las
    condiciones del crédito exigen que a mi nuevo celular financiado por Aló Crédit Colombia S.A.S., se
    le instale una aplicación de seguridad que permite inhabilitar el celular en caso de no pago, que
    una vez pagadas todas mis cuotas el celular queda libre y solo ahí podré disponer del terminal,
    una vez la aplicación de seguridad haya sido instalada.
  </div>

  <div class="seccion">
    Entiendo que cualquier intento de manipular esta aplicación es <b>prohibido contractualmente</b> y podría
    ser considerado un <b>delito</b>, entiendo que este delito está tipificado en el <b>Código Penal</b> como
    <b>violación al sistema informático</b>, por el cual tendré que aceptar las consecuencias penales y
    responder ante la fiscalía general de la Nación, por estos actos delictivos.
  </div>

  <div class="seccion">
    En virtud de lo anterior, doy constancia de que no he sido contactado por ningún tercero ni estoy
    actuando como intermediario por tercero para tramitar este crédito, y soy consciente y responsable
    de que las consecuencias de incurrir en este acto están consideradas como, “<b>Concierto para
    delinquir</b>” delito tipificado en el <b>Código Penal Colombiano, Artículo 340</b>, y que cuando varias
    personas se concierten con el fin de cometer delitos, cada una de ellas podría ser penada, por esa
    sola conducta, con <b>prisión de cuarenta y ocho (48) a ciento ocho (108) meses</b>.
  </div>

  <div class="seccion">
    Consecuentemente cometiendo también el delito de <b>estafa, artículo 248 del Código Penal</b>, para
    los que con ánimo de lucro utilizan el engaño para producir error en otro (ALÓ CREDIT COLOMBIA
    S.A.S.) induciéndolo a realizar un acto en perjuicio propio o ajeno, la <b>pena prevista para el delito
    de estafa oscila entre seis (6) y treinta y seis (36) meses de prisión</b>.
  </div>

  <div class="seccion no-break">
    <img src="{{ public_path('imagenes_cabecera_pdf/checkbox_checked.png') }}"
         style="width:15px; height:15px; vertical-align:middle; margin-right:6px;">
    <b>He leído el paquete de crédito y he comprendido los términos y condiciones del mismo.</b>
  </div>

  <!-- ===== Bloque de firmas + huella (nombre por encima de la línea) ===== -->
  <table class="firmas no-break">
    <tr>
      <td style="width:38%;">
        <div class="firmante"><b>{{ $firma }}</b></div>
        <div class="linea-firma"></div>
        <div class="etiqueta">FIRMA</div>
      </td>

      <td style="width:38%;">
        <div class="firmante"><b>{{ $token }}</b></div>
        <div class="linea-firma"></div>
        <div class="etiqueta">FIRMA DIGITAL (TOKEN)</div>
      </td>

      <td class="huella-cell">
        <div class="huella-dactilar">
          <div class="caja"></div>
          <div class="titulo">Huella Dactilar</div>
          <div class="dedo">DEDO INDICE DERECHO</div>
        </div>
      </td>
    </tr>
  </table>

</body>
</html>