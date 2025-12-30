<div
  id="chat-widget"
  x-data="chatWidget()"
  x-on:open-chat.window="clientId = $event.detail.clientId; open = true; document.body.classList.add('chat-open')"
  @click.away="handleClickAway($event);"
  x-show="open"
  x-transition:enter="transition ease-out duration-300"
  x-transition:enter-start="opacity-0 transform scale-95"
  x-transition:enter-end="opacity-100 transform scale-100"
  x-transition:leave="transition ease-in duration-200"
  x-transition:leave-start="opacity-100 transform scale-100"
  x-transition:leave-end="opacity-0 transform scale-95"
  class="fixed bottom-6 right-6 w-80 h-80 bg-white dark:bg-gray-800 rounded-full shadow-2xl overflow-hidden border border-gray-200 dark:border-gray-700 z-50 pointer-events-auto"
  style="display: none; position: fixed; z-index: 9999;"
>
  {{-- Header circular --}}
  <div class="flex items-center justify-between bg-gradient-to-r from-blue-600 to-blue-700 text-white px-3 py-2 rounded-t-full">
    <div class="flex items-center space-x-2">
      <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
      <span class="font-semibold text-xs" x-text="clientName || 'Chat'"></span>
    </div>
    <button @click="closeBubble()"
            class="text-white hover:bg-red-600 p-1 rounded-full transition-colors duration-200"
            title="Cerrar">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
      </svg>
    </button>
  </div>

  {{-- Contenido del Chat --}}
  <div x-show="!isMinimized" class="flex flex-col" style="height: calc(100% - 3rem);">
    {{-- Área de Mensajes --}}
    <div class="flex-1 overflow-y-auto p-3 space-y-2 bg-gray-50 dark:bg-gray-900 rounded-b-full" id="chat-messages-container" style="max-height: calc(100% - 4rem);">
      <div x-show="messages.length === 0" class="text-center text-gray-500 py-6">
        <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
        </svg>
        <p class="text-xs">No hay mensajes</p>
        <p class="text-xs mt-1 opacity-70">Escribe para comenzar</p>
      </div>

      <template x-for="message in messages" :key="message.id">
        <div class="flex" :class="message.sender_type === 'user' ? 'justify-end' : 'justify-start'">
          <div class="max-w-[70%] px-3 py-2 rounded-2xl shadow-sm text-xs"
               :class="message.sender_type === 'user'
                 ? 'bg-blue-600 text-white rounded-br-md'
                 : 'bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-600 rounded-bl-md'">
            <p class="text-xs leading-relaxed" x-text="message.message"></p>
            <p class="text-xs mt-1 opacity-70" x-text="formatTime(message.created_at)"></p>
          </div>
        </div>
      </template>
    </div>

    {{-- Formulario de Envío --}}
    <div class="p-3 bg-white dark:bg-gray-800 rounded-b-full">
      <form @submit.prevent="sendMessage()" class="flex space-x-2">
        <div class="flex-1">
          <input
            x-model="newMessage"
            @keydown.enter.prevent="sendMessage()"
            type="text"
            placeholder="Escribe tu mensaje..."
            :disabled="!clientId"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-full focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 disabled:opacity-50 disabled:cursor-not-allowed text-xs"
          >
        </div>
        <button
          type="submit"
          :disabled="!clientId || !newMessage.trim()"
          class="p-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-full transition-colors duration-200 flex items-center justify-center"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
          </svg>
        </button>
      </form>
    </div>
  </div>

  {{-- Chat Minimizado --}}
  <div x-show="isMinimized"
       @click="expandBubble()"
       class="flex items-center justify-center h-full text-center p-4 cursor-pointer"
       title="Haz clic para abrir el chat">
    <div>
      <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-2 relative">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
        </svg>
        <!-- Indicador de nuevos mensajes -->
        <div x-show="messages.length > 0" class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full flex items-center justify-center">
          <span class="text-xs text-white font-bold" x-text="messages.length > 9 ? '9+' : messages.length"></span>
        </div>
      </div>
      <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Chat</p>
    </div>
  </div>
</div>

