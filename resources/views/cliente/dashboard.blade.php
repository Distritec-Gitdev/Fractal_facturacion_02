
@extends('layouts.app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <style>
        .swiper {
            width: 100vw;
            height: 950px;
            min-height: 400px;
            display: flex;
            justify-content: center;
        }
                } else {.swiper-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .swiper-slide {
            display: flex !important;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            height: 100%;
            width: 800px !important;
            max-width: 95vw;
            transition: transform 0.5s;
        }
        .swiper-slide > div {
            width: 100%;
            max-width: 780px;
        }
        .swiper-button-next, .swiper-button-prev {
            color: #82cc0e;
        }
        .swiper .swiper-slide {
            background: transparent;
        }
    </style>

    <style>
  /* Overlay del loader */
  #page-loader {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,.85);
    backdrop-filter: blur(2px);
  }
  #page-loader[hidden] { display: none; }

  /* Spinner */
  .loader-ring {
    width: 64px; height: 64px;
    border: 4px solid #e5e7eb;
    border-top-color: #82cc0e; /* tu verde */
    border-radius: 50%;
    animation: spin .8s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  .loader-text {
    margin-top: 12px;
    font: 500 14px/1.3 system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
    color: #314155;
    text-align: center;
  }
</style>




@section('content')
<div class="container" style="max-width: 1800px; margin: 40px auto;">

<!-- Loader Global -->
<div id="page-loader" hidden aria-hidden="true" aria-busy="true">
  <div role="status" aria-live="polite" style="display:flex; flex-direction:column; align-items:center;">
    <div class="loader-ring"></div>
    <div class="loader-text">Procesando…</div>
  </div>
</div>

    <h2 class="mb-4">Documentos Del Cliente</h2>
    @if (session('email_ok'))
    <div class="alert alert-success">{{ session('email_ok') }}</div>
    @endif
    @if (session('email_error'))
    <div class="alert alert-danger">{{ session('email_error') }}</div>
    @endif

    <div class="alert alert-success" id="success-alert">Acceso validado correctamente.</div>
    <!-- ALERTA GLOBAL PARA TÉRMINOS -->
    <div id="globalAlert" style="display:none; background:#e53935; color:#fff; border-radius:8px; padding:0.7rem 1rem; text-align:center; font-size:1.05rem; max-width: 400px; margin: 0 auto; position: fixed; bottom: 24px; left: 0; right: 0; z-index: 99999; box-shadow: 0 2px 12px #e5393555;">
        Debes aceptar <b>Términos y Condiciones</b> para continuar.
    </div>
    <div id="successGlobalAlert" style="display:none; background:#e6f9e6; color:#155724; border-radius:8px; padding:0.7rem 1rem; text-align:center; font-size:1.05rem; max-width: 400px; margin: 24px auto 0 auto; position: fixed; top: 70px; left: 0; right: 0; z-index: 99999; box-shadow: 0 2px 12px #82cc0e33;">
        ¡Guardado correctamente!
    </div>
    <div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="text-center mt-2">Entrega De Producto</div>
        <div class="pdf-box">
            {{-- Pila de páginas del PDF (todas las páginas en un solo contenedor) --}}
            <div
                id="pdf-entrega"
                class="pdf-stack"
                data-pdf="{{ route('pdf.entrega-producto', $cliente->id_cliente) }}"
            ></div>
        </div>
    </div>

    @php $plataforma_normalizada = strtolower(trim($plataforma)); @endphp

    @if($plataforma_normalizada === 'crediminuto')
        <div class="col-12 col-lg-6">
            <div class="pdf-box">
                <div
                    id="pdf-crediminuto-antifraude"
                    class="pdf-stack"
                    data-pdf="{{ route('pdf.crediminuto-antifraude', $cliente->id_cliente) }}"
                ></div>
            </div>
            <div class="text-center mt-2">Carta Antifraude Crediminuto</div>
        </div>
    @elseif($plataforma_normalizada === 'krediya')
        <div class="col-12 col-lg-6">
            <div class="pdf-box">
                <div
                    id="pdf-krediya-antifraude"
                    class="pdf-stack"
                    data-pdf="{{ route('pdf.krediya-antifraude', $cliente->id_cliente) }}"
                ></div>
            </div>
            <div class="text-center mt-2">Carta Antifraude Krediya</div>
        </div>
    @elseif($plataforma_normalizada === 'payjoy')
        <div class="col-12 col-lg-6">
            <div class="pdf-box">
                <div
                    id="pdf-payjoy-antifraude"
                    class="pdf-stack"
                    data-pdf="{{ route('pdf.carta-antifraude', $cliente->id_cliente) }}"
                ></div>
            </div>
            <div class="text-center mt-2">Carta Antifraude PayJoy</div>
        </div>
    @elseif($plataforma_normalizada === 'alocredit')
        <div class="col-12 col-lg-6">
            <div class="pdf-box">
                <div
                    id="pdf-alo-antifraude"
                    class="pdf-stack"
                    data-pdf="{{ route('pdf.alo-antifraude', $cliente->id_cliente) }}"
                ></div>
            </div>
            <div class="text-center mt-2">Carta Antifraude Aló Crédito</div>
        </div>
    @endif
</div>

    <div class="text-center mb-4">
    <div style="display: flex; justify-content: center;">
        <button type="button" class="btn btn-success btn-lg" onclick="showModal('terminos')">Términos y Condiciones</button>
    </div>
</div>
<form method="POST" action="{{ route('finalizar.proceso') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
  
    <div class="text-center mt-4" style="margin-top: 2.5rem !important;">
        <button type="submit" id="btn-continuar" class="btn btn-primary btn-lg">Continuar</button>
    </div>
</form>

<!-- Modal -->
<div id="confirmModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#232323; color: #fff; padding:30px; border-radius:10px; max-width:600px; margin:auto; text-align:left;">
        <div id="modalAlert" style="display:none; background:#e53935; color:#fff; border-radius:8px; padding:0.7rem 1rem; margin-bottom:1rem; text-align:center; font-size:1.05rem;">
            Debes aceptar <b>Términos y Condiciones</b> y <b>Términos Comerciales</b> para continuar.
        </div>
        <div id="modalContent">
            <!-- El contenido se llenará dinámicamente -->
        </div>
        <div style="text-align:center; margin-top: 18px; display: flex; justify-content: center; gap: 18px;">
            <button id="btn-aceptar-terminos" onclick="modalAccept()" class="btn btn-success" style="min-width: 120px; font-size: 1.1rem;">Aceptar</button>
            <button onclick="modalDecline()" class="btn btn-danger" style="min-width: 120px; font-size: 1.1rem;">No aceptar</button>
        </div>
    </div>
</div>
<script>
/* ================== LOADER + GATEKEEPER SUBMIT ================== */
(function () {
  const el = document.getElementById('page-loader');
  const state = { count: 0, showTimer: null };

  function renderLoader() {
    if (state.count > 0) {
      if (state.showTimer == null) {
        state.showTimer = setTimeout(() => { el.hidden = false; }, 120);
      }
    } else {
      if (state.showTimer) clearTimeout(state.showTimer);
      state.showTimer = null;
      el.hidden = true;
    }
  }

  const AppLoader = {
    inc() { state.count++; renderLoader(); },
    dec() { state.count = Math.max(0, state.count - 1); renderLoader(); },
    show() { state.count++; renderLoader(); },
    hide() { state.count = 0; renderLoader(); },
    wrap(p) { AppLoader.inc(); return Promise.resolve(p).finally(() => AppLoader.dec()); },
  };

  window.AppLoader = AppLoader;

  // Muestra durante navegación real
  window.addEventListener('beforeunload', () => { AppLoader.show(); });

  // Interceptor global fetch para mostrar loader mientras hay requests
  const _fetch = window.fetch;
  window.fetch = function () {
    AppLoader.inc();
    return _fetch.apply(this, arguments)
      .finally(() => AppLoader.dec());
  };

  // ---------- Fuente de verdad: bandera TyC ----------
  // Usamos variable global + hidden (por si necesitas leerlo en backend)
  let terminosAceptados = false;
  window.__setTyC = function (v) {
    terminosAceptados = !!v;
    let h = document.getElementById('tyc_accepted');
    if (!h) {
      h = document.createElement('input');
      h.type = 'hidden';
      h.id = 'tyc_accepted';
      h.name = 'tyc_accepted';
      (document.querySelector('form[action="{{ route('finalizar.proceso') }}"]') || document.querySelector('form'))?.appendChild(h);
    }
    h.value = terminosAceptados ? '1' : '0';
  };
  window.__isTyCAccepted = () => terminosAceptados === true || (document.getElementById('tyc_accepted')?.value === '1');

  // Hookea el botón del modal para marcar aceptación y habilitar continuar
  document.addEventListener('click', function (ev) {
    const t = ev.target;
    if (t && t.id === 'btn-aceptar-terminos') {
      __setTyC(true);
      const btnContinuar = document.getElementById('btn-continuar');
      if (btnContinuar) btnContinuar.disabled = false;
    }
  });

  // ---------- Gatekeeper del submit (CAPTURE) ----------
  document.addEventListener('submit', function (e) {
    const form = e.target;
    const isFinalizar = form.matches('form[action="{{ route('finalizar.proceso') }}"]');

    if (isFinalizar) {
      const btnContinuar = document.getElementById('btn-continuar');
      const accepted = __isTyCAccepted() && (!btnContinuar || btnContinuar.disabled === false);

      if (!accepted) {
        // BLOQUEAMOS envío y detenemos cualquier otro handler
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

        // Apaga loader si alguien lo encendió
        AppLoader.hide();

        // Tu alerta
        showGlobalAlert && showGlobalAlert();
        return false;
      }

      // Si pasó validación -> ahora sí enciende loader y deja continuar
      AppLoader.show();
    } else {
      // Otros formularios de la página: loader normal
      AppLoader.show();
    }
  }, true); // CAPTURE

})();
</script>

<script>
// Guard en CAPTURA: si no aceptó TyC, cancelamos submit y apagamos loader
document.addEventListener('submit', function (e) {
  const form = e.target;
  if (!form || !form.matches('form[action="{{ route('finalizar.proceso') }}"]')) return;

  const btn = document.getElementById('btn-continuar');
  const accepted = btn && !btn.disabled; // tu fuente de verdad actual

  if (!accepted) {
    e.preventDefault();
    e.stopPropagation();
    if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

    // 🔑 apaga el loader si algún otro handler lo encendió
    if (window.AppLoader) window.AppLoader.hide();

    showGlobalAlert();
    return false;
  }
}, true); // <-- CAPTURE = true
</script>


<script>
/* ---------- Hooks con tu renderizado PDF existente ---------- */
/* Sustituye tu función renderPdfStack por esta versión envuelta */
async function renderPdfStack(container, url) {
  window.AppLoader.inc();
  try {
    const pdfjsLib = window['pdfjs-dist/build/pdf'] || window.pdfjsLib;
    const loadingTask = pdfjsLib.getDocument({ url, withCredentials: true });
    const pdf = await loadingTask.promise;

    container.innerHTML = '';

    const rawWidth = Math.round(container.clientWidth || window.innerWidth || 800);
    const clampedWidth = Math.min(Math.max(rawWidth, 320), 1400);
    const gutter = (clampedWidth < 480) ? 12 : (clampedWidth < 900 ? 16 : 24);

    const initialWidth = Math.max(320, clampedWidth - gutter * 2);
    container.dataset.initialWidth = String(initialWidth);
    container.dataset.gutter = String(gutter);

    const isDesktop  = initialWidth >= 900;
    const MAX_PIXELS = isDesktop ? 64_000_000 : 16_000_000;
    const dprRaw     = window.devicePixelRatio || 1;
    const boost      = isDesktop ? 2.5 : (initialWidth < 480 ? 1.5 : 1.0);
    const cap        = isDesktop ? (initialWidth >= 1200 ? 8 : 6) : 3;

    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
      const page = await pdf.getPage(pageNum);
      const base = page.getViewport({ scale: 1 });
      const cssScale = initialWidth / base.width;

      let renderDpr = Math.min(dprRaw * boost, cap);
      let renderViewport = page.getViewport({ scale: cssScale * renderDpr });

      let pixels = renderViewport.width * renderViewport.height;
      if (pixels > MAX_PIXELS) {
        const reduceFactor = Math.sqrt(pixels / MAX_PIXELS);
        renderDpr = Math.max(1, renderDpr / reduceFactor);
        renderViewport = page.getViewport({ scale: cssScale * renderDpr });
      }

      const pageWrapper = document.createElement('div');
      pageWrapper.className = 'pdf-page-wrapper';
      pageWrapper.style.width = '100%';
      pageWrapper.style.boxSizing = 'border-box';
      pageWrapper.style.paddingLeft = gutter + 'px';
      pageWrapper.style.paddingRight = gutter + 'px';
      pageWrapper.style.margin = '0 auto';
      pageWrapper.style.maxWidth = (initialWidth + gutter * 8) + 'px';

      const canvas = document.createElement('canvas');
      canvas.className = 'pdf-page-canvas';
      canvas.width  = Math.floor(renderViewport.width);
      canvas.height = Math.floor(renderViewport.height);
      canvas.style.width  = '100%';
      canvas.style.height = 'auto';
      canvas.style.display = 'block';
      if (isDesktop) canvas.style.imageRendering = 'auto';

      const ctx = canvas.getContext('2d', { alpha: false });
      ctx.imageSmoothingEnabled = false;

      pageWrapper.appendChild(canvas);
      container.appendChild(pageWrapper);

      await page.render({ canvasContext: ctx, viewport: renderViewport, intent: 'display' }).promise;
      page.cleanup?.();
    }
  } catch (err) {
    console.error('Error al renderizar PDF:', err);
    container.innerHTML = `
      <div class="pdf-error">
        No fue posible mostrar el documento.<br>
        <a href="${url}" target="_blank" rel="noopener">Abrir en una nueva pestaña</a>
      </div>`;
  } finally {
    window.AppLoader.dec();
  }
}

