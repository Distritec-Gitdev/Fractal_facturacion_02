{{-- resources/views/components/recibo-modal.blade.php --}}
<div
    x-data="reciboModal()"
    x-init="init()"
    x-cloak
    class="recibo-modal-root"
>
    {{-- Overlay --}}
    <div
        x-show="open"
        x-transition.opacity.duration.180ms
        class="recibo-overlay"
        @click="close()"
    ></div>

    {{-- Modal --}}
    <div
        x-show="open"
        x-transition:enter="recibo-enter"
        x-transition:enter-start="recibo-enter-start"
        x-transition:enter-end="recibo-enter-end"
        x-transition:leave="recibo-leave"
        x-transition:leave-start="recibo-leave-start"
        x-transition:leave-end="recibo-leave-end"
        class="recibo-modal"
        role="dialog"
        aria-modal="true"
        @keydown.escape.window="close()"
    >
        {{-- Header --}}
        <div class="recibo-header">
            <div class="recibo-title">
                <span class="recibo-dot"></span>
                <div class="recibo-title-text">
                    <div class="recibo-h1">Factura</div>
                    <div class="recibo-h2" x-text="url ? url : '—'"></div>
                </div>
            </div>

            <div class="recibo-actions">
                <button
                    type="button"
                    class="recibo-btn"
                    @click="openInNewTab()"
                    :disabled="!url"
                >
                    Abrir en pestaña
                </button>

                <button
                    type="button"
                    class="recibo-btn close"
                    @click="close()"
                >
                    Cerrar
                </button>
            </div>
        </div>

        {{-- Body --}}
        <div class="recibo-body">
            {{-- Loader --}}
            <div x-show="loading" class="recibo-loading">
                <div class="spinner"></div>
                <div class="loading-text">Cargando recibo…</div>
            </div>

            {{-- Iframe --}}
            <iframe
                x-ref="frame"
                class="recibo-iframe"
                :src="open ? url : ''"
                @load="loading=false"
                frameborder="0"
            ></iframe>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }

        :root{
            --corp: #82CC0E;
            --corp-soft: rgba(130,204,14,.18);
            --ink: #0b0f14;
        }

        .recibo-modal-root { position: relative; }

        /* Overlay con blur */
        .recibo-overlay{
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.62);
            z-index: 9998;
            backdrop-filter: blur(7px);
            -webkit-backdrop-filter: blur(7px);
        }

        /* Animaciones (clases usadas por Alpine) */
        .recibo-enter{
            transition: opacity 180ms ease, transform 180ms ease;
            transform-origin: center;
        }
        .recibo-enter-start{
            opacity: 0;
            transform: translate(-50%, -48%) scale(.985);
        }
        .recibo-enter-end{
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }
        .recibo-leave{
            transition: opacity 140ms ease, transform 140ms ease;
            transform-origin: center;
        }
        .recibo-leave-start{
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }
        .recibo-leave-end{
            opacity: 0;
            transform: translate(-50%, -48%) scale(.985);
        }

        .recibo-modal{
            position: fixed;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;

            width: min(1220px, 96vw);
            height: min(92vh, 920px);

            background: #0b0f14;
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 18px;
            overflow: hidden;

            box-shadow: 0 40px 130px rgba(0,0,0,.65);
            display: flex;
            flex-direction: column;
        }

        /* Header corporativo */
        .recibo-header{
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;

            padding: 12px 14px;
            background:
                radial-gradient(900px 220px at 10% 0%, rgba(130,204,14,.14), transparent 55%),
                rgba(255,255,255,.04);
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .recibo-header::before{
            content:"";
            position:absolute;
            left:0; right:0; top:0;
            height: 4px;
            background: linear-gradient(90deg, var(--corp), rgba(130,204,14,.40), #0b0f14);
        }

        .recibo-title{
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .recibo-dot{
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: var(--corp);
            box-shadow: 0 0 0 5px rgba(130,204,14,.16);
            flex: 0 0 auto;
        }

        .recibo-title-text{ min-width: 0; }

        .recibo-h1{
            font-weight: 900;
            font-size: 13px;
            color: #e5e7eb;
            line-height: 1.1;
            letter-spacing: .2px;
        }

        .recibo-h2{
            font-size: 11px;
            color: rgba(229,231,235,.70);
            max-width: 62vw;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-top: 3px;
        }

        .recibo-actions{
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 0 0 auto;
        }

        /* Botones más “premium” */
        .recibo-btn{
            appearance: none;
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.06);
            color: #e5e7eb;
            padding: 8px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: transform .12s ease, border-color .12s ease, background .12s ease, box-shadow .12s ease;
            box-shadow: 0 10px 22px rgba(0,0,0,.25);
        }

        .recibo-btn:hover{
            border-color: rgba(130,204,14,.55);
            background: rgba(130,204,14,.14);
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(0,0,0,.35);
        }

        .recibo-btn:active{
            transform: translateY(0);
            box-shadow: 0 10px 22px rgba(0,0,0,.25);
        }

        .recibo-btn:disabled{
            opacity: .45;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .recibo-btn.close{
            border-color: rgba(255,255,255,.10);
            background: rgba(255,255,255,.03);
        }

        .recibo-body{
            position: relative;
            flex: 1 1 auto;
            background: #111827;
        }

        .recibo-iframe{
            width: 100%;
            height: 100%;
            border: 0;
            background: #ffffff;
        }

        /* Loader más limpio */
        .recibo-loading{
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: rgba(17,24,39,.72);
            z-index: 2;
            color: #e5e7eb;
            font-weight: 800;
            font-size: 12px;
        }

        .spinner{
            width: 16px;
            height: 16px;
            border-radius: 999px;
            border: 2px solid rgba(229,231,235,.30);
            border-top-color: rgba(130,204,14,1);
            animation: spin 0.75s linear infinite;
            box-shadow: 0 0 0 5px rgba(130,204,14,.10);
        }

        @keyframes spin{ to { transform: rotate(360deg); } }

        @media (max-width: 640px){
            .recibo-modal{
                width: 96vw;
                height: 92vh;
                border-radius: 14px;
            }
            .recibo-h2{ display: none; }
        }
    </style>

    <script>
        function reciboModal() {
            return {
                open: false,
                url: null,
                loading: false,

                init() {
                    // Livewire v3 listener
                    document.addEventListener('livewire:init', () => {
                        if (window.Livewire?.on) {
                            Livewire.on('open-recibo-modal', (payload) => {
                                const u = payload?.url ?? payload;
                                this.show(u);
                            });
                        }
                    });

                    // Fallback browser event
                    window.addEventListener('open-recibo-modal', (e) => {
                        const u = e?.detail?.url ?? e?.detail ?? null;
                        this.show(u);
                    });

                    // Legacy helpers
                    window.openReciboModal = (u) => this.show(u);
                    window.closeReciboModal = () => this.close();
                },

                show(u) {
                    this.url = u;
                    this.loading = true;
                    this.open = true;

                    this.$nextTick(() => {
                        if (this.$refs.frame) {
                            this.$refs.frame.src = 'about:blank';
                            setTimeout(() => {
                                this.$refs.frame.src = this.url;
                            }, 60);
                        }
                    });
                },

                close() {
                    this.open = false;
                    this.loading = false;

                    this.$nextTick(() => {
                        if (this.$refs.frame) {
                            this.$refs.frame.src = 'about:blank';
                        }
                    });
                },

                openInNewTab() {
                    if (!this.url) return;
                    window.open(this.url, '_blank');
                },
            }
        }
    </script>
</div>
