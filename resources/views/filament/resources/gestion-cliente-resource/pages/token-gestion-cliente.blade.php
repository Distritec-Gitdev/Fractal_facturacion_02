<x-filament::page>
    <x-slot name="header">
        <h2 class="header-title">
            Generar y enviar token para:
            <span class="header-highlight">{{ $this->record->cedula }}</span>
        </h2>
    </x-slot>
    
    <link rel="stylesheet" href="{{ asset('css/modulo_token.css') }}">


    <div class="section-wrapper">
        <canvas id="particleCanvas"></canvas>

        <div class="content-wrapper">

        @if (! $this->aprobadoCliente)
        <div class="filament-notifications pointer-events-none">
        <div class="fi-notification bg-yellow-50 text-yellow-900 border border-yellow-200 rounded-xl p-4 shadow-sm">
        <div class="font-semibold">En espera a la aprobación del cliente</div>
        <div class="text-sm opacity-90">Para seguir con la firma y el envío del token, la gestión debe estar en estado Aprobado.</div>
        </div>
        </div>
        @endif
            <!-- Mensaje de documentación mejorado -->
            <div class="documentation-alert">
                EN ESPERA DE FIRMA DE DOCUMENTACIÓN
            </div>
@php
    // Normaliza a entero para que las comparaciones funcionen
    $estado = is_numeric($this->estadoCredito ?? null)
        ? (int) $this->estadoCredito
        : 0; // 0 = desconocido

    $label = match ($estado) {
        1 => 'Pendiente',
        2 => 'Aprobado',
        3 => 'No aprobado',
        default => 'Desconocido',
    };
@endphp

@if (! $this->aprobadoCliente)
    <div class="mt-3">
        <div
            class="status-badge inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold ring-2 shadow-md tracking-wide uppercase"
            @class([
                // Base visual
                '!text-white',
                // Colores por estado (todas clases literales)
                '!bg-blue-600 !ring-blue-300'   => $estado == 1,
                '!bg-green-600 !ring-green-300' => $estado == 2,
                '!bg-red-600 !ring-red-300'     => $estado == 3,
                '!bg-gray-500 !ring-gray-300'   => ! in_array($estado, [1,2,3], true),
            ])>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-90" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.5a.75.75 0 10-1.5 0v4.25a.75.75 0 001.5 0V6.5zM10 14a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
            </svg>
            <span class="opacity-90">Estado del crédito:</span>
            <span class="font-extrabold">{{ $label }}</span>
        </div>
    </div>
