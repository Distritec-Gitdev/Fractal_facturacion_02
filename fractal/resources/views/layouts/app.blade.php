<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Distritec Fractal') }}</title>

    {{-- SEO & Seguridad --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Carga los estilos y scripts desde Vite --}}
    @vite('resources/js/app.js')

    {{-- Estilos Livewire (si estás usando componentes Livewire/Filament personalizados) --}}
    @livewireStyles
    @filamentStyles

    {{-- Estilos personalizados del Chat Widget --}}
    <link rel="stylesheet" href="{{ asset('css/chat-widget.css') }}">
</head>
<body class="antialiased bg-gray-100 text-gray-900">

    {{-- Contenido de la página --}}
    @yield('content')

    {{-- Scripts Livewire --}}
    @livewireScripts

    {{-- Scripts adicionales opcionales --}}
    <script>
        console.log('✅ Layout cargado correctamente (producción)');
    </script>
</body>
</html>
