@php
    // Import para usar Str dentro del Blade sin errores de sintaxis
    use Illuminate\Support\Str;
@endphp

{{-- resources/views/livewire/modals/documentation-modal.blade.php --}}


<div
    x-data="{
        show: false,
        showQRModal: false,
        fileUrl: '',
        currentIndex: 0,
        files: [],
        // 🔔 Estado de notificación
        notifShow: false,
        notifMsg: '',
        notify(msg) {
            this.notifMsg = msg || 'El proceso de firma no ha empezado o no se ha realizado por parte del cliente.';
            this.notifShow = true;
            clearTimeout(this._notifTimer);
            this._notifTimer = setTimeout(() => { this.notifShow = false }, 3500);
        },

        qrUrl: '{{ route('cliente.documentacion', ['cliente' => $cliente->id_cliente]) }}',
        open(index) {
            // Bloquear apertura si la firma no está habilitada
            if (!@this.firmaHabilitada) {
                window.dispatchEvent(new CustomEvent('firma-no-valida', {
                    detail: { message: 'El proceso de firma no ha empezzado o no se ha realizado por parte del cliente.' }
                }));
                return;
            }

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
    {{-- Listener para eventos desde Livewire ($this->dispatch) o desde JS --}}
    x-on:firma-no-valida.window="notify($event.detail?.message)"
    {{-- Si por cualquier motivo el visor estuviera abierto y no hay firma, se cierra al toque --}}
    x-effect="
        if (!@this.firmaHabilitada && show) {
            show = false;
            fileUrl = '';
            document.body.style.overflow = '';
        }
    "
>

    {{-- 🔔 Notificación bonita (toast) --}}
    <div
        x-cloak
        x-show="notifShow"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        class="fixed top-4 right-4 z-[100] max-w-sm w-full"
        role="status"
        aria-live="polite"
    >
        <div class="rounded-lg border border-amber-300 bg-amber-50 text-amber-900 shadow-lg">
            <div class="flex items-start gap-3 p-4">
                <div class="shrink-0">
                    <!-- ícono -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M12 9v4m0 4h.01M12 3l9 4.5v9L12 21 3 16.5v-9L12 3z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="font-semibold">Proceso de firma pendiente</p>
                    <p class="text-sm" x-text="notifMsg"></p>
                </div>
                <button
                    type="button"
                    class="rounded p-1 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-300"
                    @click="notifShow = false"
                    aria-label="Cerrar notificación"
                >
                    <!-- X -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 8.586l4.95-4.95a1 1 0 111.414 1.414L11.414 10l4.95 4.95a1 1 0 01-1.414 1.414L10 11.414l-4.95 4.95A1 1 0 013.636 15.95L8.586 11l-4.95-4.95A1 1 0 115.05 4.636L10 9.586z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    @if ($firmaHabilitada ?? false)

        {{-- 📱 Botón para Generar QR Móvil --}}
        <div class="mb-4" @click.stop>
            <button
                @click.prevent.stop="showQRModal = true"
                class="btn-qr"
            >
                📱 Generar QR Móvil
            </button>
        </div>

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

            @if ($successMessage)
                <div class="p-3 mb-4 text-green-800 bg-green-100 rounded">
                    {{ $successMessage }}
                </div>
            @endif

            <form wire:submit.prevent="guardarDocumentos">
                <div class="docs-grid">
                    @php
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

    @else
        {{-- 🚫 SIN FIRMA: no se muestra el módulo, solo aviso --}}
        <div class="p-4 border border-amber-300 bg-amber-50 rounded text-amber-900">
            <h3 class="font-semibold text-lg mb-1">Proceso de firma pendiente</h3>
            <p>El proceso de firma no ha empezado o no se ha realizado por parte del cliente.</p>
        </div>
        <script>
            // Aviso inmediato al cargar, por si llegaste a esta vista
            window.dispatchEvent(new CustomEvent('firma-no-valida', {
                detail: { message: 'El proceso de firma no ha empezado o no se ha realizado por parte del cliente.' }
            }));
        </script>
    @endif

</div>
