<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Compromiso Antifraude credismart</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      font-size: 10px;
      margin: 25px 35px;
    }
    .titulo {
      text-align: center;
      font-size: 13px;
      font-weight: bold;
      margin-bottom: 10px;
    }
    .cuadro-financiacion {
      border: 1.5px solid #000;
      padding: 6px;
      font-size: 10px;
      width: 210px;
      margin-left: 0px;
    }
    .tabla-cuotas-columna {
      width: 180px;
      border-collapse: collapse;
      font-size: 9px;
      border: 1.5px solid #000;
      margin-left: 30px;
      margin-top: 10px;
    }
    .tabla-cuotas-columna th,
    .tabla-cuotas-columna td {
      border: 1px solid #000;
      padding: 2px 4px;
      text-align: left;
      font-size: 10px;
    }
    .label-bold {
      font-weight: bold;
    }
    .bloque-2cuadros {
      position: relative;
      height: 70px;
      margin-top: 28px; /* Aumentado para separación extra */
      min-width: 350px;
    }
    .aceptacion-abs {
      position: absolute;
      left: 30px;
      top: 0;
      width: 160px;
      min-height: 120px;
      border: 2px solid #000;
      padding: 10px 10px;
      font-size: 11px;
      line-height: 1.3;
      background: #fff;
      box-sizing: border-box;
    }
    .cuota-abs {
      position: absolute;
      left: 244px;
      top: 0px;
      width: 110px;
      min-height: 120px;
      border: 1.5px solid #000;
      border-left: 4px solid #000;
      border-right: 4px solid #000;
      padding: 10px 10px;
      font-size: 10px;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      background: #fff;
      box-sizing: border-box;
    }
    .negrita { font-weight: bold; }
  </style>
