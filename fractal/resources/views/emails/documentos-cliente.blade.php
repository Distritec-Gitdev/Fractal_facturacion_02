<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Documentación de tu compra</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f5f7fb;">
  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="background:#f5f7fb;">
    <tr>
      <td align="center" style="padding:24px;">
        <table role="presentation" width="640" border="0" cellspacing="0" cellpadding="0" style="width:640px;max-width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 28px rgba(18,38,63,.12);">
          <tr>
            <td
              style="
                background:#0b1e2e;
                background-image: url('{{ $backgroundUrl ?? asset('imagenes_cabecera_pdf/Izquierda2.png') }}');
                background-size: cover;
                background-position: center;
                padding:28px;"
            >
              <img src="{{ $logoUrl ?? asset('imagenes_cabecera_pdf/logo.png') }}" alt="Distritec" style="height:42px;display:block;filter:drop-shadow(0 2px 4px rgba(0,0,0,.25));">
              <div class="header-text" style=" background:#0000004d;">
                <h1 style="margin:18px 0 0;color:#ffffff;font-weight:700;font-size:22px;font-family:Arial,Helvetica,sans-serif;">
                  ¡Bienvenido(a) a Distritec!
                </h1>
                <p style="margin:8px 0 0;color:#cfe6ff;font-size:14px;font-family:Arial,Helvetica,sans-serif;">
                  Hemos preparado tu documentación. Para abrir los archivos adjuntos, utiliza tu número de cédula como clave.
                </p>
              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:28px 24px 8px 24px;font-family:Arial,Helvetica,sans-serif;color:#14243a;">
              <p style="margin:0 0 10px 0;font-size:16px;">Hola <strong>{{ $clienteNombre ?? 'Cliente' }}</strong>,</p>
              <p style="margin:0 0 12px 0;line-height:1.55;font-size:15px;">
                Gracias por confiar en <strong>Distritec</strong>. Adjuntamos los documentos de tu proceso de compra:
              </p>
              <ul style="margin:8px 0 0 20px;padding:0;font-size:15px;line-height:1.5;">
                <li>Entrega de producto</li>
                <li>Carta Antifraude</li>
                <li>Términos y Condiciones</li>
              </ul>
              <div style="margin-top:14px;padding:12px 14px;background:#f0f6ff;border:1px solid #d9e8ff;border-radius:8px;color:#0b3a75;font-size:14px;">
                <strong>Para abrir los archivos adjuntos, utiliza tu número de cédula como clave.</strong> 
              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:8px 24px 4px 24px;font-family:Arial,Helvetica,sans-serif;color:#14243a;">
              <p style="margin:0 0 12px 0;line-height:1.55;font-size:15px;">
                Si necesitas ayuda o soporte técnico, con gusto estamos para acompañarte.
              </p>
              <a href="{{ $soporteLink ?? 'https://distritec.co' }}" target="_blank" style="display:inline-block;margin:8px 0 16px 0;padding:10px 16px;background:#0f7c1b;color:#fff;text-decoration:none;border-radius:8px;font-size:14px;">
                Ir a Soporte
              </a>
            </td>
          </tr>

          <tr>
            <td style="padding:16px 24px 24px 24px;">
              <hr style="border:none;height:1px;background:#eef2f7;margin:0 0 14px 0;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="font-family:Arial,Helvetica,sans-serif;color:#748091;font-size:13px;">
                    Síguenos:
                    <a href="{{ $instagramLink ?? 'https://instagram.com/distriteccolombia' }}" target="_blank" style="color:#1c2b90;text-decoration:none;margin-left:10px;">
                      <img src="{{ asset('imagenes_cabecera_pdf/instagram.png') }}" alt="Instagram" style="height:18px;vertical-align:middle;border:0;"> Instagram
                    </a>
                    <span style="margin:0 10px;">|</span>
                    <a href="{{ $whatsappLink ?? 'https://wa.me/573136200202' }}" target="_blank" style="color:#128C7E;text-decoration:none;">
                      <img src="{{ asset('imagenes_cabecera_pdf/whatsapp.png') }}" alt="WhatsApp" style="height:18px;vertical-align:middle;border:0;"> WhatsApp
                    </a>
                  </td>
                  <td align="right" style="font-family:Arial,Helvetica,sans-serif;color:#748091;font-size:12px;">
                    &copy; {{ date('Y') }} Distritec. Todos los derechos reservados.
                  </td>
                </tr>
              </table>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
