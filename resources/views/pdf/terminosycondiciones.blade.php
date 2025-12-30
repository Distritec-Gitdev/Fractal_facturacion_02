<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><span class="negrita">POLÍTICAS DE ENTREGA DE PRODUCTOS</span></title>
    <style>
        @page { margin: 0; }

        /* (Opcional) Incrustar Arial real para PDF: coloca los .ttf en public/fonts/arial/ */
        @font-face {
            font-family: 'Arial';
            src: url('{{ public_path('fonts/arial/arial.ttf') }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Arial';
            src: url('{{ public_path('fonts/arial/arialbd.ttf') }}') format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'Arial';
            src: url('{{ public_path('fonts/arial/ariali.ttf') }}') format('truetype');
            font-weight: normal;
            font-style: italic;
        }
        @font-face {
            font-family: 'Arial';
            src: url('{{ public_path('fonts/arial/arialbi.ttf') }}') format('truetype');
            font-weight: bold;
            font-style: italic;
        }

        /* Fuerza Arial en todo el documento */
        *, *::before, *::after {
            font-family: 'Arial', Helvetica, sans-serif !important;
        }

        /* ====== LAYOUT BASE ====== */
        body {
            font-size: 11px;
            color: #111;
            line-height: 1.45;                 /* más aire */
            text-align: justify;
            margin: 0;
            padding: 130px 52px 100px 52px;    /* +4–7px de aire en cada lado */
            background: #fff;
        }

        header {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 110px;                     /* +5px */
            background: #fff;
            z-index: 100;
            border-bottom: 1px solid #e6e6e6;
        }
        header img {
            width: 809px;
            max-height: 98px;                  /* +3px */
            height: auto;
            margin: 12px auto 0 auto;          /* +2px */
            display: block;
        }

        footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 74px;                      /* +4px */
            background: #fff;
            z-index: 100;
            border-top: 1px solid #e6e6e6;
        }
        footer img {
            width: 809px;
            max-height: 50px;                  /* +2px */
            height: auto;
            margin: 10px auto;                 /* +2px */
            display: block;
        }

        main { position: relative; background: #fff; }

        /* ====== TIPOGRAFÍA / ESPACIADO ====== */
        .titulo {
            text-align: justify;
            font-weight: bold;
            font-size: 12px;
            margin: 10px 0 12px 0;
            text-transform: uppercase;
            letter-spacing: .2px;
        }
        .subtitulo {
            display: block;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 14px;
            margin-bottom: 8px;
            font-size: 11px;
            letter-spacing: .2px;
        }
        span[style*="text-decoration: underline;"] {
            display: block;
            margin-top: 14px;
            margin-bottom: 8px;
        }

        .negrita { font-weight: bold; }

        .linea {
            border-bottom: 1px solid #111;
            margin: 10px 0;                    /* separa bien */
        }
        .datos-principales { margin-bottom: 8px; }

        /* ====== TABLAS / LISTAS ====== */
        .referencias-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;               /* +2px */
        }
        .referencias-table td {
            padding: 2px 4px;                  /* +1px en cada lado */
            font-size: 10.6px;
            vertical-align: top;
        }

        ul { margin: 0 0 0 18px; padding: 0; } /* +4px de indentación */
        ul li {
            margin-bottom: 5px;                /* +2px */
            font-size: 10.8px;
        }

        /* ====== BLOQUES DESTACADOS ====== */
        .nota {
            background: #fff;
            padding: 12px 16px;                /* +4px */
            border-radius: 6px;                /* +2px */
            margin: 14px 0;                    /* +2px arriba/abajo */
            font-size: 10.8px;
            page-break-inside: avoid;
            border: 1px solid #efefef;
        }

        .credito {
            margin-top: 14px;
            font-size: 11px;
        }

        /* ====== FIRMA ====== */
        .firma-bloque {
            width: 100%;
            text-align: center;
            font-size: 11px;
            margin-top: 40px;                  /* +12px para que no “choque” */
            margin-bottom: 10px;               /* +4px */
            page-break-inside: avoid;
        }
        .firma {
            display: inline-block;
            text-align: center;
        }

        /* ====== GARANTÍA ====== */
        .garantia-text {
            margin: 10px 0 12px 0;             /* +2px */
        }
        .garantia-list {
            margin-top: 10px;                  /* +2px */
            page-break-inside: avoid;
        }
        .garantia-list li {
            margin-bottom: 7px;                /* +1px */
            line-height: 1.5;                  /* +0.05 */
        }

        /* ====== AUTORIZACIÓN ====== */
        .autorizacion-title {
            display: block;
            margin-top: 18px;
            margin-bottom: 12px;               /* +2px */
            font-weight: bold;
            text-decoration: underline;
            letter-spacing: .2px;
        }
        .autorizacion-text p {
            margin: 0 0 10px 0;                /* vuelve a 10px */
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <!-- Header (aparece en todas las páginas) -->
    <header>
        <img src="{{ public_path('imagenes_cabecera_pdf/img1.jpg') }}">
    </header>

    <h1 style="text-align: center; margin: 12px 0 14px 0; letter-spacing:.2px;">Términos y Condiciones</h1>

    <!-- Footer (aparece en todas las páginas) -->
    <footer>
        <img src="{{ public_path('imagenes_cabecera_pdf/img2.jpg') }}">
    </footer>

    <!-- Contenido principal -->
    <main>
        <h3 style="color: #fff; margin-bottom: 22px; text-align: center; letter-spacing:.2px;">Términos y Condiciones</h3>

        <div style="margin-bottom: 26px; height: 60vh; overflow-y: auto; padding-right: 18px;">
            <p style="margin-bottom: 16px;">
                <strong>Distribuciones Tecnológicas de Colombia S.A.S. – Distritec</strong><br>
                NIT 901.042.503
            </p>

            <p style="margin-bottom: 16px;">
                "Distritec informa que los datos personales suministrados serán tratados conforme a nuestra Política de Tratamiento de Datos Personales disponible en www.distritec.co. El titular podrá ejercer sus derechos de acceso, rectificación, cancelación y oposición a través del correo habeasdata@distritec.co."
            </p>

            <div style="margin: 26px 0; padding: 16px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;">
                <h4 style="color: #111; margin-bottom: 14px; text-align: center; text-transform: uppercase; letter-spacing:.2px;">
                    AUTORIZACIÓN DE TRATAMIENTO DE DATOS PERSONALES
                </h4>
                
                <p style="margin-bottom: 14px;">
                    Yo, <span style="border-bottom: 1px solid #666; padding: 0 3px;">{{ $nombre ?? '' }}</span>, 
                    identificado con cédula de ciudadanía No. <span style="border-bottom: 1px solid #666; padding: 0 3px;">{{ $cedula ?? '' }}</span>, 
                    en calidad de titular de los datos personales, autorizo de manera previa, expresa e informada a 
                    Distribuciones Tecnológicas de Colombia S.A.S. – Distritec, NIT 901.042.503, para recolectar, 
                    almacenar, usar, circular, suprimir y en general dar tratamiento a mis datos personales conforme 
                    a la Política de Tratamiento de Datos Personales publicada por la compañía.
                </p>

                <div style="margin-top: 14px;">
                    <p style="margin-bottom: 8px;">Firma: {{$firma}}</p>
                    <p style="margin-bottom: 8px;">Nombre: {{ $nombre ?? '' }} </p>
                    <p style="margin-bottom: 8px;">Token:{{ $token ?? '' }}</p>
                    <p style="margin-bottom: 0;">CC: {{ $cedula ?? '' }}</p>
                </div>
            </div>

            <div style="margin: 26px 0; padding: 16px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;">
                <h4 style="color: #111; margin-bottom: 14px; text-align: center; text-transform: uppercase; letter-spacing:.2px;">
                    CONTRATO DE FIRMA ELECTRÓNICA MEDIANTE TOKEN
                </h4>
                
                <p style="margin-bottom: 14px;">
                    Entre los suscritos, de una parte DISTRIBUCIONES TECNOLÓGICAS DE COLOMBIA S.A.S. – DISTRITEC, identificada con NIT 901.042.503, quien en adelante se denominará DISTRITEC, y de otra parte el CLIENTE, persona natural identificada con su respectivo documento de identidad, quien en adelante se denominará EL CLIENTE, hemos convenido en celebrar el presente CONTRATO DE FIRMA ELECTRÓNICA MEDIANTE TOKEN, el cual se regirá por las siguientes CLÁUSULAS:
                </p>

                <h5 style="color: #111; margin: 14px 0 8px; text-transform: uppercase;">1. OBJETO</h5>
                <p style="margin-bottom: 14px;">El presente contrato tiene por objeto regular el uso de la firma electrónica mediante token implementada por DISTRITEC para la suscripción de contratos de crédito de equipos celulares, documentación de garantía, cartas antifraude y demás documentos contractuales relacionados.</p>

                <h5 style="color: #111; margin: 14px 0 8px; text-transform: uppercase;">2. MARCO LEGAL</h5>
                <p style="margin-bottom: 14px;">Este contrato se encuentra amparado por: Ley 527 de 1999, Decreto 1747 de 2000, Decreto 2364 de 2012, Ley 1581 de 2012, Decreto 1377 de 2013 y las disposiciones de la Superintendencia de Industria y Comercio y la Superintendencia Financiera de Colombia en materia de comercio electrónico, protección de datos y contratos.</p>

                <h5 style="color: #111; margin: 14px 0 8px; text-transform: uppercase;">3. DEFINICIONES</h5>
                <ul style="margin-bottom: 14px; padding-left: 22px;">
                    <li style="margin-bottom: 8px;">Firma Electrónica: Conjunto de métodos técnicos que permiten identificar al firmante y garantizar la integridad del documento.</li>
                    <li style="margin-bottom: 8px;">Token: Código único, temporal e intransferible enviado al CLIENTE por DISTRITEC a través de canales autorizados.</li>
                    <li style="margin-bottom: 8px;">CLIENTE: Persona natural que acepta los términos y condiciones de este contrato.</li>
                </ul>

                <h5 style="color: #111; margin: 14px 0 8px; text-transform: uppercase;">4. ACEPTACIÓN DE LA FIRMA ELECTRÓNICA</h5>
                <p style="margin-bottom: 14px;">EL CLIENTE acepta que el ingreso del token constituye manifestación inequívoca de su consentimiento, con la misma validez legal que la firma manuscrita, conforme a lo previsto en la Ley 527 de 1999.</p>

                <h5 style="color: #111; margin: 14px 0 8px; text-transform: uppercase;">5. OBLIGACIONES DE DISTRITEC</h5>
                <ul style="margin-bottom: 14px; padding-left: 22px;">
                    <li style="margin-bottom: 8px;">Generar tokens de un solo uso, únicos y temporales.</li>
                    <li style="margin-bottom: 8px;">Implementar protocolos de seguridad que garanticen autenticidad, confidencialidad e integridad.</li>
                    <li style="margin-bottom: 8px;">Conservar los documentos firmados y registros de trazabilidad.</li>
                    <li style="margin-bottom: 8px;">Poner a disposición del CLIENTE copia de los documentos suscritos.</li>
                </ul>

                <h5 style="color: #111; margin: 14px 0 8px; text-transform: uppercase;">6. OBLIGACIONES DEL CLIENTE</h5>
                <ul style="margin-bottom: 14px; padding-left: 22px;">
                    <li style="margin-bottom: 8px;">Custodiar debidamente sus medios de autenticación.</li>
                    <li style="margin-bottom: 8px;">Suministrar información verídica y actualizada.</li>
                    <li style="margin-bottom: 8px;">Reconocer como válidas todas las operaciones efectuadas mediante el token enviado a sus canales autorizados.</li>
                    <li style="margin-bottom: 8px;">Responder por fraudes o negligencia en la custodia de sus dispositivos.</li>
                </ul>

                <h5 style="color: #111; margin: 14px 0 8px; text-transform: uppercase;">7. PROTECCIÓN DE DATOS PERSONALES</h5>
                <p style="margin-bottom: 14px;">DISTRITEC dará cumplimiento a la Ley 1581 de 2012 en el tratamiento de los datos personales del CLIENTE, quien podrá ejercer los derechos de acceso, rectificación, cancelación y oposición a través de los canales dispuestos por la compañía.</p>

                <h5 style="color: #111; margin: 14px 0 8px; text-transform: uppercase;">8. CONSERVACIÓN DE LA EVIDENCIA</h5>
                <p style="margin-bottom: 14px;">DISTRITEC conservará en medios electrónicos seguros: el documento íntegro firmado, las evidencias de autenticación del token y la constancia de aceptación del CLIENTE, los cuales tendrán plena validez probatoria.</p>

                <h5 style="color: #111; margin: 14px 0 8px; text-transform: uppercase;">9. LIMITACIÓN DE RESPONSABILIDAD</h5>
                <p style="margin-bottom: 14px;">DISTRITEC no será responsable por fraudes ocasionados por negligencia del CLIENTE, pérdida de dispositivos o uso indebido de los canales autorizados.</p>

                <h5 style="color: #111; margin: 14px 0 8px; text-transform: uppercase;">10. VALIDEZ JURÍDICA Y PRUEBA</h5>
                <p style="margin-bottom: 14px;">El CLIENTE reconoce que los documentos firmados electrónicamente mediante token tienen plena validez y eficacia probatoria en cualquier proceso judicial o administrativo.</p>

                <h5 style="color: #111; margin: 14px 0 8px; text-transform: uppercase;">11. JURISDICCIÓN Y LEY APLICABLE</h5>
                <p style="margin-bottom: 14px;">El presente contrato se regirá por la legislación colombiana. Las diferencias se someterán a la jurisdicción ordinaria de los jueces de la República de Colombia.</p>

                <h5 style="color: #111; margin: 14px 0 8px; text-transform: uppercase;">12. ACEPTACIÓN</h5>
                <p style="margin-bottom: 14px;">EL CLIENTE declara haber leído y comprendido íntegramente este contrato y acepta que el ingreso del token equivale a su firma electrónica, obligándose en todos sus efectos.</p>

                <div style="margin-top: 24px;">
                    <p style="margin-bottom: 14px;">FIRMAS</p>
                    <p style="margin-bottom: 8px;">Firma:{{ $firma ?? '' }}</p>
                    <p style="margin-bottom: 8px;">Token:{{ $token ?? '' }}</p>
                    <p style="margin-bottom: 8px;">CC: {{ $cedula ?? '' }}</p>

                    <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                        <div style="flex: 1; min-width: 200px;">
                            <p style="margin-bottom: 8px;">EL CLIENTE</p>
                            <p style="margin-bottom: 6px;"><strong>{{ $nombre ?? '' }}</strong></p>
                            <p style="margin-bottom: 6px;">Nombre:{{ $nombre ?? '' }}</p>
                            <p style="margin-bottom: 6px;">Token:{{ $token ?? '' }}</p>
                            <p style="margin-bottom: 0;">CC: {{ $cedula ?? '' }}</p>
                        </div>

                        <div style="flex: 1; min-width: 200px; text-align: left;">
                            <p style="margin-bottom: 8px;">REPRESENTANTE LEGAL DE DISTRITEC</p>
                            <img src="{{ public_path('imagenes_cabecera_pdf/firma_representante.jpg') }}" 
                                alt="Firma representante" 
                                style="max-width: 180px; height: auto; margin-bottom: 6px; display: block;">
                            <!-- Si quieres línea bajo la firma:
                            <span style="display:block; height:1px; background:#222; width:200px;"></span> -->
                        </div>
                    </div>
                </div>

                <p style="margin-top: 14px;">
                    Antes de continuar, por favor lea detenidamente los términos anteriores. Al aceptar, usted reconoce que ha leído y comprendido la información, y que está de acuerdo con las condiciones aquí descritas.
                </p>
            </div>

       
    </main>
</body>
</html>
