<?php

namespace App\Filament\Widgets;

use App\Events\NewMessageSent; // ⬅️ IMPORTANTE: evento de broadcast (PASO 2)
use App\Models\Chat;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;

class ChatWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.chat-widget';
    // arriba junto a tus otras propiedades
    protected bool $isSwitching = false; // evita carreras al cambiar de cliente


    /** Estado UI / datos */
    public array $data = ['message' => ''];
    public ?int  $clientId = null;
    public array $messages = [];
    public bool  $isMinimized = true; // inicia como burbuja

    /** Listeners */
    protected $listeners = [
        'openChat'        => 'open',
        'closeChat'       => 'close',
        'toggleChat'      => 'toggleMinimize',
        'setClientId'     => 'setClient',
        'setClient'       => 'setClient',
        'refreshMessages' => 'loadMessages',
    ];

    /** Cache columnas */
    protected static ?array $chatCols = null;

    public function mount(): void
    {
        // Recupera último cliente usado (si existe) pero NO cargues mensajes aquí.
        $sessionId = (int) (session('chatClientId') ?? 0);
        if ($sessionId > 0 && $this->clientId === null) {
            $this->clientId = $sessionId;
        }

        $this->isMinimized = true;
        $this->form->fill();
        // No: $this->loadMessages();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('message')
                ->label('Mensaje')
                ->maxLength(1000)
                ->placeholder('Escribe tu mensaje...')
                ->disabled(fn () => ! $this->clientId),
        ])->statePath('data');
    }

    /* ================== Eventos UI ================== */

    #[On('openChat')]
public function open(): void
{
    if ($this->isSwitching) return;         // ⛔ si estoy cambiando, ignoro
    if ($this->isMinimized) {
        $this->isMinimized = false;
        $this->loadMessages();
    }
}

#[On('closeChat')]
public function close(): void
{
    if ($this->isSwitching) return;         // ⛔
    $this->isMinimized = true;
}

#[On('toggleChat')]
public function toggleMinimize(): void
{
    if ($this->isSwitching) return;         // ⛔
    $this->isMinimized = ! $this->isMinimized;
    if (! $this->isMinimized) {
        $this->loadMessages();
    }
}

