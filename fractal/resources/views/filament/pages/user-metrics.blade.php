<x-filament-panels::page>
    {{-- 🔹 Filtro global de rango de fechas (estilo verde, inputs transparentes) --}}
<div class="mb-6">
    <div
        class="rounded-xl p-4 md:p-5 bg-emerald-600 text-emerald-50 ring-1 ring-emerald-300/50
               dark:bg-emerald-900 dark:text-emerald-100 dark:ring-emerald-700/60 shadow-sm">
        <div class="flex flex-col md:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-3 w-full md:w-auto">
                <span class="inline-flex items-center gap-2 font-medium text-sm">
                    {{-- Icono + título --}}
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 opacity-90" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M7 2a1 1 0 0 1 1 1v1h8V3a1 1 0 1 1 2 0v1h1a3 3 0 0 1 3 3v11a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h1V3a1 1 0 0 1 1-1Zm14 10H3v7a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-7ZM20 7a1 1 0 0 0-1-1h-1v1a1 1 0 1 1-2 0V6H9v1a1 1 0 0 1-2 0V6H6a1 1 0 0 0-1 1v3h15V7Z"/>
                    </svg>
                    Rango de fechas
                </span>

                {{-- Inputs de fecha TRANSPARENTES --}}
                <input
                    type="date"
                    class="fi-input w-40 rounded-lg border border-emerald-300/60 bg-transparent
                           text-emerald-900 placeholder-emerald-900/70
                           focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-200
                           dark:text-emerald-50 dark:placeholder-emerald-100/70 dark:border-emerald-700/70
                           dark:focus:ring-emerald-500 dark:focus:border-emerald-500
                           appearance-none"
                    style="background-color: transparent !important;"
                    wire:model.live="dateStart"
                />
                <span class="opacity-80">—</span>
                <input
                    type="date"
                    class="fi-input w-40 rounded-lg border border-emerald-300/60 bg-transparent
                           text-emerald-900 placeholder-emerald-900/70
                           focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-200
                           dark:text-emerald-50 dark:placeholder-emerald-100/70 dark:border-emerald-700/70
                           dark:focus:ring-emerald-500 dark:focus:border-emerald-500
                           appearance-none"
                    style="background-color: transparent !important;"
                    wire:model.live="dateEnd"
                />

                {{-- 🔘 Botones: van AQUÍ, justo después del "Aplicar" original, como HERMANOS --}}
                <x-filament::button color="success" wire:click="applyDateRange" class="whitespace-nowrap">
                    Aplicar
                </x-filament::button>

                <x-filament::button
                    wire:click="clearFilter"
                    class="whitespace-nowrap bg-transparent border border-white/60 text-white hover:bg-white/10
                           dark:border-emerald-300/40 dark:text-emerald-50"
                >
                    Borrar filtro
                </x-filament::button>

                <x-filament::button
                    wire:click="resetLast30"
                    class="whitespace-nowrap bg-emerald-700 hover:bg-emerald-800 text-emerald-50 border-none
                           dark:bg-emerald-600 dark:hover:bg-emerald-700"
                >
                    30 días
                </x-filament::button>
            </div>
        </div>
    </div>
</div>


    {{-- 🔹 Tus widgets --}}
    <x-filament-widgets::widgets :widgets="[
        \App\Filament\Pages\UserMetrics\Widgets\UserMetricsStats::class,
        \App\Filament\Pages\UserMetrics\Widgets\ClientsPerDayChart::class,
        \App\Filament\Pages\UserMetrics\Widgets\EstadoCreditoBarChart::class,
        \App\Filament\Pages\UserMetrics\Widgets\TopChannelsPieChart::class,
    ]" />

    {{-- Ajustes del ícono del datepicker (evita fondo blanco del indicador) --}}
    <style>
        /* Ícono del calendario transparente y legible en dark */
        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(0);
            opacity: .9;
            background: transparent;
        }
        @media (prefers-color-scheme: dark) {
            input[type="date"]::-webkit-calendar-picker-indicator {
                filter: invert(1);
            }
        }
        /* Quitar fondo azul de autofill en algunos navegadores */
        input[type="date"]:-webkit-autofill,
        input[type="date"]:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0px 1000px transparent inset;
            -webkit-text-fill-color: inherit;
            transition: background-color 99999s ease-in-out 0s;
        }
    </style>
</x-filament-panels::page>