/* Si vuelves a crear esta función en otro bloque, aplica lo mismo */
function setupStableRerender(stack, url) {
  let lastWidth = parseInt(stack.dataset.initialWidth || '0', 10) || stack.clientWidth || 0;

  const rerender = () => {
    if (window.visualViewport && window.visualViewport.scale !== 1) return;
    const current = Math.round(stack.clientWidth || 0);
    if (Math.abs(current - lastWidth) > 160) {
      // Mostrar loader brevemente durante re-render
      window.AppLoader.wrap(renderPdfStack(stack, url));
      lastWidth = current;
    }
  };

  window.addEventListener('orientationchange', () => {
    window.AppLoader.show();
    setTimeout(() => { rerender(); window.AppLoader.dec(); }, 350);
  }, { passive: true });

  // Si quieres también en resize "grande", puedes descomentar:
  // let to;
  // window.addEventListener('resize', () => {
  //   clearTimeout(to);
  //   to = setTimeout(() => window.AppLoader.wrap(renderPdfStack(stack, url)), 250);
  // }, { passive: true });
}

/* Hook de tus inicializaciones existentes */
document.addEventListener('DOMContentLoaded', () => {
  const stacks = document.querySelectorAll('.pdf-stack[data-pdf]');
  stacks.forEach(stack => {
    const url = stack.getAttribute('data-pdf');
    stack.innerHTML = `
      <div class="pdf-page-wrapper" style="padding:12px;text-align:center;">
        <div style="color:#666;">Cargando documento…</div>
      </div>`;
    window.AppLoader.wrap(renderPdfStack(stack, url));
    setupStableRerender(stack, url);
  });
});
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
  const pdfjsLib = window['pdfjs-dist/build/pdf'] || window.pdfjsLib;
  pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

  // Gutter (margen interno) fijo que NO varía con el zoom
  function computeGutter(width) {
    if (width < 480) return 12;     // móvil
    if (width < 900) return 16;     // tablet
    return 24;                      // desktop
  }

  async function renderPdfStack(container, url) {
    try {
      const loadingTask = pdfjsLib.getDocument({ url, withCredentials: true });
      const pdf = await loadingTask.promise;

      container.innerHTML = '';

      // Ancho inicial del contenedor (ancla) y gutter fijo
      const rawWidth = Math.round(container.clientWidth || window.innerWidth || 800);
      const clampedWidth = Math.min(Math.max(rawWidth, 320), 1400); // permitimos algo más en desktop
      const gutter = computeGutter(clampedWidth);

      // Restamos el gutter a ambos lados para el ancho CSS de página
      const initialWidth = Math.max(320, clampedWidth - gutter * 2);
      container.dataset.initialWidth = String(initialWidth);
      container.dataset.gutter = String(gutter);

      // 🔧 Nitidez: mantenemos móvil igual y subimos MUCHO en desktop
      const isDesktop  = initialWidth >= 900;
      const MAX_PIXELS = isDesktop ? 64_000_000 : 16_000_000; // 64MP en desktop / 16MP móvil
      const dprRaw     = window.devicePixelRatio || 1;

      // En escritorio forzamos supersampling (2.5x) para nítido en 1x/1.25x
      // En móvil dejamos igual como te funcionaba
      const boost = isDesktop ? 2.5 : (initialWidth < 480 ? 1.5 : 1.0);
      const cap   = isDesktop ? (initialWidth >= 1200 ? 8 : 6) : 3;

      for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
        const page = await pdf.getPage(pageNum);
        const base = page.getViewport({ scale: 1 });
        const cssScale = initialWidth / base.width;

        // DPR objetivo (clamp al cap y luego ajustamos por presupuesto de píxeles)
        let renderDpr = Math.min(dprRaw * boost, cap);
        let renderViewport = page.getViewport({ scale: cssScale * renderDpr });

        // Guardrail de memoria: si excede píxeles máximos, reducimos DPR
        let pixels = renderViewport.width * renderViewport.height;
        if (pixels > MAX_PIXELS) {
          const reduceFactor = Math.sqrt(pixels / MAX_PIXELS);
          renderDpr = Math.max(1, renderDpr / reduceFactor);
          renderViewport = page.getViewport({ scale: cssScale * renderDpr });
          pixels = renderViewport.width * renderViewport.height;
        }

        // Wrapper (con gutter fijo que no cambia con zoom)
        const pageWrapper = document.createElement('div');
        pageWrapper.className = 'pdf-page-wrapper';
        pageWrapper.style.width = '100%';
        pageWrapper.style.boxSizing = 'border-box';
        pageWrapper.style.paddingLeft = gutter + 'px';
        pageWrapper.style.paddingRight = gutter + 'px';
        pageWrapper.style.margin = '0 auto';
        pageWrapper.style.maxWidth = (initialWidth + gutter * 8) + 'px';

        // Canvas
        const canvas = document.createElement('canvas');
        canvas.className = 'pdf-page-canvas';

        // Tamaño REAL (px) para nitidez
        canvas.width  = Math.floor(renderViewport.width);
        canvas.height = Math.floor(renderViewport.height);

        // Tamaño VISUAL: ocupa el ancho calculado de página (márgenes incluidos en wrapper)
        canvas.style.width  = '100%';
        canvas.style.height = 'auto';
        canvas.style.display = 'block';

        // En escritorio desactivamos "crisp-edges" para evitar serrucho al upsample
        if (isDesktop) {
          canvas.style.imageRendering = 'auto';
        }

        const ctx = canvas.getContext('2d', { alpha: false, willReadFrequently: false });
        ctx.imageSmoothingEnabled = false;

        pageWrapper.appendChild(canvas);
        container.appendChild(pageWrapper);

        await page.render({ canvasContext: ctx, viewport: renderViewport, intent: 'display' }).promise;
        page.cleanup?.();
      }
    } catch (err) {
      console.error('Error al renderizar PDF:', err);
      container.innerHTML = `
        <div class="pdf-error">
          No fue posible mostrar el documento.<br>
          <a href="${url}" target="_blank" rel="noopener">Abrir en una nueva pestaña</a>
        </div>`;
    }
  }

  // Re-render solo por cambios REALES de layout (no zoom)
  function setupStableRerender(stack, url) {
    let lastWidth = parseInt(stack.dataset.initialWidth || '0', 10) || stack.clientWidth || 0;

    const rerender = () => {
      // Evita re-render mientras hay pinch-zoom activo
      if (window.visualViewport && window.visualViewport.scale !== 1) return;

      const current = Math.round(stack.clientWidth || 0);
      // Solo re-render si cambió MUCHO (>160 px) — típico de rotación/breakpoint
      if (Math.abs(current - lastWidth) > 160) {
        renderPdfStack(stack, url);
        lastWidth = current;
      }
    };

    window.addEventListener('orientationchange', () => setTimeout(rerender, 350), { passive: true });
    // Si quieres considerar grandes cambios de layout de ventana (no zoom), puedes activar esto:
    // window.addEventListener('resize', () => {
    //   clearTimeout(stack._rzTO);
    //   stack._rzTO = setTimeout(rerender, 250);
    // }, { passive: true });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const stacks = document.querySelectorAll('.pdf-stack[data-pdf]');

    stacks.forEach(stack => {
      const url = stack.getAttribute('data-pdf');

      // Placeholder mientras carga
      stack.innerHTML = `
        <div class="pdf-page-wrapper" style="padding:12px;text-align:center;">
          <div style="color:#666;">Cargando documento…</div>
        </div>`;

      renderPdfStack(stack, url);
      setupStableRerender(stack, url);
    });
  });
