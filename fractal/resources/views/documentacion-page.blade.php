@php
    // Variables pasadas: $cliente, $imagenes
@endphp

<x-layouts.app :title="'Documentación Cliente #'.$cliente->id_cliente">
    <div class="container mx-auto py-8">

        {{-- Confirmaciones --}}
        <div class="mb-6 p-4 bg-yellow-100 text-yellow-800 rounded">
            🚩 <strong>documentacion-page.blade.php</strong> cargada correctamente.
        </div>
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
            Clase ClienteDocumentacionStandalone existe?
            {{ class_exists(\App\Http\Livewire\ClienteDocumentacionStandalone::class) ? '✅ Sí' : '❌ No' }}
        </div>

        {{-- Montamos tu Livewire --}}
        <livewire:cliente-documentacion-standalone
            :cliente="$cliente"
            :imagenes="$imagenes"
        />
    </div>
</x-layouts.app>
