<?php

namespace App\Providers\Filament;

// ===== Imports base de Filament Panel =====
use Filament\Panel;                    // Objeto “Panel” (el panel de administración)
use Filament\PanelProvider;            // Proveedor que construye/configura el panel
use Filament\Support\Colors\Color;     // Paleta de colores para el tema
use Filament\Support\Assets\Css;       // Registrar assets CSS externos
use Filament\Support\Assets\Js;        // Registrar assets JS externos

// ===== Middlewares propios de Filament =====
use Filament\Http\Middleware\Authenticate;                 // Protege rutas del panel
use Filament\Http\Middleware\AuthenticateSession;          // Gestión de sesión
use Filament\Http\Middleware\DisableBladeIconComponents;   // Optimiza iconos Blade
use Filament\Http\Middleware\DispatchServingFilamentEvent; // Hook “serving” de Filament

// ===== Utilidades de Laravel/Vite/HTML =====
use Illuminate\Support\Facades\Vite;   // Cargar assets con Vite (compilados)
use Illuminate\Support\HtmlString;     // Devolver HTML crudo en hooks
use Illuminate\Routing\Middleware\SubstituteBindings; // Route model binding
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken; // CSRF
use Illuminate\Cookie\Middleware\EncryptCookies;           // Encriptar cookies
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse; // Cola cookies
use Illuminate\Session\Middleware\StartSession;            // Iniciar sesión
use Illuminate\View\Middleware\ShareErrorsFromSession;     // Errores a vistas

// ===== Plugins =====
use BezhanSalleh\FilamentShield\FilamentShieldPlugin; // Control de permisos/roles

// ===== Hooks de render de Filament v3 =====
use Filament\View\PanelsRenderHook; // Permite inyectar vistas/HTML en lugares del layout

class AdminPanelProvider extends PanelProvider
{
    /**
     * Método requerido por PanelProvider para construir el Panel.
     * Aquí defines todo: rutas, middlewares, widgets, colores, assets, hooks, etc.
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            // === Identidad y ruta base del panel ===
            ->default()               // Marca este panel como “por defecto” (si tuvieses varios)
            ->id('admin')             // ID interno del panel (para multi-panel)
            ->path('admin')           // Prefijo de URL: /admin
            ->authGuard('web')        // Guard de autenticación a usar
            ->login()                 // Usa la página de login por defecto de Filament

            // === Tema (colores) ===
            ->colors([
                'primary' => Color::Amber, // Color “primary” del panel (botones, acentos)
            ])

            // === Descubrimiento automático ===
            // Busca automáticamente clases en estos directorios y las registra:
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')

            // === Páginas de nivel de panel ===
            ->pages([
                \App\Filament\Pages\Dashboard::class, // Dashboard como página disponible en el panel
            ])

            // === Widgets globales que aparecen en el panel ===
            ->widgets([
                \Filament\Widgets\AccountWidget::class,     // Widget de cuenta/usuario
                \Filament\Widgets\FilamentInfoWidget::class,// Info de Filament (versión, etc.)
            ])

            // === Middlewares de la “web stack” del panel ===
            ->middleware([
                EncryptCookies::class,                 // Encripta cookies
                AddQueuedCookiesToResponse::class,     // Agrega cookies pendientes a la respuesta
                StartSession::class,                   // Inicia sesión de usuario
                ShareErrorsFromSession::class,         // Comparte errores de validación a vistas
                VerifyCsrfToken::class,                // Protege contra CSRF
                SubstituteBindings::class,             // Route Model Binding
                DispatchServingFilamentEvent::class,   // Dispara el evento “serving” de Filament
                AuthenticateSession::class,            // Asegura la sesión autenticada
                DisableBladeIconComponents::class,     // Evita registrar iconos Blade como componentes
            ])

            // === Middleware de auth específico del panel ===
            ->authMiddleware([
                Authenticate::class, // Si no estás logueado, te redirige a login del panel
            ])

            // === Plugins del panel ===
            ->plugins([
                FilamentShieldPlugin::make(), // Permisos/roles (ver, crear, editar, borrar recursos)
            ])

            // === Recursos explícitos (además del discover) ===
            ->resources([
                \App\Filament\Resources\ClienteResource::class, // Asegura que ClienteResource esté registrado
            ])

            // === Assets estáticos que quieres cargar en TODO el panel ===
            ->assets([
                // CSS propio (public/css/custom.css)
                Css::make('custom-styles', asset('css/custom.css')),
                // JS compilado con Vite (resources/js/app.js → public/build/...):
                Js::make('app-scripts', Vite::asset('resources/js/app.js')),
            ])

            // === Hooks de render: inyectan contenido al final del <body> del layout del panel ===

            // 1) Monta el Livewire del Chat (burbuja/panel) al final del body
            //    La vista 'filament.widgets.chat-widget-mount' debe contener:
            //    <livewire:filament.widgets.chat-widget />
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.widgets.chat-widget-mount')
            )

            // 2) Expone el ID del usuario actual a window.currentUserId (para JS)
            //    y carga un script propio de notificaciones (public/js/chat-notifications.js)
            ->renderHook(
                PanelsRenderHook::BODY_END,
                function () {
                    return new HtmlString(
                        '<script>window.currentUserId = ' . json_encode(auth()->id()) . ';</script>' .
                        '<script src="' . asset('js/chat-notifications.js') . '" defer></script>'
                    );
                }
            )

            // 3) Inyecta el script de drag-to-scroll para tablas (Blade parcial)
            //    La vista 'components.dragscroll' debe contener el <script> con la lógica
            //    que marca contenedores scrolleables y agrega el gesto de arrastre.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('components.dragscroll')
            );
    }
}