</script>


<script>
    let terminosAceptados = false;
    let currentAction = '';
    function showModal(action) {
        currentAction = action;
        document.getElementById('confirmModal').style.display = 'flex';
        let content = '';
        if(action === 'terminos') {
            content = `
            <h3 style="color: #fff; margin-bottom: 20px; text-align: center;">Términos y Condiciones</h3>
            <div style="margin-bottom: 24px; height: 60vh; overflow-y: auto; padding-right: 16px;">
                <p style="margin-bottom: 16px;"><strong>Distribuciones Tecnológicas de Colombia S.A.S. – Distritec</strong><br>
                NIT 901.042.503</p>

                <p style="margin-bottom: 16px;">"Distritec informa que los datos personales suministrados serán tratados conforme a nuestra Política de Tratamiento de Datos Personales disponible en www.distritec.co. El titular podrá ejercer sus derechos de acceso, rectificación, cancelación y oposición a través del correo habeasdata@distritec.co."</p>

                <div style="margin: 24px 0; padding: 16px; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px;">
                    <h4 style="color: #fff; margin-bottom: 16px; text-align: center;">AUTORIZACIÓN DE TRATAMIENTO DE DATOS PERSONALES</h4>
                    
                    <p style="margin-bottom: 16px;">Yo, <span style="border-bottom: 1px solid #fff; padding: 0 4px;">{{ $cliente->clientesNombreCompleto->primer_nombre ?? '' }} {{ $cliente->clientesNombreCompleto->segundo_nombre ?? '' }} {{ $cliente->clientesNombreCompleto->primer_apellido ?? '' }} {{ $cliente->clientesNombreCompleto->segundo_apellido ?? '' }}</span>, 
                    identificado con cédula de ciudadanía No. <span style="border-bottom: 1px solid #fff; padding: 0 4px;">{{ $cliente->num_documento ?? '' }}</span>, 
                    en calidad de titular de los datos personales, autorizo de manera previa, expresa e informada a 
                    Distribuciones Tecnológicas de Colombia S.A.S. – Distritec, NIT 901.042.503, para recolectar, 
                    almacenar, usar, circular, suprimir y en general dar tratamiento a mis datos personales conforme 
                    a la Política de Tratamiento de Datos Personales publicada por la compañía.</p>

                    <div style="margin-top: 24px;">
                        <p style="margin-bottom: 8px;">Firma: _______________________</p>
                        <p style="margin-bottom: 8px;">Nombre: {{ $cliente->clientesNombreCompleto->primer_nombre ?? '' }} {{ $cliente->clientesNombreCompleto->primer_apellido ?? '' }}</p>
                        <p>CC: {{ $cliente->num_documento ?? '' }}</p>
                    </div>
                </div>

                <div style="margin: 24px 0; padding: 16px; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px;">
                    <h4 style="color: #fff; margin-bottom: 16px; text-align: center;">CONTRATO DE FIRMA ELECTRÓNICA MEDIANTE TOKEN</h4>
                    
                    <p style="margin-bottom: 16px;">Entre los suscritos, de una parte DISTRIBUCIONES TECNOLÓGICAS DE COLOMBIA S.A.S. – DISTRITEC, identificada con NIT 901.042.503, quien en adelante se denominará DISTRITEC, y de otra parte el CLIENTE, persona natural identificada con su respectivo documento de identidad, quien en adelante se denominará EL CLIENTE, hemos convenido en celebrar el presente CONTRATO DE FIRMA ELECTRÓNICA MEDIANTE TOKEN, el cual se regirá por las siguientes CLÁUSULAS:</p>

                    <h5 style="color: #fff; margin: 16px 0 8px;">1. OBJETO</h5>
                    <p style="margin-bottom: 16px;">El presente contrato tiene por objeto regular el uso de la firma electrónica mediante token implementada por DISTRITEC para la suscripción de contratos de crédito de equipos celulares, documentación de garantía, cartas antifraude y demás documentos contractuales relacionados.</p>

                    <h5 style="color: #fff; margin: 16px 0 8px;">2. MARCO LEGAL</h5>
                    <p style="margin-bottom: 16px;">Este contrato se encuentra amparado por: Ley 527 de 1999, Decreto 1747 de 2000, Decreto 2364 de 2012, Ley 1581 de 2012, Decreto 1377 de 2013 y las disposiciones de la Superintendencia de Industria y Comercio y la Superintendencia Financiera de Colombia en materia de comercio electrónico, protección de datos y contratos.</p>

                    <h5 style="color: #fff; margin: 16px 0 8px;">3. DEFINICIONES</h5>
                    <ul style="margin-bottom: 16px; padding-left: 20px;">
                        <li style="margin-bottom: 8px;">Firma Electrónica: Conjunto de métodos técnicos que permiten identificar al firmante y garantizar la integridad del documento.</li>
                        <li style="margin-bottom: 8px;">Token: Código único, temporal e intransferible enviado al CLIENTE por DISTRITEC a través de canales autorizados.</li>
                        <li style="margin-bottom: 8px;">CLIENTE: Persona natural que acepta los términos y condiciones de este contrato.</li>
                    </ul>

                    <h5 style="color: #fff; margin: 16px 0 8px;">4. ACEPTACIÓN DE LA FIRMA ELECTRÓNICA</h5>
                    <p style="margin-bottom: 16px;">EL CLIENTE acepta que el ingreso del token constituye manifestación inequívoca de su consentimiento, con la misma validez legal que la firma manuscrita, conforme a lo previsto en la Ley 527 de 1999.</p>

                    <h5 style="color: #fff; margin: 16px 0 8px;">5. OBLIGACIONES DE DISTRITEC</h5>
                    <ul style="margin-bottom: 16px; padding-left: 20px;">
                        <li style="margin-bottom: 8px;">Generar tokens de un solo uso, únicos y temporales.</li>
                        <li style="margin-bottom: 8px;">Implementar protocolos de seguridad que garanticen autenticidad, confidencialidad e integridad.</li>
                        <li style="margin-bottom: 8px;">Conservar los documentos firmados y registros de trazabilidad.</li>
                        <li style="margin-bottom: 8px;">Poner a disposición del CLIENTE copia de los documentos suscritos.</li>
                    </ul>

                    <h5 style="color: #fff; margin: 16px 0 8px;">6. OBLIGACIONES DEL CLIENTE</h5>
                    <ul style="margin-bottom: 16px; padding-left: 20px;">
                        <li style="margin-bottom: 8px;">Custodiar debidamente sus medios de autenticación.</li>
                        <li style="margin-bottom: 8px;">Suministrar información verídica y actualizada.</li>
                        <li style="margin-bottom: 8px;">Reconocer como válidas todas las operaciones efectuadas mediante el token enviado a sus canales autorizados.</li>
                        <li style="margin-bottom: 8px;">Responder por fraudes o negligencia en la custodia de sus dispositivos.</li>
                    </ul>

                    <h5 style="color: #fff; margin: 16px 0 8px;">7. PROTECCIÓN DE DATOS PERSONALES</h5>
                    <p style="margin-bottom: 16px;">DISTRITEC dará cumplimiento a la Ley 1581 de 2012 en el tratamiento de los datos personales del CLIENTE, quien podrá ejercer los derechos de acceso, rectificación, cancelación y oposición a través de los canales dispuestos por la compañía.</p>

                    <h5 style="color: #fff; margin: 16px 0 8px;">8. CONSERVACIÓN DE LA EVIDENCIA</h5>
                    <p style="margin-bottom: 16px;">DISTRITEC conservará en medios electrónicos seguros: el documento íntegro firmado, las evidencias de autenticación del token y la constancia de aceptación del CLIENTE, los cuales tendrán plena validez probatoria.</p>

                    <h5 style="color: #fff; margin: 16px 0 8px;">9. LIMITACIÓN DE RESPONSABILIDAD</h5>
                    <p style="margin-bottom: 16px;">DISTRITEC no será responsable por fraudes ocasionados por negligencia del CLIENTE, pérdida de dispositivos o uso indebido de los canales autorizados.</p>

                    <h5 style="color: #fff; margin: 16px 0 8px;">10. VALIDEZ JURÍDICA Y PRUEBA</h5>
                    <p style="margin-bottom: 16px;">El CLIENTE reconoce que los documentos firmados electrónicamente mediante token tienen plena validez y eficacia probatoria en cualquier proceso judicial o administrativo.</p>

                    <h5 style="color: #fff; margin: 16px 0 8px;">11. JURISDICCIÓN Y LEY APLICABLE</h5>
                    <p style="margin-bottom: 16px;">El presente contrato se regirá por la legislación colombiana. Las diferencias se someterán a la jurisdicción ordinaria de los jueces de la República de Colombia.</p>

                    <h5 style="color: #fff; margin: 16px 0 8px;">12. ACEPTACIÓN</h5>
                    <p style="margin-bottom: 16px;">EL CLIENTE declara haber leído y comprendido íntegramente este contrato y acepta que el ingreso del token equivale a su firma electrónica, obligándose en todos sus efectos.</p>

                    <div style="margin-top: 32px;">
                        <p style="margin-bottom: 24px;">FIRMAS</p>
                        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                            <div style="flex: 1; min-width: 200px;">
                                <p style="margin-bottom: 8px;">EL CLIENTE</p>
                                <p style="margin-bottom: 8px;">_______________________</p>
                                <p style="margin-bottom: 8px;">Nombre: {{ $cliente->clientesNombreCompleto->primer_nombre ?? '' }} {{ $cliente->clientesNombreCompleto->primer_apellido ?? '' }}</p>
                                <p>CC: {{ $cliente->num_documento ?? '' }}</p>
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <p style="margin-bottom: 8px;">REPRESENTANTE LEGAL DE DISTRITEC</p>
                                <p style="margin-bottom: 8px;">_______________________</p>
                            </div>
                        </div>
                    </div>
                </div>

                <p>Antes de continuar, por favor lea detenidamente los términos anteriores. Al aceptar, usted reconoce que ha leído y comprendido la información, y que está de acuerdo con las condiciones aquí descritas.</p>
            </div>
            <p style='margin-bottom: 8px; font-weight:bold;'>¿Acepta los Términos y Condiciones, la Autorización de Tratamiento de Datos Personales y el Contrato de Firma Electrónica?</p>`;
        } else if(action === 'comerciales') {
            content = `<p><b>Términos Comerciales:</b> Al aceptar los términos comerciales, usted reconoce y acepta las condiciones de compra, pago, devoluciones, garantías y demás aspectos relacionados con la adquisición de productos o servicios ofrecidos por la empresa. Es importante que comprenda sus derechos y obligaciones comerciales antes de aceptar.</p>
            <p style='margin-bottom: 8px; font-weight:bold;'>¿Acepta los Términos Comerciales?</p>`;
        } else {
            content = '<p>¿Estás seguro?</p>';
        }
        document.getElementById('modalContent').innerHTML = content;
    }
    function modalAccept() {
        document.getElementById('confirmModal').style.display = 'none';
        if(currentAction === 'terminos') {
            terminosAceptados = true;
            guardarTermino(1);
            
        }
    }
    function modalDecline() {
        document.getElementById('confirmModal').style.display = 'none';
        guardarTermino(0);
    }
    function guardarTermino(acepta) {
        fetch("{{ route('guardar.termino') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({
                id_cliente: {{ $cliente->id_cliente }},
                tipo: currentAction === 'terminos' ? 'tyc' : 'comercial',
                acepta: acepta
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                showSuccessGlobalAlert();
            } else {
                alert('Error al guardar la respuesta.');
            }
        });
    }
    // Función para mostrar la alerta en el modal
    function showModalAlert() {
        var alertDiv = document.getElementById('modalAlert');
        if(alertDiv) {
            alertDiv.style.display = 'block';
            setTimeout(function() {
                alertDiv.style.display = 'none';
            }, 3000);
        }
    }

    // Mostrar alerta global y hacer scroll arriba
    function showGlobalAlert() {
        var alertDiv = document.getElementById('globalAlert');
        if(alertDiv) {
            alertDiv.style.display = 'block';
           
            setTimeout(function() {
                alertDiv.style.display = 'none';
            }, 3000);
        }
    }

    // Mostrar notificación de guardado exitoso
    function showSuccessGlobalAlert() {
        var alertDiv = document.getElementById('successGlobalAlert');
        if(alertDiv) {
            alertDiv.style.display = 'block';
            
            setTimeout(function() {
                alertDiv.style.display = 'none';
            }, 3000);
        }
    }

    // Validación al intentar continuar
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.querySelector('form[action="{{ route('finalizar.proceso') }}"]');
        form.addEventListener('submit', function(e) {
            if(!terminosAceptados) {
                e.preventDefault();
                showGlobalAlert();
            }
        });
    });
