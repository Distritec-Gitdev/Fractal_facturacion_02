@extends('layouts.app')

@section('styles')
    {{-- Bootstrap Icons (necesario para que se vean los iconos) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/token-login.css') }}">


@section('content')
{{-- capa visual de la línea diagonal --}}
<div class="bg-cut" aria-hidden="true"></div>

<div class="container login-card with-anim">
    <div class="login-header">
        <div class="login-icon-wrap">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h2 class="login-title">Validar Acceso</h2>
        <p class="login-subtitle">
            <i class="bi bi-info-circle-fill me-1"></i>
            Ingresa tu <strong>cédula</strong> y confirma el <strong>token</strong> recibido.
        </p>
    </div>

    <div class="login-fields">
        @if(session('error'))
            <div class="alert alert-danger animate-shake">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('token.login.submit', ['token' => $token]) }}" class="login-form">
            @csrf

            {{-- CÉDULA --}}
            <div class="mb-3 field">
                <label for="cedula" class="form-label">
                    <i class="bi bi-person-vcard-fill me-1"></i> Cédula
                </label>
                <div class="input-with-icon">
                    <i class="bi bi-123 input-icon" aria-hidden="true"></i>
                    <input
                        type="text"
                        name="cedula"
                        class="form-control focus-bounce"
                        id="cedula"
                        required
                        inputmode="numeric"
                        maxlength="10"
                        pattern="[0-9]*"
                        autocomplete="off"
                        placeholder="Tu cédula (10 dígitos)"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                    >
                </div>
            </div>

            {{-- TOKEN --}}
            <div class="mb-3 field">
                <label for="token" class="form-label">
                    <i class="bi bi-check-square-fill me-1"></i> Token
                </label>
                <div class="input-with-icon">
                    <i class="bi bi-shield-lock-fill input-icon" aria-hidden="true"></i>
                    <input
                        type="text"
                        class="form-control readonly-ghost"
                        id="token"
                        name="token"
                        value="{{ $token }}"
                        readonly
                    >
                </div>
                <div class="tiny-hint">
                    <i class="bi bi-lightning-charge-fill me-1"></i>
                    Este token expira; úsalo pronto.
                </div>
            </div>

            {{-- BOTÓN --}}
            <button type="submit" class="btn btn-primary w-100 btn-validate">
                <span class="btn-sheen" aria-hidden="true"></span>
                <i class="bi bi-shield-check me-2"></i>
                <span class="btn-label">Validar</span>
            </button>
        </form>
    </div>
</div>

<!-- Loader Global -->
<div id="page-loader" hidden aria-hidden="true" aria-busy="true">
  <div role="status" aria-live="polite" style="display:flex; flex-direction:column; align-items:center;">
    <div class="loader-ring"></div>
    <div class="loader-text">Procesando…</div>
  </div>
</div>





<script>
/* ---------------- Loader Controller ---------------- */
(function () {
  const el = document.getElementById('page-loader');
  const state = { count: 0, showTimer: null };

  function render() {
    // Muestra solo si count > 0 (y con un pequeño delay para evitar parpadeos)
    if (state.count > 0) {
      if (state.showTimer == null) {
        state.showTimer = setTimeout(() => { el.hidden = false; }, 120);
      }
    } else {
      clearTimeout(state.showTimer);
      state.showTimer = null;
      el.hidden = true;
    }
  }

  const AppLoader = {
    inc() { state.count++; render(); },
    dec() { state.count = Math.max(0, state.count - 1); render(); },
    show() { state.count++; render(); },
    hide() { state.count = 0; render(); }, // forzado
    wrap(promise) { AppLoader.inc(); return Promise.resolve(promise).finally(AppLoader.dec); }
  };

  // Exponer global
  window.AppLoader = AppLoader;

  // Mostrar durante navegación/salida
  window.addEventListener('beforeunload', () => { AppLoader.show(); });

  // Mostrar al enviar cualquier formulario
  document.addEventListener('submit', function (e) {
    // Evitar dobles si es un submit sin navegación; igual sirve si hay validación
    AppLoader.show();
  }, true);

  // En DOM listo: opcional mostrar breve si hay tareas iniciales
  document.addEventListener('DOMContentLoaded', () => {
    // Si quieres que aparezca brevemente en carga inicial, descomenta:
    // AppLoader.show();
    // window.addEventListener('load', () => AppLoader.hide());
  });

  // Interceptor global de fetch()
  const _fetch = window.fetch;
  window.fetch = function () {
    AppLoader.inc();
    return _fetch.apply(this, arguments)
      .catch(err => { throw err; })
      .finally(() => AppLoader.dec());
  };
})();
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
@endsection


