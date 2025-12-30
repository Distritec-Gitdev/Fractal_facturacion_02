<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{{ $title ?? 'Aplicación Distritec' }}</title>

    {{-- Livewire Styles --}}
    @livewireStyles

    {{-- AlpineJS --}}
  

    {{-- CSS de documentación --}}
    <link href="{{ asset('css/documentacion.css') }}" rel="stylesheet" />
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

    <main class="min-h-screen py-10 px-4 max-w-6xl mx-auto">
        {{ $slot }}
    </main>

    {{-- Livewire Scripts --}}
    @livewireScripts

    {{-- Depuración en consola --}}
    <script>
        console.log("📦 Layout cargado correctamente");
        ['component-mounted', 'component-rendered', 'validation-errors', 'documentos-guardados'].forEach(evt =>
            window.addEventListener(evt, e => console.log(`📣 ${evt}`, e.detail))
        );
    </script>
</body>
</html>