#[On('setClientId')]
#[On('setClient')]
public function setClient(int $clientId): void
{
    if ($clientId <= 0) return;

    // ⛔ si ya estoy en esto, no reentrar
    if ($this->isSwitching) return;
    $this->isSwitching = true;

    // si ya es el mismo cliente y ya está abierto, no hagas nada más
    if ($this->clientId === $clientId && ! $this->isMinimized) {
        $this->isSwitching = false;
        return;
    }

    // 1) cambiar cliente
    $this->clientId    = $clientId;
    session()->put('chatClientId', $clientId);

    // 2) garantizar un único chat visible (abre aquí mismo)
    $this->isMinimized = false;

    // 3) cargar mensajes del nuevo
    $this->loadMessages();

    // 4) pedir al front que re-suscriba SOLO a este cliente
    //    (tu Blade ya escucha 'chat:subscribe' y hace Echo.private(...))
    $this->dispatch('chat:subscribe', id: $clientId);

    $this->isSwitching = false;
}

    #[On('refreshMessages')]
    public function loadMessages(): void
    {
        if (! $this->clientId) {
            $this->messages = [];
            return;
        }

        $this->detectChatColumns();
        $c = static::$chatCols;

        if (!($c['cliente_id'] || $c['id_cliente'] || $c['client_id'])) {
            $this->messages = [];
            return;
        }

        $rows = Chat::query()
            ->where(function ($q) use ($c) {
                if ($c['cliente_id']) $q->orWhere('cliente_id', $this->clientId);
                if ($c['id_cliente']) $q->orWhere('id_cliente', $this->clientId);
                if ($c['client_id'])  $q->orWhere('client_id',  $this->clientId);
            })
            ->orderBy('id', 'asc')
            ->get();

        $currentId = Auth::id();

        $this->messages = $rows->map(function ($m) use ($c, $currentId) {
            $texto  = $c['message'] ? $m->getAttribute('message')
                    : ($c['mensaje'] ? $m->getAttribute('mensaje') : '');

            $senderType = $c['sender_type'] ? $m->getAttribute('sender_type') : null;
            $userId     = $m->getAttribute('user_id');

            $isMine = ($userId && $currentId && (int)$userId === (int)$currentId)
                   || in_array($senderType, ['agent','admin','staff','me'], true);

            return [
                'id'          => (int) $m->id,
                'message'     => (string) ($texto ?? ''),
                'created_at'  => $m->created_at,
                'sender_type' => $senderType,
                'sender_id'   => $userId,
                'is_mine'     => $isMine,
            ];
        })->toArray();

        // Autoscroll en el navegador
        $this->dispatch('refreshMessages');
    }

    public function sendMessage(): void
    {
        $texto = trim((string) ($this->data['message'] ?? ''));
        if ($texto === '' || ! $this->clientId) return;

        $cliente = Cliente::find($this->clientId);
        if (! $cliente) {
            Notification::make()
                ->title('Error')->body('Cliente no encontrado')->danger()->send();
            return;
        }

        $this->detectChatColumns();
        $c = static::$chatCols;

        try {
            $payload = ['user_id' => Auth::id()];
            if ($c['cliente_id']) $payload['cliente_id'] = $this->clientId;
            if ($c['id_cliente']) $payload['id_cliente'] = $this->clientId;
            if ($c['client_id'])  $payload['client_id']  = $this->clientId;

            if ($c['message'])                    $payload['message']     = $texto;
            if ($c['mensaje'] && ! $c['message']) $payload['mensaje']     = $texto;
            if ($c['sender_type'])                $payload['sender_type'] = 'agent';

            $chat = Chat::create($payload);
        } catch (\Throwable $e) {
            $chat = Chat::create([
                'cliente_id' => $this->clientId,
                'id_cliente' => $this->clientId,
                'user_id'    => Auth::id(),
                'mensaje'    => $texto,
            ]);
        }

        /**
         * ====== PASO 2: emitir evento WebSocket ======
         * - Aseguramos relación 'user'
         * - Exponemos 'message' aunque la columna sea 'mensaje'
         * - Disparamos el evento que broadcast a: private-chat.cliente.{clientId}
         *   con nombre ".mensaje-nuevo"
         */
        try {
            // Asegura que venga el usuario en el payload
            $chat->loadMissing('user:id,name');

            // Normaliza por si tu tabla usa "mensaje"
            if (!isset($chat->message)) {
                $chat->message = $texto;
            }

            // Dispara el evento
            event(new NewMessageSent((int) $this->clientId, $chat));
        } catch (\Throwable $e) {
            // \Log::warning('WS broadcast failed', ['e' => $e]);
        }
        /** ====== /PASO 2 ====== */

        // Pintar de inmediato en tu UI (sin cambios)
        $this->data['message'] = '';
        $this->messages[] = [
            'id'          => (int) $chat->id,
            'message'     => $texto,
            'created_at'  => now()->toDateTimeString(),
            'sender_type' => $c['sender_type'] ? 'agent' : null,
            'sender_id'   => Auth::id(),
            'is_mine'     => true,
        ];

        $this->dispatch('refreshMessages');

        Notification::make()->title('Mensaje enviado')->success()->send();
    }

    protected function detectChatColumns(): void
    {
        if (static::$chatCols !== null) return;

        if (! Schema::hasTable('chats')) {
            static::$chatCols = [
                'cliente_id' => false,
                'id_cliente' => false,
                'client_id'  => false,
                'message'    => false,
                'mensaje'    => false,
                'sender_type'=> false,
            ];
            return;
        }

        static::$chatCols = [
            'cliente_id' => Schema::hasColumn('chats', 'cliente_id'),
            'id_cliente' => Schema::hasColumn('chats', 'id_cliente'),
            'client_id'  => Schema::hasColumn('chats', 'client_id'),
            'message'    => Schema::hasColumn('chats', 'message'),
            'mensaje'    => Schema::hasColumn('chats', 'mensaje'),
            'sender_type'=> Schema::hasColumn('chats', 'sender_type'),
        ];
    }

    public function getClientName(): string
    {
        if (! $this->clientId) return 'Selecciona un cliente';

        $cliente = Cliente::find($this->clientId);

        return $cliente ? 'Chat con Cliente #'.$this->clientId : 'Cliente #'.$this->clientId;
    }
}
