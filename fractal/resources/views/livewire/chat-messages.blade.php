<div class="chat-messages-container h-full flex flex-col bg-gray-900 text-white">
    {{-- Header --}}
    <div class="flex items-center justify-between p-3 bg-gray-800 border-b border-gray-700">
        <div class="flex items-center space-x-2">
            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
            <span class="text-sm font-medium">
                {{ $clientId ? 'Chat con Cliente #' . $clientId : 'Selecciona un cliente' }}
            </span>
        </div>
        <button
            wire:click="toggleMinimize"
            class="text-gray-400 hover:text-white text-lg font-bold"
        >
            {{ $isMinimized ? '+' : '−' }}
        </button>
    </div>

    {{-- Messages Area --}}
    <div
        id="chatMessagesBox"
        class="flex-1 overflow-y-auto p-4 space-y-3 {{ $isMinimized ? 'hidden' : '' }}"
        style="max-height: 400px;"
    >
        @if(count($messages) > 0)
            @foreach($messages as $message)
                <div class="flex {{ ($message['sender_type'] ?? 'user') === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg {{
                        ($message['sender_type'] ?? 'user') === 'user'
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-700 text-gray-100'
                    }}">
                        <p class="text-sm">{{ $message['message'] }}</p>
                        <p class="text-xs mt-1 opacity-70">
                            {{ \Carbon\Carbon::parse($message['created_at'])->format('H:i') }}
                        </p>
                    </div>
                </div>
            @endforeach
        @else
            <div class="text-center text-gray-400 py-8">
                <p class="text-sm">No hay mensajes aún</p>
                <p class="text-xs mt-1">Escribe un mensaje para comenzar</p>
            </div>
        @endif
    </div>

    {{-- Message Input --}}
    @if(!$isMinimized)
        <div class="p-3 bg-gray-800 border-t border-gray-700">
            <form wire:submit.prevent="sendMessage" class="flex space-x-2">
                <input
                    type="text"
                    wire:model="message"
                    placeholder="Escribe tu mensaje..."
                    class="flex-1 px-3 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-blue-500 focus:outline-none text-sm"
                    wire:keydown.enter.prevent="sendMessage"
                >
                <button
                    type="submit"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors"
                    {{ empty($message) || !$clientId ? 'disabled' : '' }}
                >
                    Enviar
                </button>
            </form>
        </div>
    @endif

    {{-- Loading indicator --}}
    <div wire:loading.delay class="absolute top-0 left-0 right-0 bg-blue-600 text-white text-center py-1 text-xs">
        Enviando...
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('livewire:load', () => {
    const scrollBottom = () => {
        const box = document.getElementById('chatMessagesBox');
        if (box) box.scrollTop = box.scrollHeight;
    };

    // cuando el backend te diga "se actualizaron mensajes"
    Livewire.on('refreshMessages', () => {
        scrollBottom();
    });

    // scroll inicial
    setTimeout(scrollBottom, 100);
});
</script>
@endpush
