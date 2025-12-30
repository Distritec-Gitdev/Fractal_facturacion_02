{{-- resources/views/filament/widgets/chat-widget.blade.php --}}
@php
    if (!defined('_CHAT_WIDGET_RENDERED')) define('_CHAT_WIDGET_RENDERED', true);
@endphp

<div
    class="chat-widget-root"
    x-data="{
        minimized: @entangle('isMinimized'),
        closed: @entangle('isClosed'),
    }"
    x-cloak
>
    <div
        x-show="!closed && !minimized"
        x-transition.opacity.scale.origin.bottom.right
        class="cwr__panel rounded-2xl overflow-hidden shadow-2xl border relative"
        style="position:fixed;right:24px;bottom:24px;width:360px;max-width:92vw;z-index:2147483647;background:#000;color:#fff;border:1px solid #2a2a2a;box-shadow:0 16px 40px rgba(0,0,0,.6);"
        x-init="$nextTick(() => {
            const c = document.getElementById('chat-messages-container');
            if (c) c.scrollTop = c.scrollHeight;
            window.Livewire?.dispatch('markVisibleAsRead');
        })"
    >
        <div
            wire:loading.delay
            wire:target="sendMessage"
            class="absolute inset-0 bg-black/40 grid place-items-center z-50"
            style="backdrop-filter: blur(2px);"
        >
            <div class="animate-pulse text-sm text-white/90">Enviando…</div>
        </div>

        <div class="cwr__header flex items-center justify-between px-4 py-3 border-b" style="background:#000;color:#fff;border-color:#2a2a2a;">
            <div class="flex items-center gap-2 min-w-0">
                <span class="inline-flex w-2 h-2 rounded-full" style="background:#22c55e;"></span>
                <span class="font-semibold text-sm tracking-wide truncate">{{ $this->getClientName() }}</span>
            </div>
            <div class="flex items-center gap-1">
                <button wire:click="toggleMinimize" class="inline-flex items-center justify-center w-9 h-9 rounded-lg transition" title="Minimizar" style="color:#e5e7eb;background:transparent;border:none;">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"/></svg>
                </button>
                <button wire:click="closeHard" class="inline-flex items-center justify-center w-9 h-9 rounded-lg transition" title="Cerrar" style="color:#e5e7eb;background:transparent;border:none;">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <div
            id="chat-messages-container"
            class="p-4 space-y-3 overflow-y-auto custom-scrollbar"
            style="max-height:60vh;background:#0b0b0b;color:#fff;"
            x-on:scroll.debounce.200ms="
                const el = $event.target;
                const nearBottom = (el.scrollHeight - el.scrollTop - el.clientHeight) < 10;
                if (nearBottom) { window.Livewire?.dispatch('markVisibleAsRead'); }
            "
        >
            @if(count($messages))
                @foreach($messages as $i => $message)
                    @php
                        $isMine = (bool)($message['is_mine'] ?? false);
                        $senderName = $message['sender_name'] ?? ($isMine ? (auth()->user()->name ?? 'Yo') : 'Cliente');

                        $msgId    = (int)($message['id'] ?? 0);
                        $peerLast = (int)($peerLastSeenId ?? 0);
                        $myLast   = (int)($myLastSeenId ?? 0);

                        $readMine     = $isMine && $msgId > 0 && $msgId <= $peerLast;
                        $readIncoming = (!$isMine) && $msgId > 0 && $msgId <= $myLast;
                        $showDouble   = $isMine ? $readMine : $readIncoming;

                        $time = $message['time'] ?? '';
                        if ($time === '') {
                            try { $time = \Carbon\Carbon::parse($message['created_at'])->format('H:i'); }
                            catch (\Throwable $e) { $time = ''; }
                        }

                        $doubleColor = '#3b82f6';
                        $singleColor = '#9ca3af';
                    @endphp

                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}" wire:key="chat-{{ (int)($clientId ?? 0) }}-msg-{{ $message['id'] ?? ('tmp-'.$i) }}">
                        <div class="max-w-[80%] px-4 py-2 rounded-2xl shadow-sm ring-1 ring-inset"
                             style="{{ $isMine
                                ? 'background:#16a34a;color:#fff;border-bottom-right-radius:4px;box-shadow:0 6px 18px rgba(22,163,74,.35);'
                                : 'background:#1f2937;color:#e5e7eb;border-bottom-left-radius:4px;box-shadow:0 6px 18px rgba(0,0,0,.35);'
                             }}border:1px solid rgba(255,255,255,.06);">

                            <div style="font-size:10px;opacity:.7;margin-bottom:4px; {{ $isMine ? 'text-align:right;' : 'text-align:left;' }}">
                                {{ $senderName }}
                            </div>

                            <p style="font-size:13px;line-height:1.45;word-wrap:anywhere;">{{ $message['message'] }}</p>

                            <p style="font-size:10px;margin-top:6px;opacity:.85;text-align:right;display:flex;gap:6px;align-items:center;justify-content:flex-end;">
                                <span>{{ $time }}</span>

                                @if($isMine && $readMine)
                                    <span style="font-size:10px;color:#93c5fd;">Leído</span>
                                @endif

                                <span aria-label="{{ $showDouble ? 'Leído' : 'Enviado' }}" title="{{ $showDouble ? 'Leído' : 'Enviado' }}">
                                    @if($showDouble)
                                        <svg width="16" height="16" viewBox="0 0 24 24" style="vertical-align:middle;">
                                            <path d="M1 14l5 5L20 5" fill="none" stroke="{{ $doubleColor }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M8 14l5 5L23 7" fill="none" stroke="{{ $doubleColor }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    @else
                                        <svg width="16" height="16" viewBox="0 0 24 24" style="vertical-align:middle;">
                                            <path d="M2 12l6 6L22 4" fill="none" stroke="{{ $singleColor }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    @endif
                                </span>
                            </p>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-center" style="color:#fff;opacity:.75;padding:40px 0;">
                    <x-filament::icon name="heroicon-o-chat-bubble-left-right" class="w-12 h-12 mx-auto mb-4 opacity-50" />
                    <p class="text-sm">No hay mensajes aún</p>
                    <p class="text-xs mt-1">Escribe un mensaje para comenzar</p>
                </div>
            @endif
        </div>

        <div class="p-3 border-t" style="background:#000;color:#fff;border-color:#2a2a2a;">
            <form wire:submit.prevent="sendMessage" class="flex items-end gap-2">
                <div class="relative flex-1">
                    <input
                        type="text"
                        name="message"
                        autocomplete="off"
                        wire:model.live="data.message"
                        @keydown.enter.prevent="$el.closest('form').dispatchEvent(new Event('submit', {cancelable:true, bubbles:true}))"
                        placeholder="{{ $clientId ? 'Escribe tu mensaje…' : 'Selecciona un cliente para chatear' }}"
                        class="w-full rounded-xl border focus:ring-2 focus:outline-none px-3 py-[10px] text-sm transition"
                        @disabled(! $clientId)
                        aria-label="Escribe tu mensaje"
                        style="background:#111;border-color:#333;color:#fff;"
                    />
                    @if(($data['message'] ?? '') !== '')
                        <span class="absolute inset-0 rounded-xl pointer-events-none" style="box-shadow:0 0 0 2px rgba(34,197,94,.25);"></span>
                    @endif
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="sendMessage"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-2xl text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                    @disabled(! $clientId || empty($data['message'] ?? '')) aria-label="Enviar"
                    style="background:#22c55e;color:#fff;border:1px solid rgba(0,0,0,.2);"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12l14-7-7 14-2-5-5-2z"/></svg>
                    <span class="hidden sm:inline" wire:loading.remove wire:target="sendMessage">Enviar</span>
                    <span class="hidden sm:inline" wire:loading wire:target="sendMessage">Enviando…</span>
                </button>
            </form>
        </div>
    </div>

    <button
        x-show="!closed && minimized"
        x-transition.opacity.scale.origin.bottom.right
        wire:click="toggleMinimize"
        class="pointer-events-auto relative rounded-full w-[64px] h-[64px] shadow-xl grid place-items-center"
        style="position:fixed;right:24px;bottom:24px;z-index:2147483647;background:#22c55e;color:#fff;box-shadow:0 18px 30px rgba(34,197,94,.35);"
        title="Abrir chat" aria-label="Abrir chat"
    >
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h6m-8 5l-2 2V6a2 2 0 012-2h12a2 2 0 012 2v11a2 2 0 01-2 2H7z"/></svg>
    </button>
</div>

@once
@push('scripts')
<script>
  document.addEventListener('livewire:load', () => {
    const scrollBottom = () => {
      const c = document.getElementById('chat-messages-container');
      if (c) c.scrollTop = c.scrollHeight;
    };

    window.addEventListener('messagesLoaded', () => {
      scrollBottom();
      window.Livewire?.dispatch('markVisibleAsRead');
    });

    setTimeout(() => {
      scrollBottom();
      window.Livewire?.dispatch('markVisibleAsRead');
    }, 80);

    // Sync puntual al volver (sin poll)
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        window.Livewire?.dispatch('fetchNewMessages');
        setTimeout(() => {
          scrollBottom();
          window.Livewire?.dispatch('markVisibleAsRead');
        }, 120);
      }
    });
  });
</script>
@endpush
@endonce
