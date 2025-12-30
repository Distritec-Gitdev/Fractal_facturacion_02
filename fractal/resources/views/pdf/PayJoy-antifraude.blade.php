<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>CARTA ANTIFRAUDE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    @page { size: A4; margin: 18mm; }
    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 13px;
      margin: 0;
      padding: 0;
      background: #fff;
      color: #111;
    }
    .doc { width: 100%; min-height: 100vh; margin: 0; padding: 24px; background: #fff; }
    @media print { .doc { width: auto; min-height: auto; margin: 0; padding: 0; } }

    .titulo { text-align: center; font-weight: bold; font-size: 16px; margin-bottom: 20px; text-transform: uppercase; }
    .bloque { margin-bottom: 12px; text-align: justify; }
    .upper { text-transform: uppercase; }
    .negrita { font-weight: bold; }
    .encabezado span { display: block; }
    .datos { margin-top: 12px; }

    /* ===== FIRMA Y HUELLA (posición estable que ya funciona) ===== */
    .firmas{
      position: relative;          /* contenedor de referencia */
      margin-top: 40px;
      padding-right: 160px;        /* reserva espacio para la huella (140 + 20 separación) */
      min-height: 170px;           /* asegura altura para contener la huella */
    }
    .huella-area{
      position: absolute;
      top: 0;
      right: 0;
      width: 140px;                /* columna derecha fija */
      text-align: center;
    }
    .huella{
      width: 120px;
      height: 150px;
      border: 2px solid #000;
      margin: 0 auto 8px auto;
      box-sizing: border-box;
    }
    .indice-derecho{
      font-weight: bold;
      font-size: 12px;
      width: 120px;
      margin: 0 auto;
      text-align: center;
    }

    /* ===== DISEÑO EXACTO DEL BLOQUE DE FIRMA (como la imagen) ===== */
    .firma-area{ text-align: left; }
    .atentamente{
      margin: 0 0 26px 0;          /* espacio como en la referencia */
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: .2px;
    }
    .firma-nombre{
      margin: 0;                   /* nombre inmediatamente sobre “FIRMA” */
      font-style: italic;
      font-weight: bold;
      text-transform: uppercase;
    }
    .firma-label{
      margin: 2px 0 0 0;           /* muy pegado al nombre, como la imagen */
      font-weight: bold;
      text-transform: uppercase;
    }
  </style>
</head>
<body>
  <main class="doc">
    <h1 class="titulo">CARTA ANTIFRAUDE</h1>

    <div class="bloque encabezado upper">
      <span class="negrita">SEÑORES</span>
      <span class="negrita">ADELANTOS COLOMBIA S.A.S.</span>
      <span class="negrita">CIUDAD</span>
    </div>

    <p class="seccion upper">
      Yo, <strong>{{ $nombre }}</strong>,
      identificado con cédula de ciudadanía número <strong>{{ $cedula }}</strong> de <strong>{{ $municipio }} - {{ $departamento }}</strong>
      y con domicilio en (dirección exacta) <strong>{{ $direccion }}</strong> de la ciudad de <strong>{{ $municipio_residencia }} - {{ $departamento_residencia }}</strong>,
      y con número celular <strong>{{ $tel }}</strong>, entiendo que soy el único responsable de la compra del equipo terminal móvil
      y lo estoy adquiriendo bajo mi única voluntad, responsabilidad y consentimiento.
    </p>

    <p class="seccion upper">
      Entiendo que contractualmente no puedo vender el celular que Adelantos Colombia S.A.S. está financiando hasta que
      complete el total de las cuotas por pagar. Entiendo y acepto que las condiciones del crédito exigen que a mi nuevo
      celular financiado por Adelantos Colombia S.A.S. se le instale una aplicación de seguridad que permite inhabilitar
      el celular en caso de no pago. Una vez pagadas todas mis cuotas, el celular quedará libre y solo ahí podré disponer
      de él, una vez la aplicación de seguridad haya sido desinstalada.
    </p>

    <p class="seccion upper">
      Entiendo que cualquier intento de manipular esta aplicación es prohibido contractualmente y podría ser considerado un
      delito. Este delito está tipificado como “violación al sistema informático”, por el cual tendré que aceptar todas las consecuencias
      penales y responderé ante la Fiscalía por estos actos delictivos.
    </p>

    <p class="seccion upper">
      DOY CONSTANCIA QUE NO HE SIDO CONTACTADO POR UN TERCERO, NI ESTOY ACTUANDO COMO INTERMEDIARIO POR UN TERCERO, PARA TRAMITAR ESTE CRÉDITO, Y SOY CONSCIENTE Y RESPONSABLE DE QUE LAS CONSECUENCIAS DE INCURRIR EN ESTE ACTO ESTÁN CONSIDERADAS COMO <span style="font-weight:bold;">“CONCIERTO PARA DELINQUIR”</span>, TIPIFICADO EN <span style="font-weight:bold;">EL CÓDIGO PENAL COLOMBIANO ARTÍCULO 340</span>, Y QUE CUANDO VARIAS PERSONAS SE CONCIERTEN CON EL FIN DE COMETER DELITOS, CADA UNA DE ELLAS PODRÍA SER PENADA, POR ESA SOLA CONDUCTA, CON <span style="font-weight:bold;">PRISIÓN DE CUARENTA Y OCHO (48) A CIENTO OCHO (108) MESES</span>. CONSECUENTEMENTE COMETIENDO TAMBIÉN EL DELITO DE <span style="font-weight:bold;">“ESTAFA”; ARTÍCULO 248 DEL CÓDIGO PENAL</span>, PARA LOS QUE, CON ÁNIMO DE LUCRO, UTILIZAN EL ENGAÑO PARA PRODUCIR ERROR EN OTRO (ADELANTOS COLOMBIA S.A.S.) INDUCIÉNDOLO A REALIZAR UN ACTO EN DISPOSICIÓN EN PERJUICIO PROPIO O AJENO. LA PENA PREVISTA PARA EL DELITO DE ESTAFA OSCILA ENTRE <span style="font-weight:bold;">SEIS (6) MESES Y TREINTA Y SEIS MESES (36) DE PRISIÓN.</span>
    </p>

    <div class="bloque upper datos">
      NOMBRE: <span class="negrita">{{ $nombre }}</span><br />
      CÉDULA: <span class="negrita">{{ $cedula }}</span><br />
      DIRECCIÓN: <span class="negrita">{{ $direccion }} - {{ $municipio_residencia }} {{ $departamento_residencia }}</span>
    </div>

    <!-- ===== Bloque de firma con el diseño exacto ===== -->
    <div class="firmas">
      <div class="firma-area">
        <p class="atentamente">ATENTAMENTE,</p>
        <div class="firma-nombre">{{ $firma }}</div>
        <div class="firma-label">FIRMA</div>
      </div>

      <div class="firma-area" style="margin-top: 25px;">
        <div class="firma-token">{{ $token }}</div>
        <div class="firma-label">TOKEN</div>
      </div>

      <div class="huella-area">
        <div class="huella"></div>
        <div class="indice-derecho upper">INDICE DERECHO</div>
      </div>
    </div>
    <!-- ================================================ -->
  </main>
</body>
</html>