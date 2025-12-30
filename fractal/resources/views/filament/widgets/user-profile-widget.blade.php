<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $avatarUrl = $this->getAvatarUrl();
        @endphp

        {{-- Wrapper Alpine que escucha "avatar-updated" y actualiza TODAS las imágenes de avatar --}}
        <div
            x-data
            x-on:avatar-updated.window="
                const url = $event.detail?.url;
                if (!url) return;
                // Reemplaza src de todos los avatars <img.fi-avatar ...> en el DOM
                document.querySelectorAll('img.fi-avatar').forEach(img => {
                    img.src = url;
                });
            "
            class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
        >
            {{-- IZQ: Avatar + datos --}}
            <div class="flex items-center gap-4">
                <img
                    src="{{ $avatarUrl }}"
                    alt="Avatar"
                    class="fi-avatar h-16 w-16 rounded-full object-cover ring-2 ring-white/10 cursor-pointer"
                    title="Ver foto"
                    x-on:click="$dispatch('open-modal', { id: 'preview-avatar' })"
                />

                <div class="grid">
                    <div class="text-base font-semibold">
                        {{ auth()->user()?->name }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ auth()->user()?->email }}
                    </div>

                    @if(method_exists(auth()->user(), 'roles') && auth()->user()->roles->isNotEmpty())
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach(auth()->user()->roles->pluck('name') as $role)
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {{ $role }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- DER: Acciones --}}
            <div class="flex items-center gap-2">
                <x-filament::button color="gray" icon="heroicon-m-eye"
                    x-on:click="$dispatch('open-modal', { id: 'preview-avatar' })">
                    Ver foto
                </x-filament::button>

                <x-filament::button icon="heroicon-m-pencil-square"
                    wire:click="prepareEdit">
                    Editar perfil
                </x-filament::button>
            </div>
        </div>

        {{-- MODAL: Vista previa de imagen --}}
        <x-filament::modal
            id="preview-avatar"
            width="lg"
            alignment="center"
            sticky-header
        >
            <x-slot name="heading">Foto de perfil</x-slot>

            <div class="w-full">
                <img src="{{ $this->getAvatarUrl() }}" alt="Avatar" class="w-full max-h-[70vh] object-contain rounded-xl">
            </div>

            <x-slot name="footer">
                <x-filament::button color="gray"
                    x-on:click="$dispatch('close-modal', { id: 'preview-avatar' })">
                    Cerrar
                </x-filiment::button>
            </x-slot>
        </x-filament::modal>

        {{-- MODAL: Editor de perfil --}}
        <x-filament::modal
            id="edit-profile"
            width="md"
            alignment="center"
            sticky-header
        >
            <x-slot name="heading">Editar perfil</x-slot>

            <div class="space-y-4">
                {{ $this->form }}
            </div>

            <x-slot name="footer">
                <x-filament::button color="gray"
                    x-on:click="$dispatch('close-modal', { id: 'edit-profile' })">
                    Cancelar
                </x-filament::button>

                <x-filament::button wire:click="save">
                    Guardar
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    </x-filament::section>
</x-filament-widgets::widget>
