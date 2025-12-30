@props([
    'livewire' => null,
])

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ __('filament-panels::layout.direction') ?? 'ltr' }}"
    @class([
        'fi min-h-screen',
        'dark' => filament()->hasDarkModeForced(),
    ])

    
    
>
    <head>

   @vite('resources/js/app.js')
     @vite('resources/css/app.css')
    @livewireStyles
    @filamentStyles


        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::HEAD_START, scopes: $livewire->getRenderHookScopes()) }}

        <meta charset="utf-8" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />

        @if ($favicon = filament()->getFavicon())
            <link rel="icon" href="{{ $favicon }}" />
        @endif

        @php
            $title = trim(strip_tags(($livewire ?? null)?->getTitle() ?? ''));
            $brandName = trim(strip_tags(filament()->getBrandName()));
        @endphp

         @stack('styles')

        <title>
            {{ filled($title) ? "{$title} - " : null }} {{ $brandName }}
        </title>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::STYLES_BEFORE, scopes: $livewire->getRenderHookScopes()) }}

        <style>
            [x-cloak=''],
            [x-cloak='x-cloak'],
            [x-cloak='1'] {
                display: none !important;
            }

            @media (max-width: 1023px) {
                [x-cloak='-lg'] {
                    display: none !important;
                }
            }

            @media (min-width: 1024px) {
                [x-cloak='lg'] {
                    display: none !important;
                }
            }
        </style>

     

        {{ filament()->getTheme()->getHtml() }}
        {{ filament()->getFontHtml() }}

        <style>
            :root {
                --font-family: '{!! filament()->getFontFamily() !!}';
                --sidebar-width: {{ filament()->getSidebarWidth() }};
                --collapsed-sidebar-width: {{ filament()->getCollapsedSidebarWidth() }};
                --default-theme-mode: {{ filament()->getDefaultThemeMode()->value }};
            }
        </style>

        @stack('styles')

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::STYLES_AFTER, scopes: $livewire->getRenderHookScopes()) }}

        @if (! filament()->hasDarkMode())
            <script>
                localStorage.setItem('theme', 'light')
            </script>
        @elseif (filament()->hasDarkModeForced())
            <script>
                localStorage.setItem('theme', 'dark')
            </script>
        @else
            <script>
                const loadDarkMode = () => {
                    window.theme = localStorage.getItem('theme') ?? @js(filament()->getDefaultThemeMode()->value)

                    if (
                        window.theme === 'dark' ||
                        (window.theme === 'system' &&
                            window.matchMedia('(prefers-color-scheme: dark)')
                                .matches)
                    ) {
                        document.documentElement.classList.add('dark')
                    }
                }

                loadDarkMode()

                document.addEventListener('livewire:navigated', loadDarkMode)
            </script>
        @endif

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::HEAD_END, scopes: $livewire->getRenderHookScopes()) }}
    <!-- Tailwind Play CDN: compila al vuelo las utilidades que uses -->



    </head>

    <body
        {{ $attributes
                ->merge(($livewire ?? null)?->getExtraBodyAttributes() ?? [], escape: false)
                ->class([
                    'fi-body',
                    'fi-panel-' . filament()->getId(),
                    'min-h-screen bg-gray-50 font-normal text-gray-950 antialiased dark:bg-gray-950 dark:text-white',
                ]) }}
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::BODY_START, scopes: $livewire->getRenderHookScopes()) }}

        {{ $slot }}

        @livewire(Filament\Livewire\Notifications::class)

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SCRIPTS_BEFORE, scopes: $livewire->getRenderHookScopes()) }}

        @filamentScripts(withCore: true)

        @if (filament()->hasBroadcasting() && config('filament.broadcasting.echo'))

        {{-- DEBUG! --}}
<div style="position: fixed; bottom: 0; left: 0; background: yellow; z-index: 9999;">
    chatClientId = {{ session('chatClientId') ?? 'NULL' }}
</div>

            <script data-navigate-once>
                window.Echo = new window.EchoFactory(@js(config('filament.broadcasting.echo')))

                window.dispatchEvent(new CustomEvent('EchoLoaded'))
            </script>
        @endif

        @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
            <script>
                loadDarkMode()
            </script>
        @endif

       
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SCRIPTS_AFTER, scopes: $livewire->getRenderHookScopes()) }}
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::BODY_END, scopes: $livewire->getRenderHookScopes()) }}

        {{-- Include the chat widget --}}
        @include('filament.chat-bubble')

     @livewireScripts
    @filamentScripts
        
<script src="{{ asset('js/chat-notifications.js') }}"></script>
<style>
  /* Aseguramos que el elemento con data-client-id esté en posición relativa */
  [data-client-id] {
    position: relative;
  }
  /* Pseudoelemento “punto rojo” cuando tenga clase .has-unread */
  [data-client-id].has-unread::after {
    content: '';
    position: absolute;
    top: 4px;    /* Ajusta según tu padding/márgenes */
    right: 4px;  /* Ajusta según tu padding/márgenes */
    width: 8px;
    height: 8px;
    background: red;
    border: 2px solid white;
    border-radius: 50%;
    box-sizing: content-box;
  }
