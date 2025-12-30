<div class="doc-wrapper">
    <h2 class="doc-title">Documentación de Cliente</h2>

    <form wire:submit.prevent="guardarDocumentos">
        <div class="docs-grid">
            @php
                use App\Models\ClientesContacto;

                $fields = [
                    'imagen_cedula_cara_delantera' => 'Cédula Cara Delantera',
                    'imagen_cedula_cara_trasera'   => 'Cédula Cara Trasera',
                    'imagen_persona_con_telefono'  => 'Persona con Teléfono',
                    'imagen_persona_con_cedula'    => 'Persona con Cédula',
                    'Carta_de_Garantías'           => 'Carta de Garantías',
                    'Carta_antifraude'             => 'Carta Antifraude',
                    'recibo_publico'               => 'Recibo Público',
                ];

                
            @endphp

            @foreach ($fields as $key => $label)
                @php
                    $file = $imagenes->{$key} ?? null;
                    $url = $file ? Storage::url('public/pdfs/' . $cliente->id_cliente . '/' . $file) : null;
                @endphp

                <div class="doc-card" data-file="{{ $url ?? '' }}">
                    <div>
                        <div class="doc-label">{{ $label }}</div>
                        <div class="doc-preview">
                            @if ($url)
                                @if (Str::endsWith($file, '.pdf'))
                                    <embed src="{{ $url }}" type="application/pdf" class="w-full h-full object-contain rounded" />
                                @else
                                    <img src="{{ $url }}" alt="{{ $label }}" class="h-full object-contain rounded" />
                                @endif
                            @else
                                <span class="doc-empty">Sin archivo</span>
                            @endif
                        </div>
                    </div>

                    @if (!$url)
                        <div class="doc-input">
                            <label for="file-{{ $key }}">Seleccionar archivo</label>
                            <input id="file-{{ $key }}" type="file" wire:model.defer="data.{{ $key }}" accept=".jpg,.jpeg,.png,.pdf" />
                        </div>
                    @endif

                    @if ($url)
                        <div class="doc-actions">
                            <button type="button" class="view-btn" @click="open(files.indexOf('{{ $url }}'))">
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
            <button type="submit" onclick="return confirm('¿Estás seguro de guardar los documentos?')">
                Guardar Documentos
            </button>
        </div>
    </form>

    <div class="text-xs text-gray-500 mt-4 text-center">
        <p x-text="`Archivos encontrados: ${files.length}`"></p>
    </div>
</div>
