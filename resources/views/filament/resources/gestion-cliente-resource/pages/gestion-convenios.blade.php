<x-filament-panels::page>
    <div class="filament-forms-container space-y-6">
        <h2 class="text-lg font-bold mb-4">Datos del Convenio</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="font-semibold">Nombre:</label>
                <div>{{ $this->nombre }}</div>
            </div>
            <div>
                <label class="font-semibold">Cédula:</label>
                <div>{{ $this->cedula }}</div>
            </div>
            <div>
                <label class="font-semibold">Email:</label>
                <div>{{ $this->email }}</div>
            </div>
            <div>
                <label class="font-semibold">Teléfono:</label>
                <div>{{ $this->telefono }}</div>
            </div>
        </div>
    </div>
</x-filament-panels::page> 