</head>
<body>

  <!-- LOGO CENTRADO Y GRANDE -->
  <div style="width: 100%; text-align: center; margin-bottom: 12px;">
    <img src="{{ public_path('imagenes_cabecera_pdf/cavecera_crediminuto.png') }}"
         alt="Logo credismart"
         style="width: 150px !important; max-width: 99%; height: auto !important; margin-bottom: 10px;">
  </div>

  <div class="titulo">Compromiso Antifraude</div>

  <p><strong>SEÑORES</strong></p>
  <p><strong>credismart S.A.S.</strong></p>
  <p><strong>LA CIUDAD</strong></p>

  <p style="text-transform: uppercase;">
    Yo, <strong>{{ $nombre }}</strong>, identificado con cédula de ciudadanía NUM <strong>{{ $cedula }}</strong> de <strong>{{ $municipio }} - {{ $departamento }}</strong> y con domicilio en <strong>{{ $direccion }}</strong> de la ciudad de <strong>{{ $municipio_residencia }} - {{ $departamento_residencia }}</strong>, y con número celular <strong>{{ $tel }}</strong>, entiendo que soy el único responsable de la compra del equipo terminal móvil y lo estoy adquiriendo bajo mi única voluntad, responsabilidad y consentimiento.
  </p>

  <p>
    ENTIENDO QUE CONTRACTUALMENTE NO PUEDO VENDER EL CELULAR QUE CREDISMART ME ESTÁ FINANCIANDO HASTA QUE COMPLETE EL TOTAL DE LAS CUOTAS A PAGAR. ENTIENDO Y ACEPTO QUE LAS CONDICIONES DEL CRÉDITO EXIGEN QUE A MI NUEVO CELULAR FINANCIADO POR CREDISMART SE LE INSTALE UNA APLICACIÓN DE SEGURIDAD QUE PERMITE INHABILITAR EL CELULAR EN CASO DE NO PAGO. QUE UNA VEZ PAGADAS TODAS MIS CUOTAS EL CELULAR QUEDARÁ LIBRE Y SOLO AHÍ, PODRÉ DISPONER DE ÉL, UNA VEZ LA APLICACIÓN DE SEGURIDAD HAYA SIDO DESINSTALADA.
  </p>

  <p>
    ENTIENDO QUE CUALQUIER INTENTO DE MANIPULAR ESTA APLICACIÓN, ES PROHIBIDO CONTRACTUALMENTE Y PODRÍA SER CONSIDERADO UN DELITO. ENTIENDO QUE ESTE DELITO ESTÁ TIPIFICADO EN CÓDIGO PENAL COMO “VIOLACIÓN AL SISTEMA INFORMÁTICO” POR EL CUAL TENDRÉ QUE ACEPTAR LAS CONSECUENCIAS PENALES Y RESPONDER ANTE LA FISCALÍA POR ESTOS ACTOS DELICTIVOS.
  </p>

  <p>
    DOY CONSTANCIA QUE NO HE SIDO CONTACTADO POR UN TERCERO, NI ESTOY ACTUANDO COMO INTERMEDIARIO POR UN TERCERO, PARA TRAMITAR ESTE CRÉDITO, Y SOY CONSCIENTE Y RESPONSABLE DE QUE LAS CONSECUENCIAS DE INCURRIR EN ESTE ACTO ESTÁN CONSIDERADAS COMO “CONCIERTO PARA DELINQUIR”, TIPIFICADO EN EL CÓDIGO PENAL COLOMBIANO ARTÍCULO 340, Y QUE CUANDO VARIAS PERSONAS SE CONCIERTEN CON EL FIN DE COMETER DELITOS, CADA UNA DE ELLAS PODRÍA SER PENADA, POR ESA SOLA CONDUCTA, CON PRISIÓN DE CUARENTA Y OCHO (48) A CIENTO OCHO (108) MESES.
  </p>

  <p>
    CONSECUENTEMENTE COMETIENDO TAMBIÉN EL DELITO DE “ESTAFA”; ARTÍCULO 248 DEL CÓDIGO PENAL, PARA LOS QUE, CON ÁNIMO DE LUCRO, UTILIZAN EL ENGAÑO PARA PRODUCIR ERROR EN OTRO (CREDISMART) INDUCIÉNDOLO A REALIZAR UN ACTO EN DISPOSICIÓN EN PERJUICIO PROPIO O AJENO. LA PENA PREVISTA PARA EL DELITO DE ESTAFA OSCILA ENTRE SEIS (6) MESES Y TREINTA Y SEIS MESES (36) DE PRISIÓN.
  </p>

  <!-- Tabla con datos y cuotas -->
  <table style="width: 100%; table-layout: fixed; margin-top: 10px;">
    <tr>
      <td style="width: 60%; vertical-align: top;">
        <!-- Datos personales con más separación abajo -->
        <p style="margin-bottom: 16px;">
          NOMBRE: <strong>{{ $nombre }}</strong><br>
          CÉDULA: <strong>{{ $cedula }}</strong><br>
          DIRECCIÓN: <strong>{{ $direccion }}</strong><br>
          CORREO ELECTRÓNICO: <strong>{{ $correo }}</strong><br>
          TELÉFONO DE REFERENCIA 1: <strong>{{ $referencia1_celular }}</strong><br>
          NOMBRE DE REFERENCIA: <strong>{{ $referencia1_nombre }}</strong><br>
          TELÉFONO DE REFERENCIA 2: <strong>{{ $referencia2_celular }}</strong><br>
          NOMBRE DE REFERENCIA: <strong>{{ $referencia2_nombre }}</strong>
        </p>

        <!-- Firma con margen superior y entre firma/cuadros -->
        <div style="margin-top: 28px; margin-bottom: 32px;">
          <div style="width: 180px;"><strong>{{ $nombre }}</strong>
            <div style="border-top: 1px solid black; width: 100%; margin: 15px 0;"></div>
            <div class="label-bold">FIRMA</div> 
            <p></p>
            <div style="margin-top: 25px;">
              <strong>{{ $token }}</strong>
              <div style=" width: 100%; margin: 5px 0;"></div>
              <div class="label-bold">TOKEN</div>
            </div>
            <div style="width: 100px; height: 120px; border: 1.5px solid black; margin-left: 115px;"></div>
          </div>
        </div>

        <!-- Cuadros de aceptación/cuotas, más separados del bloque anterior -->
        <div class="bloque-2cuadros" style="margin-top: 30px;">
          <div class="aceptacion-abs">
            <p style="margin: 0;">
              Entiendo y acepto que las cuotas de mi crédito las conozco y fueron informadas tal cómo se especifica a continuación:
            </p>
            <p style="margin-top: 4px; font-size: 12px;">
              <strong>
                Valor de la cuota: 
                <span style="display:inline-block; border-bottom:1px solid #000; min-width:120px; text-align:center;">
                  {{ $valor_cuotas }}
                </span>
              </strong>
            </p>
          </div>
          <div class="cuota-abs">
            <div><strong>Cuotas de Pago</strong></div>
            <p></p>
            <div style="margin-top: 2px; font-size: 8px; text-align: center;"><strong>{{ $d_pago }}</strong></div>
          </div>
        </div>
      </td>
      <p></p>
      <p></p>

      <td style="width: 40%; vertical-align: top;">
        <p></p>
        <p></p>
        <div class="cuadro-financiacion">
          <strong>CUOTA DE FINANCIAMIENTO</strong><br>
          <p></p>
          VALOR: ___________________<br>
          <p></p>
          MEDIO DE PAGO: _______________
        </div>
        <p></p>
        <p></p>
        <p></p>
        <p></p>
        <p></p>

        <table class="tabla-cuotas-columna">
          <thead>
            <tr><th>Fecha de las cuotas</th></tr>
          </thead>
          <tbody>
            @for ($i = 1; $i <= 16; $i++)
              <tr>
                <td>{{ $i }} Día_mes_año: 20</td>
              </tr>
            @endfor
          </tbody>
        </table>
      </td>
    </tr>
  </table>

</body>
</html>