@endif


            
           @php
                // Ventana de 90 segundos
                $validitySeconds = 90;

                $lastToken  = $this->record->token()->latest('created_at')->first();
                $authStatus = $lastToken->authentication_token ?? null;

                // Tiempos personalizados (sin "use", usando FQCN)
                $smsAt   = $lastToken?->envio_mssg ? \Illuminate\Support\Carbon::parse($lastToken->envio_mssg) : null;
                $emailAt = $lastToken?->envio_email ? \Illuminate\Support\Carbon::parse($lastToken->envio_email) : null;

                // Punto de partida del contador
                $startTime = match ($authStatus) {
                    3 => $smsAt,
                    4 => $emailAt,
                    default => null,
                };

                $expiresAtTs = $startTime ? $startTime->copy()->addSeconds($validitySeconds)->timestamp : null;
                $isActive    = $expiresAtTs && now()->timestamp < $expiresAtTs;
            @endphp


            @php
                // ===== Cooldown de 30 minutos basado en created_at =====
                $cooldownSeconds  = 30 * 60; // 30 min
                $cooldownStart    = isset($lastToken) ? $lastToken->created_at : null;
                $cooldownExpireTs = $cooldownStart ? $cooldownStart->copy()->addSeconds($cooldownSeconds)->timestamp : null; // seg UNIX
                $cooldownRemain   = $cooldownExpireTs ? max(0, $cooldownExpireTs - now()->timestamp) : 0; // seg restantes

                // === Paso 2: bandera de bloqueo UI ===
                $bloqueado = ! $this->aprobadoCliente;
            @endphp





            <!-- Reloj moderno y elegante -->
            @if(! $smsError && in_array($authStatus, [3,4]) && $isActive)
               <div class="token-counter-container">
                    <div class="clock-border"></div>
                    <div class="clock-face">
                        <div class="clock-inner">
                            <div
                                class="counter-text"
                                x-data="{
                                    expiresAt: {{ $expiresAtTs ?? 0 }},
                                    now: Math.floor(Date.now()/1000),
                                    remaining() { return Math.max(0, this.expiresAt - this.now); },
                                    tick() {
                                        this.now = Math.floor(Date.now()/1000);
                                        if (this.remaining() <= 30 && this.remaining() > 0) {
                                            this.$el.classList.add('pulse');
                                            setTimeout(() => this.$el.classList.remove('pulse'), 500);
                                        }
                                        if (this.remaining() === 0) {
                                            this.$dispatch('refreshbuttons');
                                        }
                                    }
                                }"
                                x-init="setInterval(() => tick(), 1000)"
                                x-text="Math.floor(remaining()/60) + ':' + String(remaining()%60).padStart(2,'0')"
                            ></div>
                            <div class="counter-label">Tiempo restante</div>
                        </div>
                    </div>

                    <div class="clock-markers">
                        @for ($i = 0; $i < 12; $i++)
                            <div class="clock-marker" style="transform: rotate({{ $i * 30 }}deg) translateY(-7rem);"></div>
                        @endfor
                    </div>
                </div>

                {{-- Pequeño poll para mantener botones/estado al día sin recargar --}}
                <span wire:poll.40s="handleRefresh" style="display:none;"></span>

            @endif

            {{-- === Paso 2: Bloqueo visual del PROCESO DE TOKEN ===
                 Todo lo relacionado a generar/enviar/reiniciar va dentro de este contenedor --}}
            <div @class([
                    'token-ui-wrap',
                    'opacity-50 select-none' => $bloqueado,
                    'pointer-events-none' => $bloqueado,
                ])
                @style($bloqueado ? 'filter: grayscale(0.15);' : '')
            >

            <!-- Formulario de Filament -->
            <div class="form-grid">
                {{ $this->form }}
            </div>

            <!-- Botones con iconos -->
            <div class="btn-group">
                @if($authStatus !== 3 && $authStatus !== 4 && $authStatus !== 5)
                    <button wire:click="generateAndSend" class="btn btn-primary">
                        <span class="btn-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 2l-7 20-4-9-9-4z"></path>
                                <path d="M22 2L11 13"></path>
                            </svg>
                        </span>
                        Generar y Enviar Token por SMS
                    </button>
                @endif

                @if(($smsError || $this->shouldShowEmailButton()) && $authStatus !== 4 && $authStatus !== 5)
                    <button wire:click="sendByEmail" class="btn btn-danger-outline">
                        <span class="btn-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </span>
                        Enviar Token por Correo
                    </button>
                @endif

                @if($authStatus === 4 && $this->shouldShowWhatsAppButton())
                    <button wire:click="sendByWhatsApp" class="btn btn-success">
                        <span class="btn-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                <path d="M17 8h.01"></path>
                                <path d="M13 8h.01"></path>
                                <path d="M9 8h.01"></path>
                            </svg>
                        </span>
                        Enviar Token por WhatsApp
                    </button>
                @endif

                @php
                    // URL firmada por 30 minutos, ligada al usuario actual (uid) y marcada como "via=token"
                    $signedEditUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                        'filament.admin.resources.clientes.edit', // nombre de la ruta de Filament
                        now()->addMinutes(30),
                        [
                            'record' => $this->record->getKey(), // usa la PK real (id_cliente) a través de getKey()
                            'uid'    => auth()->id(),           // amarra la firma al usuario actual
                            'via'    => 'token',                // marcador para tu lógica
                        ]
                    );
                @endphp

                 <!--<a href="{{ $signedEditUrl }}" class="btn btn-warning">
                    <span class="btn-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"></path>
                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                        </svg>
                    </span>
                    Editar cliente
                </a>-->

            </div>
        
           @if ($authStatus === 5)
                <div class="cooldown-panel"
                    x-data="{
                        // segundos restantes iniciales (por si llegas con la pestaña activa)
                        remaining: {{ (int) $cooldownRemain }},
                        // deadline en segundos UNIX (lo convertimos a ms en JS)
                        deadline: {{ $cooldownExpireTs ? (int) $cooldownExpireTs : 'null' }},
                        update() {
                            if (!this.deadline) { this.remaining = 0; return; }
                            // cálculo desde tiempo absoluto → preciso aunque la pestaña haya estado inactiva
                            const msLeft = (this.deadline * 1000) - Date.now();
                            this.remaining = Math.max(0, Math.floor(msLeft / 1000));
                        },
                        fmt() {
                            const m = Math.floor(this.remaining / 60);
                            const s = String(this.remaining % 60).padStart(2, '0');
                            return m + ':' + s;
                        }
                    }"
                    x-init="
                        update();                            // sincroniza al cargar
                        setInterval(() => update(), 1000);   // recálculo cada segundo
                        document.addEventListener('visibilitychange', () => update()); // recálculo al volver la pestaña
                    "
                >
                    <div class="cooldown-chip">
                        <svg class="cooldown-icon" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 6v6l4 2"></path>
                        </svg>
                        <span>Tiempo restante para realizar de nuevo todo el proceso:</span>
                        <span class="cooldown-time" x-text="fmt()"></span>
                    </div>

                    <button
                        class="btn btn-warning"
                        wire:click="resetToken"
                        :disabled="remaining > 0"
                        title="Eliminar token y reiniciar el proceso"
                    >
                        Reiniciar
                    </button>
                </div>
            @endif

            </div> <!-- Fin token-ui-wrap -->


        </div>
    </div>

    <script>
        // Sistema de partículas mejorado
        const canvas = document.getElementById('particleCanvas');
        const ctx = canvas.getContext('2d');
        let particles = [];
        const particleCount = 80;
        const colorPalette = ['#c7d2fe', '#a5b4fc', '#818cf8', '#6366f1'];

        function resizeCanvas() {
            canvas.width = canvas.parentElement.clientWidth;
            canvas.height = canvas.parentElement.clientHeight;
        }

        class Particle {
            constructor() {
                this.reset();
                this.r = Math.random() * 3 + 1;
                this.color = colorPalette[Math.floor(Math.random() * colorPalette.length)];
            }
            
            reset() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.vx = (Math.random() - 0.5) * 0.8;
                this.vy = (Math.random() - 0.5) * 0.8;
                this.alpha = Math.random() * 0.4 + 0.1;
                this.life = Math.random() * 100 + 50;
            }
            
            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.r, 0, Math.PI * 2);
                ctx.fillStyle = this.color;
                ctx.globalAlpha = this.alpha;
                ctx.fill();
            }
            
            update() {
                this.x += this.vx;
                this.y += this.vy;
                this.life--;
                
                if (this.x < -10 || this.x > canvas.width + 10 || 
                    this.y < -10 || this.y > canvas.height + 10 || 
                    this.life <= 0) {
                    this.reset();
                }
                
                this.draw();
            }
        }

        function initParticles() {
            particles = [];
            for (let i = 0; i < particleCount; i++) {
                particles.push(new Particle());
            }
        }

        function animateParticles() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(p => p.update());
            requestAnimationFrame(animateParticles);
        }

        // Inicialización
        window.addEventListener('load', () => {
            resizeCanvas();
            initParticles();
            animateParticles();
            
            window.addEventListener('resize', () => {
                resizeCanvas();
                initParticles();
            });
        });
    </script>
</x-filament::page>