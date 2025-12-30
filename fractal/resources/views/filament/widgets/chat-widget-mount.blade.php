{{-- resources/views/filament/widgets/chat-widget-mount.blade.php --}}
@once
    @auth
        @if (config('filament-chat-widget.enabled'))
            <style>
                .chat-portal-root{
                    position: fixed !important;
                    right: 24px !important;
                    bottom: 24px !important;
                    z-index: 2147483647 !important;
                    pointer-events: none;
                }
                .chat-portal-root > *{ pointer-events: auto; }
            </style>

            <div id="chat-portal-root" class="chat-portal-root">
                {{-- Montaje directo. Sin templates. Sin Livewire.start(). Sin magia rara. --}}
                @livewire(\App\Filament\Widgets\ChatWidget::class, [], key('chat-widget-global'))
            </div>
        @endif
    @endauth
@endonce
