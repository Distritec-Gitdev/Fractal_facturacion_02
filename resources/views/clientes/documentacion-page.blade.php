<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Documentación Cliente #{{ $cliente->id_cliente }}</title>
  @livewireStyles
  <link rel="stylesheet" href="{{ asset('css/documentacion.css') }}">
</head>
<body class="bg-gray-50">
  <div class="container mx-auto py-8">

    <h1 class="text-2xl font-bold mb-6">Documentación de {{ $cliente->ID_Cliente_Nombre }}</h1>

    @if ($successMessage)
      <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">
        {{ $successMessage }}
      </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-6">

      @foreach($this->getFieldKeys() as $key)
        @php
          $label = str_replace('_', ' ', ucfirst($key));
          $existing = $imagenes->{$key};
        @endphp

        <div class="border p-4 rounded shadow-sm">
          <label class="block font-semibold mb-2">{{ $label }}</label>

          {{-- Preview de imagen o PDF --}}
          <div class="mb-2 h-48 bg-gray-100 flex items-center justify-center overflow-hidden rounded">
            @if(isset($uploads[$key]))
              @if(Str::endsWith($uploads[$key]->getClientOriginalName(), '.pdf'))
                <div class="text-gray-700">📄 {{ $uploads[$key]->getClientOriginalName() }}</div>
              @else
                <img src="{{ $uploads[$key]->temporaryUrl() }}" class="max-h-full object-contain" />
              @endif
            @elseif($existing)
              @php $url = Storage::url("public/pdfs/{$cliente->id_cliente}/{$existing}"); @endphp
              @if(Str::endsWith($existing, '.pdf'))
                <embed src="{{ $url }}" type="application/pdf" class="w-full h-full" />
              @else
                <img src="{{ $url }}" class="max-h-full object-contain" />
              @endif
            @else
              <span class="text-gray-400">Sin archivo</span>
            @endif
          </div>

          {{-- Input --}}
          <input
            type="file"
            wire:model="uploads.{{ $key }}"
            accept=".jpg,.jpeg,.png,.pdf"
            class="block w-full text-sm text-gray-700"
          />
          @error("uploads.{$key}") 
            <p class="mt-1 text-red-600 text-sm">{{ $message }}</p> 
          @enderror
        </div>
      @endforeach

      <button type="submit"
              class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">
        Guardar Documentos
      </button>
    </form>

  </div>

  @livewireScripts
  <script src="//unpkg.com/alpinejs" defer></script>
</body>
</html>
