{{-- resources/views/livewire/modals/documentation-modal.blade.php --}}


<div
    x-data="{
        show: false,
        showQRModal: false,
        fileUrl: '',
        currentIndex: 0,
        files: [],
        qrUrl: '{{ route('cliente.documentacion', ['cliente' => $cliente->id_cliente]) }}',
        open(index) {
            this.files = Array.from(document.querySelectorAll('[data-file]'))
                .map(el => el.dataset.file)
                .filter(Boolean);
            this.currentIndex = index;
            this.fileUrl = this.files[index];
            this.show = true;
            document.body.style.overflow = 'hidden';
        },
        close(event) {
            event.stopPropagation();
            this.show = false;
            this.fileUrl = '';
            document.body.style.overflow = '';
        },
        next(event) {
            event.stopPropagation();
            if (this.currentIndex < this.files.length - 1) {
                this.currentIndex++;
                this.fileUrl = this.files[this.currentIndex];
            }
        },
        prev(event) {
            event.stopPropagation();
            if (this.currentIndex > 0) {
                this.currentIndex--;
                this.fileUrl = this.files[this.currentIndex];
            }
        }
    }"
    @click.stop
    x-init="$nextTick(() => {
        this.files = Array.from(document.querySelectorAll('[data-file]'))
            .map(el => el.dataset.file)
            .filter(Boolean);


                // 2) Injerta tu CSS justo después
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '{{ asset('css/documentacion.css') }}';
        document.head.appendChild(link);
            
    })"
    
>

