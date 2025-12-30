{{-- Chat Modal - Estilo oscuro completo con burbujas --}}
<div
    x-data="{
        minimized: false,
        initChat() {
            console.log('Chat modal inicializado para cliente:', '{{ $clientId }}');
        }
    }"
    x-init="initChat()"
    data-client-id="{{ $clientId }}"
    class="chat-modal-wrapper flex flex-col rounded-2xl shadow-2xl overflow-hidden transition-all duration-300 border border-gray-700 bg-gray-900"
    x-bind:class="minimized ? 'w-52 h-12' : 'w-full max-w-2xl h-[600px]'"
>
    {{-- HEADER --}}
    <!-- <div class="flex items-center justify-between px-4 py-2 bg-gray-800 border-b border-gray-700"> -->
        <!-- <span class="font-semibold text-gray-200 text-sm tracking-wide">💬 Chat</span> -->
        <!-- <button
            type="button"
            @click="minimized = !minimized"
            class="text-gray-300 hover:text-green-400 focus:outline-none transition"
        > -->
            <!-- <span
                class="text-xl font-bold leading-none"
                x-text="minimized ? '+' : '–'"
            ></span>
        </button> -->
    <!-- </div> -->

    {{-- CONTENIDO --}}

        {{-- Aquí se inyectan los mensajes reales de Livewire --}}
        @livewire('chat-messages', ['clientId' => $clientId])
    </div>

    {{-- FOOTER --}}
    <div x-show="! minimized" x-transition class="bg-gray-800 p-3 border-t border-gray-700">
        <div class="flex items-center space-x-2">
            <input
                type="text"
                placeholder="Escribe un mensaje..."
                class="flex-1 rounded-lg bg-gray-700 border border-gray-600 placeholder-gray-400 text-gray-200 focus:ring-2 focus:ring-green-500 focus:outline-none px-3 py-2 text-sm"
            />
            <button class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-500 text-white text-sm font-medium transition">
                Enviar
            </button>
        </div>
    </div>
</div>

{{-- Scrollbar y animaciones --}}
<style>
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #2f2f2f;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #3f3f3f;
}
</style>
