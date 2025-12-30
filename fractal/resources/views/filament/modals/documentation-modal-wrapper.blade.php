{{-- resources/views/filament/modals/documentation-modal-wrapper.blade.php --}}
@php
    /** @var \App\Models\Cliente   $cliente */
    /** @var \App\Models\Imagenes  $imagenes */
@endphp

{{-- Montamos el componente Livewire estándar --}}
<livewire:cliente-documentacion
    :cliente="$cliente"
    :imagenes="$imagenes"
/>