{{-- Loader global + Compresión cliente + espera hasta 100% y DOM procesado --}}
<style>
  .doc-loader{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.35);backdrop-filter:saturate(120%) blur(2px);z-index:9999;}
  .doc-loader.hidden{display:none;}
  .doc-loader__box{min-width:260px;max-width:90vw;background:#0f172a;color:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.25);padding:18px 20px;display:flex;flex-direction:column;gap:12px;align-items:center}
  .doc-spinner{width:28px;height:28px;border:3px solid rgba(255,255,255,.25);border-top-color:#fff;border-radius:50%;animation:spin 1s linear infinite}
  .doc-loader__title{font-weight:700;font-size:14px}
  .doc-loader__text{font-size:12px;opacity:.8}
  .doc-progress{width:100%;height:6px;background:rgba(255,255,255,.18);border-radius:999px;overflow:hidden}
  .doc-progress>div{height:100%;width:0%;background:#22c55e;transition:width .15s ease}
  @keyframes spin{to{transform:rotate(360deg)}}
</style>
<div id="docGlobalLoader" class="doc-loader hidden" aria-hidden="true">
  <div class="doc-loader__box">
    <div class="doc-spinner" aria-hidden="true"></div>
    <div class="doc-loader__title">Procesando…</div>
    <div id="docLoaderMsg" class="doc-loader__text">Esto puede tardar unos segundos.</div>
    <div class="doc-progress"><div id="docLoaderBar"></div></div>
  </div>
</div>

<script>
(() => {
  if (window.__DOC_HELPERS_INIT__) return; window.__DOC_HELPERS_INIT__ = true;

  // === Parámetros de compresión ===
  const MAX_DIM = 2000;                 // px máx. lado largo
  const MAX_BYTES = 1.5 * 1024 * 1024;  // umbral (~1.5MB)
  const JPEG_QUALITY = 0.82;

  // === Estado del loader / colas ===
  let activeUploads = 0;     // archivos subiendo según eventos Livewire
  let compressing = 0;       // compresiones en progreso
  let pendingMsgs = 0;       // mensajes Livewire en vuelo
  let hideTimer = null;      // pequeño settle antes de ocultar
  let fallbackTimer = null;  // por si algo se queda colgado

  const $loader = document.getElementById('docGlobalLoader');
  const $msg    = document.getElementById('docLoaderMsg');
  const $bar    = document.getElementById('docLoaderBar');

  function showLoader(text){
    if (text) $msg.textContent = text;
    $bar && ($bar.style.width = '0%');
    $loader?.classList.remove('hidden');
    $loader?.setAttribute('aria-hidden','false');

    // Fallback duro (p.e. si el servidor no responde eventos)
    clearTimeout(fallbackTimer);
    fallbackTimer = setTimeout(() => {
      // sólo cerrar si ya no hay nada en progreso
      if (compressing===0 && activeUploads===0 && pendingMsgs===0) hideLoader();
    }, 60000); // 60s
  }
  function hideLoader(){
    clearTimeout(hideTimer);
    clearTimeout(fallbackTimer);
    $loader?.classList.add('hidden');
    $loader?.setAttribute('aria-hidden','true');
    $msg && ($msg.textContent = 'Esto puede tardar unos segundos.');
    $bar && ($bar.style.width = '0%');
  }
  function setProgress(pct){
    if (!$bar) return;
    const v = Math.max(0, Math.min(100, Number(pct) || 0));
    $bar.style.width = v + '%';
  }
  function maybeHide(){
    clearTimeout(hideTimer);
    if (compressing>0 || activeUploads>0 || pendingMsgs>0) return;
    // Espera breve a que Livewire pinte previews/DOM
    hideTimer = setTimeout(() => {
      if (compressing===0 && activeUploads===0 && pendingMsgs===0) hideLoader();
    }, 600);
  }

  // ========= Eventos Livewire: mantener loader hasta 100% y DOM listo =========
  document.addEventListener('livewire-upload-start', () => {
    activeUploads++;
    showLoader('Subiendo archivos…');
    setProgress(0);
  });
  document.addEventListener('livewire-upload-progress', (e) => {
    setProgress(e.detail?.progress ?? 0);
  });
  document.addEventListener('livewire-upload-error', () => {
    activeUploads = Math.max(0, activeUploads - 1);
    maybeHide();
  });
  document.addEventListener('livewire-upload-finish', () => {
    activeUploads = Math.max(0, activeUploads - 1);
    setProgress(100);
    // No ocultar aún: esperamos a que Livewire procese y pinte
    maybeHide();
  });

  // Hooks de ciclo Livewire: cuando envía/procesa mensajes (incluye previews)
  if (window.Livewire?.hook) {
    window.Livewire.hook('message.sent', () => {
      pendingMsgs++;
      showLoader('Procesando…');
    });
    window.Livewire.hook('message.processed', () => {
      pendingMsgs = Math.max(0, pendingMsgs - 1);
      maybeHide();
    });
  }

  // ========= Loader al enviar formularios no-Livewire =========
  document.addEventListener('submit', (ev) => {
    const form = ev.target;
    if (form?.tagName === 'FORM') {
      showLoader('Guardando…');
      // Si no hay Livewire, ocúltalo a los 15s salvo que haya subidas/msgs
      setTimeout(maybeHide, 15000);
    }
  }, true);

  // ========= Compresión previa en cliente (no ocultar hasta que suba) =========
  function isHeicName(name){ return /\.heic|\.heif$/i.test(name || ''); }
  function shouldCompress(file){
    if (!file || !/^image\//.test(file.type) || isHeicName(file.name)) return false;
    return file.size > MAX_BYTES;
  }
  async function imgFromFile(file){
    if ('createImageBitmap' in window) {
      try { return await createImageBitmap(file); } catch (e) {}
    }
    const url = URL.createObjectURL(file);
    try {
      const img = await new Promise((res, rej) => { const i=new Image(); i.onload=()=>res(i); i.onerror=rej; i.src=url; });
      return img;
    } finally { URL.revokeObjectURL(url); }
  }
  async function compressFile(file){
    const img = await imgFromFile(file);
    const w = img.width, h = img.height;
    const scale = Math.min(1, MAX_DIM / Math.max(w, h));
    const outW = Math.max(1, Math.round(w * scale));
    const outH = Math.max(1, Math.round(h * scale));
    const canvas = document.createElement('canvas');
    canvas.width = outW; canvas.height = outH;
    const ctx = canvas.getContext('2d', { alpha:false });
    ctx.drawImage(img, 0, 0, outW, outH);
    const blob = await new Promise(res => canvas.toBlob(b => res(b), 'image/jpeg', JPEG_QUALITY));
    if (!blob || blob.size >= file.size) return file;
    const newName = (file.name.replace(/\.[^.]+$/, '') || 'archivo') + '-compressed.jpg';
    return new File([blob], newName, { type:'image/jpeg', lastModified: Date.now() });
  }

  document.addEventListener('change', async (ev) => {
    const input = ev.target;
    if (!input || input.tagName !== 'INPUT' || input.type !== 'file' || !input.files?.length) return;

    const files = Array.from(input.files);
    const anyHeavy = files.some(shouldCompress);
    if (anyHeavy) { compressing++; showLoader('Optimizando imágenes…'); }

    const dt = new DataTransfer();
    try {
      for (const f of files) {
        dt.items.add( shouldCompress(f) ? await compressFile(f) : f );
      }
      input.files = dt.files;
      input.dispatchEvent(new Event('input',  { bubbles:true }));
      input.dispatchEvent(new Event('change', { bubbles:true })); // para Livewire
    } finally {
      if (anyHeavy) {
        compressing = Math.max(0, compressing - 1);
        // Importante: NO ocultamos aquí. Esperamos a que empiece/suba y Livewire procese.
        maybeHide();
      }
    }
  }, true);

  // Por si el usuario navega mientras hay procesos
  window.addEventListener('beforeunload', () => { hideLoader(); });
})();
</script>




    {{-- Modal pequeño para mostrar el QR --}}
    <div
        x-show="showQRModal"
        x-cloak
        class="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
        @click="showQRModal = false"
    >
        <div class="bg-white p-6 rounded-lg shadow-lg" @click.stop>
            <img
                alt="QR para móvil"
                class="mx-auto mb-4"
                :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrUrl)}`"
            />
            <p class="text-center text-gray-700 mb-4">
                Escanea para abrir la carga de documentos<br>cliente #{{ $cliente->id_cliente }}
            </p>
            <button
                @click.prevent.stop="showQRModal = false"
                class="btn-close-qr"
            >Cerrar</button>
        </div>
    </div>

    {{-- Modal de vista completa --}}
    <div
        wire:ignore.self
        x-show="show"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 overflow-auto"
        @click.prevent.stop
    >
    
        <div
            class="modal-viewer mx-4 md:mx-auto flex flex-col"
            @click.prevent.stop
        >
            <!-- Botón cerrar -->
            <button
                @click.prevent.stop="close"
                class="modal-close"
            >✖</button>

            <!-- Controles de navegación -->
            <div class="modal-controls">
                <button
                    @click.prevent.stop="prev"
                    class="px-4 py-2 bg-gray-200 rounded disabled:opacity-50"
                    :disabled="currentIndex === 0"
                >⬅️</button>
                <button
                    @click.prevent.stop="next"
                    class="px-4 py-2 bg-gray-200 rounded disabled:opacity-50"
                    :disabled="currentIndex >= files.length - 1"
                >➡️</button>
            </div>

            <!-- Contenedor centrado para PDF o imagen -->
            <div class="flex-1 flex items-center justify-center p-4 overflow-auto">
                <!-- PDF -->
                <template x-if="fileUrl.endsWith('.pdf')">
                    <iframe
                        :src="fileUrl"
                        class="modal-iframe max-w-full max-h-[calc(100vh-6rem)]"
                    ></iframe>
                </template>
                <!-- Imagen -->
                <template x-if="!fileUrl.endsWith('.pdf')">
                    <img
                        :src="fileUrl"
                        class="max-w-full max-h-[calc(100vh-6rem)] object-contain rounded"
                        alt="Vista previa"
                    />
                </template>
            </div>
        </div>
    </div>

    {{-- Formulario principal --}}
    <div class="doc-wrapper">
        <h2 class="doc-title">Documentación de Cliente</h2>

     <!--       {{-- Depuración --}}
    <div class="p-2 text-xs bg-gray-100 rounded mb-2">
        <strong>Debug Data:</strong>
        <pre>{{ json_encode($data, JSON_PRETTY_PRINT) }}</pre>
    </div>
    <div class="p-2 text-xs bg-red-100 text-red-800 rounded mb-2">
        <strong>Debug Errors:</strong>
        <pre>{{ json_encode($errors->all(), JSON_PRETTY_PRINT) }}</pre>
    </div>-->

        @if ($successMessage)
            <div class="p-3 mb-4 text-green-800 bg-green-100 rounded">
                {{ $successMessage }}
            </div>
        @endif

        <form wire:submit.prevent="guardarDocumentos">
            <div class="docs-grid">
                 @php
                    use Illuminate\Support\Str;

                    $fields = [
                        'imagen_cedula_cara_delantera' => 'Cédula Cara Delantera',
                        'imagen_cedula_cara_trasera'   => 'Cédula Cara Trasera',
                        'imagen_persona_con_telefono'  => 'Persona con Teléfono',
                        'imagen_persona_con_cedula'    => 'Persona con Cédula',
                        'Carta_de_Garantías'           => 'Carta de Garantías',
                        'Carta_antifraude'             => 'Carta Antifraude',
                        'recibo_publico'               => 'Recibo Público',
                    ];

                    // Este branch NO incluye Carta_antifraude por diseño
                    if ((int) ($cliente->ID_Tipo_credito ?? 0) === 2) {
                        $fields = [
                            'imagen_cedula_cara_delantera' => 'Cédula Cara Delantera (ambas caras)',
                            'imagen_persona_con_telefono'  => 'Persona con Teléfono',
                            'imagen_persona_con_cedula'    => 'Persona con Cédula',
                            'Carta_de_Garantías'           => 'Carta de Garantías',
                        ];
                    }

                    // Regla recibo público (igual a la tuya)
                    if ((int) ($cliente->ID_Identificacion_Cliente ?? 0) === 44) {
                        $contacto = \App\Models\ClientesContacto::where('id_cliente', $cliente->id_cliente)->first();
                        if (! ($contacto && in_array((int) $contacto->residencia_id_municipio, [54001, 54874], true))) {
                            unset($fields['recibo_publico']);
                        }
                    } else {
                        unset($fields['recibo_publico']);
                    }

                    /**
                    * 🔍 Mostrar "Carta Antifraude" solo si hay algo cargado para ese doc.
                    * Soporta $imagenes como:
                    *  - array asociativo: ['Carta_antifraude' => <valor>]
                    *  - colección/lista de arrays/objetos donde algún campo/propiedad contenga "antifraude"
                    *  - objeto con atributo/relación accesible vía data_get
                    */
                    $hasAntifraude = false;

                    if (isset($imagenes)) {
                        // 1) Si es array asociativo y viene la key exacta
                        if (is_array($imagenes) && array_key_exists('Carta_antifraude', $imagenes)) {
                            $hasAntifraude = filled($imagenes['Carta_antifraude']);
                        }

                        // 2) Si es lista/colección de ítems (arrays/objetos) e identificamos por nombre/tipo
                        if (! $hasAntifraude && (is_array($imagenes) || $imagenes instanceof \Illuminate\Support\Collection)) {
                            $iter = $imagenes instanceof \Illuminate\Support\Collection ? $imagenes : collect($imagenes);
                            $hasAntifraude = $iter->contains(function ($item) {
                                // intenta distintas claves/props comunes
                                $candidatos = [];
                                if (is_array($item)) {
                                    $candidatos = [
                                        $item['campo']   ?? null,
                                        $item['tipo']    ?? null,
                                        $item['nombre']  ?? null,
                                        $item['documento'] ?? null,
                                        $item['slug']    ?? null,
                                        $item['nombre_archivo'] ?? null,
                                    ];
                                } elseif (is_object($item)) {
                                    $candidatos = [
                                        $item->campo      ?? null,
                                        $item->tipo       ?? null,
                                        $item->nombre     ?? null,
                                        $item->documento  ?? null,
                                        $item->slug       ?? null,
                                        $item->nombre_archivo ?? null,
                                    ];
                                }
                                foreach ($candidatos as $val) {
                                    if ($val && Str::of((string) $val)->lower()->contains('antifraude')) {
                                        return true;
                                    }
                                }
                                return false;
                            });
                        }

                        // 3) Como fallback, intenta data_get directo (por si es objeto con prop pública)
                        if (! $hasAntifraude) {
                            $hasAntifraude = filled(data_get($imagenes, 'Carta_antifraude'));
                        }
                    }

                    if (! $hasAntifraude) {
                        unset($fields['Carta_antifraude']);
                    }

                    // DEBUG opcional en HTML:
                    // echo "<!-- hasAntifraude=" . ($hasAntifraude ? '1' : '0') . " -->";
                @endphp

                @foreach ($fields as $key => $label)
                    @php
                        $existingFile = $imagenes->{$key} ?? null;
                        $url          = $existingFile
                            ? Storage::url("public/pdfs/{$cliente->id_cliente}/{$existingFile}")
                            : null;
                        $uploaded     = $data[$key] ?? null;
                    @endphp

                    <div class="doc-card" data-file="{{ $url ?? '' }}">
                        <div class="doc-label">{{ $label }}</div>

                        <div class="doc-preview">
                            @if ($uploaded)
                                @php $ext = Str::lower($uploaded->getClientOriginalExtension()); @endphp
                                @if ($ext === 'pdf')
                                    <div class="text-gray-700 p-2">
                                        📄 PDF cargado: {{ $uploaded->getClientOriginalName() }}
                                    </div>
                                @else
                                    <img src="{{ $uploaded->temporaryUrl() }}"
                                         class="w-full h-full object-contain rounded" />
                                @endif

                            @elseif ($url)
                                @if (Str::endsWith($existingFile, '.pdf'))
                                    <embed src="{{ $url }}" type="application/pdf"
                                           class="w-full h-full rounded"/>
                                @else
                                    <img src="{{ $url }}" class="w-full h-full object-contain rounded"/>
                                @endif

                            @else
                                <span class="doc-empty">Sin archivo</span>
                            @endif
                        </div>

                        {{-- Solo inputs para casillas sin archivo existente --}}
                        @if (! $url)
                            <div class="doc-input">
                                <label class="block w-full bg-green-500 text-white p-2 text-center rounded cursor-pointer">
                                    Seleccionar archivo
                                    <input
                                        type="file"
                                        wire:model="data.{{ $key }}"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        class="hidden"
                                    />
                                </label>
                                @error("data.{$key}")
                                    <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        @if ($url)
                            <div class="doc-actions">
                                <button type="button" class="view-btn"
                                        @click="open(files.indexOf('{{ $url }}'))">
                                    👁️ Ver
                                </button>
                                <a href="{{ $url }}" target="_blank" class="download-btn">
                                    ⬇️ Descargar
                                </a>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="footer-actions mt-6">
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded">
                    Guardar Documentos
                </button>
            </div>
        </form>

        <div class="text-xs text-gray-500 mt-4 text-center">
            <p x-text="`Archivos encontrados: ${files.length}`"></p>
        </div>
    </div>
</div>