</style>

<script>
    console.log('↪ Notificaciones globales habilitadas para cliente:');
    document.addEventListener('DOMContentLoaded', () => {
        console.log('↪ Notificaciones globales habilitadas para cliente2:');
        if (!window.Echo) {
            console.error('❌ window.Echo no está definido; verifica tu configuración de Echo/Pusher.');
            return;
        }

        // ID del usuario actual para evitar marcar sus propios envíos
        window.currentUserId = {{ auth()->id() }};

        // 1) Si no existe globalChatClientIds o está vacío, no suscribimos nada.
        const clientIds = @json($globalChatClientIds ?? []);
        if (!Array.isArray(clientIds) || clientIds.length === 0) {
            console.log('ℹ️ El usuario no tiene chats previos o globalChatClientIds está vacío.');
            return;
        }

        // 2) Pedir permiso para notificaciones si no se hizo
        if (window.Notification && Notification.permission !== 'granted') {
            Notification.requestPermission().then(permission => {
                console.log('Permiso notificaciones:', permission);
            });
        }

        // 3) Crear instancia única de sonido
        const notificationSound = new Audio('https://notificationsounds.com/storage/sounds/file-sounds-1150-pristine.mp3');
        notificationSound.volume = 1.0;

        // 4) Verificar mensajes no leídos (solo los que NO envió currentUserId)
        function hasUnreadMessages(clientId) {
            return fetch(`/chats/${clientId}/unread-count`)
                .then(res => res.json())
                .then(data => data.unread > 0)
                .catch(() => false);
        }

        // 5) Suscribir a canal por clientId
        function subscribeToClient(clientId) {
            if (!clientId || window['__chatSubscribed_' + clientId]) return;
            window['__chatSubscribed_' + clientId] = true;

            const channelName = `private-chat.cliente.${clientId}`;
            console.log('↪ Suscribiéndome al canal:', channelName);

            window.Echo.channel(channelName).listen('.mensaje-nuevo', async (e) => {
                console.log('🔔 Nuevo mensaje en canal', channelName, e);

                // Ignorar si el remitente es quien actualmente ve
                if (e.message.user.id === window.currentUserId) return;

                // a) Asegurar interacción para sonido
                document.addEventListener('click', () => {
                    notificationSound.play().catch(() => {});
                }, { once: true });
                notificationSound.play().catch(() => {});

                // b) Extraer datos
                let remitente = e.message.user.name || 'Desconocido';
                let texto = e.message.content || '';

                // c) Revisar si hay no leídos
                const unread = await hasUnreadMessages(clientId);
                if (unread) {
                    const iconWrapper = document.querySelector(`[data-client-id="${clientId}"]`);
                    if (iconWrapper) {
                        iconWrapper.classList.add('has-unread');
                    }
                }

                // d) Verificar si el widget de chat para este clientId está oculto
                const widgetEl = document.getElementById('chat-widget');
                const isHidden = !widgetEl
                                || !widgetEl.__x
                                || !widgetEl.__x.$data.open
                                || widgetEl.__x.$data.clientId != clientId;

                // e) Si está oculto/minimizado, crear notificación clickeable
                if (isHidden && window.Notification && Notification.permission === 'granted') {
                    const notif = new Notification(`💬 Nuevo mensaje de ${remitente}`, {
                        body: texto,
                        icon: 'https://cdn-icons-png.flaticon.com/512/2462/2462719.png',
                        badge: 'https://cdn-icons-png.flaticon.com/512/189/189792.png',
                        requireInteraction: true
                    });
                    notif.onclick = () => {
                        // i) Enfocar la ventana
                        window.focus();

                        // ii) Cerrar cualquier modal abierto (todos los botones .fi-modal-close-btn)
                        document.querySelectorAll('.fi-modal-close-btn').forEach(btn => btn.click());

                        // iii) Esperar un tiempo mayor para que Livewire desmonte el componente anterior
                        setTimeout(() => {
                            // iv) Simular click en el ícono del chat correcto
                            const button = document.querySelector(`[data-client-id="${clientId}"]`);
                            if (button) button.click();

                            // v) Hacer scroll al fondo del chat
                            setTimeout(() => {
                                const chatContainer = document.getElementById('messages-container');
                                if (chatContainer) {
                                    chatContainer.scrollTop = chatContainer.scrollHeight;
                                }
                            }, 300);
                        }, 2000); // ← 500 ms en lugar de 100 ms para evitar “Snapshot missing”
                    };
                }
                            });
        }

        // 6) Suscribir a todos los chats del usuario
        clientIds.forEach(id => subscribeToClient(id));
    });
</script>



   
    </body>
</html>