<script>
function chatWidget() {
  return {
    open: false,
    clientId: null,
    clientName: '',
    messages: [],
    newMessage: '',
    isMinimized: false,

    init() {
      // Escuchar eventos de Livewire
      Livewire.on('refreshMessages', () => {
        this.loadMessages();
      });

      // Auto-scroll cuando se cargan nuevos mensajes
      this.$watch('messages', () => {
        this.$nextTick(() => {
          const container = document.getElementById('chat-messages-container');
          if (container) {
            container.scrollTop = container.scrollHeight;
          }
        });
      });

      // Asegurar que la página principal siga siendo interactiva
      this.$watch('open', (isOpen) => {
        if (isOpen) {
          document.body.classList.add('chat-open');
          // Permitir interacción con elementos debajo del chat
          document.addEventListener('click', this.handleDocumentClick, true);
        } else {
          document.body.classList.remove('chat-open');
          document.removeEventListener('click', this.handleDocumentClick, true);
        }
      });
    },

    handleDocumentClick(event) {
      // Solo permitir interacción con elementos específicos de Filament
      const filamentElements = event.target.closest('.fi-topbar, .fi-sidebar, .fi-page, .fi-modal, [role="dialog"], [role="menu"], button, input, select, textarea, a');
      if (filamentElements) {
        // Permitir que el evento continúe normalmente
        return;
      }
    },

    handleClickAway(event) {
      // No cerrar el chat si se hizo clic en elementos de Filament
      const filamentElements = event.target.closest('.fi-topbar, .fi-sidebar, .fi-page, .fi-modal, [data-controller]');
      if (filamentElements) {
        // Prevenir que se cierre el chat
        event.stopPropagation();
        return;
      }

      // Solo cerrar si realmente se hizo clic fuera del chat
      this.open = false;
      document.body.classList.remove('chat-open');
    },

    toggleMinimize() {
      this.isMinimized = !this.isMinimized;
      // Si se está minimizando, cerrar completamente
      if (this.isMinimized) {
        this.closeBubble();
      }
    },

    // Método para expandir la burbuja
    expandBubble() {
      this.isMinimized = false;
      this.open = true;
      document.body.classList.add('chat-open');
    },

    // Método para cerrar completamente
    closeBubble() {
      this.open = false;
      this.isMinimized = false;
      document.body.classList.remove('chat-open');
    },

    async loadMessages() {
      if (!this.clientId) return;

      try {
        const response = await fetch(`/admin/chat/messages/${this.clientId}`);
        const data = await response.json();
        this.messages = data.messages || [];
        this.clientName = data.clientName || 'Cliente';
      } catch (error) {
        console.error('Error cargando mensajes:', error);
      }
    },

    async sendMessage() {
      if (!this.newMessage.trim() || !this.clientId) return;

      try {
        const response = await fetch('/admin/chat/send', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            clientId: this.clientId,
            message: this.newMessage.trim()
          })
        });

        if (response.ok) {
          this.newMessage = '';
          this.loadMessages();
        }
      } catch (error) {
        console.error('Error enviando mensaje:', error);
      }
    },

    formatTime(dateString) {
      const date = new Date(dateString);
      return date.toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit'
      });
    }
  }
}
</script>

@push('scripts')
<script>
document.addEventListener('livewire:loaded', () => {
  // Función global para abrir el chat
  window.openChat = function(clientId, clientName = null) {
    const chatWidget = Alpine.store('chatWidget') || {
      clientId: clientId,
      clientName: clientName,
      open: true
    };

    // Emitir evento personalizado
    window.dispatchEvent(new CustomEvent('open-chat', {
      detail: { clientId, clientName }
    }));
  };
});
</script>
@endpush

@push('styles')
<style>
/* Burbuja de chat completamente independiente - NO AFECTA EL FONDO */
#chat-widget {
  position: fixed !important;
  z-index: 9999 !important;
  pointer-events: auto;
  transition: all 0.3s ease;
  /* Asegurar que no afecte el layout principal */
  contain: layout style paint;
  will-change: transform;
  /* El fondo permanece completamente fijo e interactivo */
}

/* Permitir interacción con elementos debajo del chat cuando está minimizado */
#chat-widget[x-data*="isMinimized"][x-data*="true"] {
  pointer-events: auto;
}

/* Fondo de la página principal debe permanecer fijo */
body {
  overflow-x: hidden;
  overflow-y: auto;
  position: relative;
}

body.chat-open {
  /* No cambiar el overflow del body para mantener el fondo fijo */
  position: static;
}

/* Asegurar que el chat esté completamente por encima */
#chat-widget {
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.05);
}

/* Estilos específicos para la burbuja circular */
#chat-widget .rounded-full {
  border-radius: 50% !important;
}

#chat-widget .rounded-t-full {
  border-radius: 50% 50% 0 0 !important;
}

#chat-widget .rounded-b-full {
  border-radius: 0 0 50% 50% !important;
}

/* Burbuja minimizada */
#chat-widget[x-data*="isMinimized"][x-data*="true"] {
  width: 4rem !important;
  height: 4rem !important;
  cursor: pointer;
  transform: scale(1);
}

#chat-widget[x-data*="isMinimized"][x-data*="true"]:hover {
  transform: scale(1.1);
}

/* Responsive: en móviles, hacer el chat más pequeño */
@media (max-width: 768px) {
  #chat-widget {
    width: 280px !important;
    height: 280px !important;
    right: 1rem !important;
    bottom: 1rem !important;
  }

  #chat-widget[x-data*="isMinimized"][x-data*="true"] {
    width: 3rem !important;
    height: 3rem !important;
  }
}

/* Mejorar la accesibilidad */
#chat-widget button:focus {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
}

/* Asegurar que el chat no interfiera con dropdowns y modales */
#chat-widget {
  isolation: isolate;
}

/* Animación de pulso para la burbuja minimizada */
#chat-widget[x-data*="isMinimized"][x-data*="true"] .bg-blue-600 {
  animation: pulse-glow 2s infinite;
}

@keyframes pulse-glow {
  0%, 100% {
    box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7);
  }
  50% {
    box-shadow: 0 0 0 10px rgba(59, 130, 246, 0);
  }
}

/* Scrollbar personalizado para la burbuja */
#chat-messages-container::-webkit-scrollbar {
  width: 4px;
}

#chat-messages-container::-webkit-scrollbar-track {
  background: transparent;
}

#chat-messages-container::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.5);
  border-radius: 2px;
}

#chat-messages-container::-webkit-scrollbar-thumb:hover {
  background: rgba(156, 163, 175, 0.7);
}

/* Prevenir que el chat afecte el layout principal */
#chat-widget {
  /* Crear un nuevo contexto de apilamiento */
  contain: layout style paint;
  /* Asegurar que no cause reflow en el documento principal */
  transform: translateZ(0);
}

/* Overlay para evitar interacción con el fondo cuando el chat está abierto */
#chat-widget::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: transparent;
  z-index: -1;
  pointer-events: none;
}
</style>
@endpush