</script>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        window.onload = function() {
            new Swiper('.mySwiper', {
                slidesPerView: 1,
                spaceBetween: 0,
                centeredSlides: true,
                loop: false,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                allowTouchMove: true,
                observer: true,
                observeParents: true,
            });
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var alert = document.getElementById('success-alert');
            if(alert) {
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 3000);
            }
        });
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const btnAceptarTerminos = document.getElementById('btn-aceptar-terminos');
    const btnContinuar = document.getElementById('btn-continuar');
    if (btnContinuar) {
        btnContinuar.disabled = true; // Deshabilitado por defecto
    }
    if (btnAceptarTerminos && btnContinuar) {
        btnAceptarTerminos.addEventListener('click', function() {
            btnContinuar.disabled = false;
        });
    }
});
</script>

<script>
function showGlobalAlert() {
    var alertDiv = document.getElementById('globalAlert');
    if(alertDiv) {
        alertDiv.innerHTML = "Debes aceptar <b>Términos y Condiciones</b> para continuar.";
        alertDiv.style.display = 'block';
       
        setTimeout(function() {
            alertDiv.style.display = 'none';
        }, 3000);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const btnContinuar = document.getElementById('btn-continuar');
    if(form && btnContinuar) {
        form.addEventListener('submit', function(e) {
            if(btnContinuar.disabled) {
                e.preventDefault();
                showGlobalAlert();
            }
        });
    }
});
</script>



@endsection
