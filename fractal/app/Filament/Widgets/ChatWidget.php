<?php

namespace App\Filament\Widgets;

use App\Events\NewMessageSent;
use App\Models\Chat;
use App\Models\Cliente;
use App\Support\ChatSeen;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;

class ChatWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.chat-widget';

    protected static bool $isLazy = false;

    protected bool $singletonMode = true;

    protected string $instanceId = '';
    protected int $openedAt = 0;

    protected bool $isSwitching = false;
    protected ?int $lastLoadedFor = null;
    protected int $lastSwitchAt = 0;
    protected ?int $lastSwitchId = null;

    public bool $isClosed = false;
    public bool $isMinimized = true;
    public ?int $clientId = null;

    public array $messages = [];
    public array $data = ['message' => ''];

    /** Read receipts */
    public int $peerLastSeenId = 0;
    public int $myLastSeenId   = 0;
    private int $lastSeenSent  = 0;

    /** tracking */
    public int $lastMessageId = 0;
    public int $oldestMessageId = 0;

    public string $clientNameCached = 'Selecciona un cliente';

    protected $listeners = [
        'openChat'              => 'open',
        'closeChat'             => 'close',
        'toggleChat'            => 'toggleMinimize',
        'setClientId'           => 'setClient',
        'refreshMessagesFor'    => 'refreshMessagesFor',
        'hardClose'             => 'closeHard',
        'closeOlderChats'       => 'closeOlderChats',
        'bringToFront'          => 'bringToFront',
        'markVisibleAsRead'     => 'markVisibleAsRead',
        'updatePeerLastSeen'    => 'updatePeerLastSeen',
        'bootSeenForChat'       => 'bootSeenForChat',
        'appendIncomingMessage' => 'appendIncomingMessage',
        'fetchNewMessages'      => 'fetchNewMessages',
    ];

    protected static ?array $chatCols = null;

    // ✅ Realtime por Livewire (NO Echo en JS)
    public function getListeners(): array
    {
        $listeners = $this->listeners;

        if ($this->clientId && ! $this->isClosed) {
            // ✅ Sin punto
            $listeners["echo-private:chat.cliente.{$this->clientId},mensaje-nuevo"] = 'appendIncomingMessage';
            // ✅ Con punto (compatibilidad)
            $listeners["echo-private:chat.cliente.{$this->clientId},.mensaje-nuevo"] = 'appendIncomingMessage';
        }

        return $listeners;
    }

    private function ensureInstanceMeta(): void
    {
        if ($this->instanceId === '') {
            $this->instanceId = 'inst-' . substr(md5(spl_object_hash($this) . microtime(true)), 0, 10);
        }
        if ($this->openedAt === 0) {
            $this->openedAt = (int) floor(microtime(true) * 1000);
        }
    }

    public function mount(): void
    {
        $this->ensureInstanceMeta();

        $sessionId = (int) (session('chatClientId') ?? 0);
        if ($sessionId > 0 && $this->clientId === null) {
            $this->clientId = $sessionId;
        }

        $this->isMinimized = true;
        $this->form->fill();

        if ($this->clientId) {
            $this->hydrateClientName($this->clientId);
        }

        $this->bootSeenForChat();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('message')
                    ->label('Mensaje')
                    ->maxLength(1000)
                    ->placeholder('Escribe tu mensaje...')
                    ->disabled(fn () => ! $this->clientId),
            ])
            ->statePath('data');
    }

    #[On('openChat')]
    public function open(): void
    {
        $this->ensureInstanceMeta();
        if ($this->isSwitching) return;

        if ($this->singletonMode) {
            $this->dispatch('closeOlderChats', senderId: $this->instanceId, senderOpenedAt: $this->openedAt);
        }

        $this->isClosed = false;

        if ($this->isMinimized) {
            $this->isMinimized = false;

            if ($this->clientId && $this->lastLoadedFor !== $this->clientId) {
                $this->loadMessages();
            }
        }

        $this->dispatch('chat:window-open', id: $this->instanceId);

        if ($this->clientId) {
            $this->markVisibleAsRead();
        }
    }

    #[On('closeChat')]
    public function close(): void
    {
        if ($this->isSwitching) return;
        $this->isMinimized = true;
        $this->dispatch('chat:window-close', id: $this->instanceId);
    }

    #[On('hardClose')]
    public function closeHard(): void
    {
        if ($this->isSwitching) return;

        $this->isClosed = true;
        $this->isMinimized = true;
        $this->messages = [];
        $this->clientId = null;
        $this->lastLoadedFor = null;

        $this->lastMessageId = 0;
        $this->oldestMessageId = 0;
        $this->clientNameCached = 'Selecciona un cliente';

        $this->peerLastSeenId = 0;
        $this->myLastSeenId   = 0;
        $this->lastSeenSent   = 0;

        session()->forget('chatClientId');
        $this->dispatch('chat:window-close', id: $this->instanceId);
    }

    #[On('toggleChat')]
    public function toggleMinimize(): void
    {
        if ($this->isSwitching) return;

        $this->isClosed = false;
        $this->isMinimized = ! $this->isMinimized;

        if (! $this->isMinimized) {
            if ($this->singletonMode) {
                $this->dispatch('closeOlderChats', senderId: $this->instanceId, senderOpenedAt: $this->openedAt);
            }

            if ($this->clientId && $this->lastLoadedFor !== $this->clientId) {
                $this->loadMessages();
            }

            $this->dispatch('chat:window-open', id: $this->instanceId);
            $this->markVisibleAsRead();
        } else {
            $this->dispatch('chat:window-close', id: $this->instanceId);
        }
    }

    #[On('setClientId')]
    public function setClient(int $clientId): void
    {
        $this->ensureInstanceMeta();
        if ($clientId <= 0) return;

        $now = (int) (microtime(true) * 1000);
        if ($this->lastSwitchId === $clientId && ($now - $this->lastSwitchAt) < 600) return;
        $this->lastSwitchId = $clientId;
        $this->lastSwitchAt = $now;

        if ($this->isSwitching) return;
        $this->isSwitching = true;

        if ($this->singletonMode) {
            $this->dispatch('closeOlderChats', senderId: $this->instanceId, senderOpenedAt: $this->openedAt);
        }

        $this->isClosed = false;

        if ($this->clientId === $clientId) {
            $this->isMinimized = false;
            if ($this->lastLoadedFor !== $this->clientId) $this->loadMessages();
            $this->bootSeenForChat($clientId);
            $this->markVisibleAsRead();
            $this->dispatch('chat:window-open', id: $this->instanceId);
            $this->isSwitching = false;
            return;
        }

        $this->clientId = $clientId;
        $this->messages = [];
        $this->lastLoadedFor = null;
        $this->isMinimized = false;
        $this->lastMessageId = 0;
        $this->oldestMessageId = 0;

        session()->put('chatClientId', $clientId);

        $this->hydrateClientName($clientId);
        $this->loadMessages();
        $this->dispatch('chat:window-open', id: $this->instanceId);

        $this->bootSeenForChat($clientId);
        $this->markVisibleAsRead();

        $this->isSwitching = false;
    }

    #[On('bringToFront')]
    public function bringToFront(): void
    {
        // UI only
    }

    #[On('closeOlderChats')]
    public function closeOlderChats(string $senderId, int $senderOpenedAt): void
    {
        $this->ensureInstanceMeta();
        if ($senderId === $this->instanceId) return;

        $iAmOlder = $this->openedAt < $senderOpenedAt
            || ($this->openedAt === $senderOpenedAt && strcmp($this->instanceId, $senderId) < 0);

        if ($iAmOlder) $this->closeHard();
    }

    /* ================= Mensajería ================= */

    #[On('refreshMessages')]
    public function loadMessages(?int $targetClientId = null): void
    {
        if (! $this->clientId) {
            $this->messages = [];
            $this->lastLoadedFor = null;
            $this->lastMessageId = 0;
            $this->oldestMessageId = 0;
            return;
        }

        if ($targetClientId !== null && $targetClientId !== $this->clientId) return;

        $this->detectChatColumns();
        $c = static::$chatCols;

        if (empty($c['client_fk']) || empty($c['text_col'])) {
            $this->messages = [];
            $this->lastLoadedFor = null;
            return;
        }

        $fk  = $c['client_fk'];
        $txt = $c['text_col'];

        $limit = 60;

        $rows = Chat::query()
            ->select(['id', $fk, 'user_id', 'created_at'])
            ->selectRaw($txt . ' as body')
            ->with('user:id,name')
            ->where($fk, $this->clientId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $meId   = (int) (Auth::id() ?? 0);
        $meName = (string) (Auth::user()->name ?? 'Yo');

        $this->messages = $rows->map(function ($m) use ($meId, $meName) {
            $userId = (int) ($m->user_id ?? 0);
            $isMine = ($userId > 0 && $meId > 0 && $userId === $meId);

            $senderName = ($m->relationLoaded('user') && $m->user)
                ? (string) $m->user->name
                : ($isMine ? $meName : 'Cliente');

            $created = $m->created_at;

            return [
                'id'          => (int) $m->id,
                'message'     => (string) ($m->body ?? ''),
                'created_at'  => $created ? $created->toDateTimeString() : null,
                'time'        => $created ? $created->format('H:i') : '',
                'sender_id'   => $userId ?: null,
                'sender_name' => $senderName,
                'is_mine'     => $isMine,
            ];
        })->toArray();

        $ids = array_column($this->messages, 'id');
        $this->oldestMessageId = $ids ? (int) min($ids) : 0;
        $this->lastMessageId   = $ids ? (int) max($ids) : 0;

        $this->lastLoadedFor = $this->clientId;

        $this->dispatch('messagesLoaded', clientId: $this->clientId);
        $this->markVisibleAsRead();
    }

    #[On('fetchNewMessages')]
    public function fetchNewMessages(): void
    {
        // Sync puntual (visibilidad/reconexión). No se spamea.
        if ($this->isClosed) return;
        if (! $this->clientId) return;

        $this->detectChatColumns();
        $c = static::$chatCols;
        if (empty($c['client_fk']) || empty($c['text_col'])) return;

        $fk  = $c['client_fk'];
        $txt = $c['text_col'];

        $lastId = (int) ($this->lastMessageId ?? 0);

        $rows = Chat::query()
            ->select(['id', $fk, 'user_id', 'created_at'])
            ->selectRaw($txt . ' as body')
            ->with('user:id,name')
            ->where($fk, $this->clientId)
            ->where('id', '>', $lastId)
            ->orderBy('id', 'asc')
            ->limit(50)
            ->get();

        if ($rows->isEmpty()) return;

        $meId   = (int) (Auth::id() ?? 0);
        $meName = (string) (Auth::user()->name ?? 'Yo');

        foreach ($rows as $m) {
            $id = (int) $m->id;
            if ($id <= $this->lastMessageId) continue;

            $senderId = (int) ($m->user_id ?? 0);
            $isMine   = ($senderId > 0 && $senderId === $meId);

            $createdAt = $m->created_at ? $m->created_at->toDateTimeString() : now()->toDateTimeString();
            $time      = $m->created_at ? $m->created_at->format('H:i') : '';

            $senderName = ($m->relationLoaded('user') && $m->user)
                ? (string) $m->user->name
                : ($isMine ? $meName : 'Cliente');

            $this->messages[] = [
                'id'          => $id,
                'message'     => (string) ($m->body ?? ''),
                'created_at'  => $createdAt,
                'time'        => $time,
                'sender_id'   => $senderId ?: null,
                'sender_name' => $senderName,
                'is_mine'     => $isMine,
            ];

            $this->lastMessageId = $id;
            if ($this->oldestMessageId === 0) $this->oldestMessageId = $id;
        }

        if (! $this->isMinimized) {
            $this->markVisibleAsRead();
        }

        $this->dispatch('messagesLoaded', clientId: $this->clientId);
    }

    #[On('appendIncomingMessage')]
    public function appendIncomingMessage(array $payload = []): void
    {
        $cid = (int) ($payload['clientId'] ?? $payload['client_id'] ?? 0);
        if (! $this->clientId || $cid !== (int) $this->clientId) return;

        $kind = (string) ($payload['kind'] ?? 'message');

        if ($kind === 'seen') {
            $last = (int) ($payload['peer_last_seen'] ?? 0);
            if ($last > 0) $this->updatePeerLastSeen($last);
            return;
        }

        $m = $payload['message'] ?? null;
        if (!is_array($m)) return;

        $id = (int) ($m['id'] ?? 0);
        if ($id <= 0 || $id <= (int) $this->lastMessageId) return;

        $user = $m['user'] ?? null;
        $senderName = is_array($user) && !empty($user['name']) ? (string) $user['name'] : 'Cliente';
        $senderId   = is_array($user) && !empty($user['id']) ? (int) $user['id'] : 0;

        $me = (int) (Auth::id() ?? 0);
        if ($senderId > 0 && $me > 0 && $senderId === $me) return;

        $createdAt = (string) ($m['created_at'] ?? now()->toDateTimeString());
        $time = '';
        try { $time = \Carbon\Carbon::parse($createdAt)->format('H:i'); } catch (\Throwable $e) {}

        $this->messages[] = [
            'id'          => $id,
            'message'     => (string) ($m['message'] ?? $m['content'] ?? ''),
            'created_at'  => $createdAt,
            'time'        => $time,
            'sender_id'   => $senderId ?: null,
            'sender_name' => $senderName,
            'is_mine'     => false,
        ];

        $this->lastMessageId = $id;
        if ($this->oldestMessageId === 0) $this->oldestMessageId = $id;

        if (! $this->isMinimized && ! $this->isClosed) {
            $this->markVisibleAsRead();
        }

        $this->dispatch('messagesLoaded', clientId: $this->clientId);
    }

    #[On('refreshMessagesFor')]
    public function refreshMessagesFor(int|string|null $clientId = null): void
    {
        $clientId = is_numeric($clientId) ? (int) $clientId : null;
        if ($clientId === null || $clientId !== ($this->clientId ?? null)) return;

        $this->loadMessages();
    }

    public function sendMessage(): void
    {
        $texto = trim((string) ($this->data['message'] ?? ''));
        if ($texto === '' || ! $this->clientId) return;

        $key = 'chat-send:' . (Auth::id() ?: 'guest') . ':' . ($this->clientId ?? 'x');
        if (RateLimiter::tooManyAttempts($key, 12)) {
            Notification::make()->title('Demasiados mensajes')->body('Espera un momento…')->danger()->send();
            return;
        }
        RateLimiter::hit($key, 10);

        $this->detectChatColumns();
        $c = static::$chatCols;

        if (empty($c['client_fk']) || empty($c['text_col'])) {
            Notification::make()->title('Error')->body('Tabla chats sin columnas esperadas.')->danger()->send();
            return;
        }

        $fk  = $c['client_fk'];
        $txt = $c['text_col'];

        $chat = Chat::create([
            'user_id' => Auth::id(),
            $fk       => $this->clientId,
            $txt      => $texto,
        ]);

        $chat->loadMissing('user:id,name');

        $this->data['message'] = '';

        $createdAt = $chat->created_at ? $chat->created_at->toDateTimeString() : now()->toDateTimeString();
        $time      = $chat->created_at ? $chat->created_at->format('H:i') : '';

        $this->messages[] = [
            'id'          => (int) $chat->id,
            'message'     => $texto,
            'created_at'  => $createdAt,
            'time'        => $time,
            'sender_id'   => (int) (Auth::id() ?? 0),
            'sender_name' => Auth::user()->name ?? 'Yo',
            'is_mine'     => true,
        ];

        $this->lastMessageId = max($this->lastMessageId, (int) $chat->id);
        if ($this->oldestMessageId === 0) $this->oldestMessageId = (int) $chat->id;

        try {
            broadcast(new NewMessageSent(
                clientId: (int) $this->clientId,
                message:  $chat,
                kind:     'message',
            ))->toOthers();
        } catch (\Throwable $e) {}

        $this->markVisibleAsRead();
        $this->dispatch('messagesLoaded', clientId: $this->clientId);
    }

    /* ================= Read Receipts ================= */

    #[On('bootSeenForChat')]
    public function bootSeenForChat(?int $clientId = null): void
    {
        $chatId = (int) ($clientId ?? $this->clientId);
        if ($chatId <= 0) {
            $this->peerLastSeenId = 0;
            $this->myLastSeenId   = 0;
            $this->lastSeenSent   = 0;
            return;
        }

        $this->peerLastSeenId = 0;
        $this->myLastSeenId   = 0;
        $this->lastSeenSent   = 0;
    }

    #[On('markVisibleAsRead')]
    public function markVisibleAsRead(): void
    {
        if ($this->isMinimized || $this->isClosed) return;
        if (! $this->clientId || empty($this->messages)) return;

        $me = (int) (Auth::id() ?? 0);
        if ($me <= 0) return;

        $lastVisible = 0;
        foreach ($this->messages as $m) {
            $senderId = (int) ($m['sender_id'] ?? 0);
            $isMine = ($senderId === $me);
            if (! $isMine) $lastVisible = max($lastVisible, (int) ($m['id'] ?? 0));
        }
        if ($lastVisible <= 0) return;

        $this->myLastSeenId = max($this->myLastSeenId, $lastVisible);

        $prevCache = ChatSeen::get($this->clientId, $me);
        $already   = max($prevCache, $this->lastSeenSent);
        if ($lastVisible <= $already) return;

        $lockKey = "chat:seen:cooldown:{$this->clientId}:{$me}";
        if (! cache()->add($lockKey, 1, 1)) return;

        ChatSeen::put($this->clientId, $me, $lastVisible);
        $this->lastSeenSent = $lastVisible;

        $dummy = new Chat();
        $dummy->id = $lastVisible;
        $dummy->message = '';

        try {
            broadcast(new NewMessageSent(
                clientId: (int) $this->clientId,
                message:  $dummy,
                kind:     'seen',
                peerLastSeenId: $lastVisible,
                readerId: $me
            ))->toOthers();
        } catch (\Throwable $e) {}
    }

    #[On('updatePeerLastSeen')]
    public function updatePeerLastSeen(int $lastSeenId): void
    {
        $this->peerLastSeenId = max($this->peerLastSeenId, (int) $lastSeenId);
    }

    /* ================= Utilidades ================= */

    protected function detectChatColumns(): void
    {
        if (static::$chatCols !== null) return;

        if (!Schema::hasTable('chats')) {
            static::$chatCols = ['client_fk' => null, 'text_col' => null];
            return;
        }

        $fk = null;
        foreach (['id_cliente', 'cliente_id', 'client_id'] as $col) {
            if (Schema::hasColumn('chats', $col)) { $fk = $col; break; }
        }

        $txt = null;
        foreach (['message', 'mensaje'] as $col) {
            if (Schema::hasColumn('chats', $col)) { $txt = $col; break; }
        }

        static::$chatCols = ['client_fk' => $fk, 'text_col' => $txt];
    }

    protected function hydrateClientName(int $clientId): void
    {
        $cacheKey = "chat:client-name:{$clientId}";

        $this->clientNameCached = cache()->remember($cacheKey, 600, function () use ($clientId) {
            $cliente = Cliente::query()
                ->where('id_cliente', $clientId)
                ->first();

            if (! $cliente) return "Cliente #{$clientId}";

            $possibleCols = [
                'ID_Cliente_Nombre',
                'nombre_completo',
                'nombre',
                'name',
            ];

            foreach ($possibleCols as $col) {
                $val = $cliente->getAttribute($col);
                if (is_string($val) && trim($val) !== '') return trim($val);
            }

            return "Cliente #{$clientId}";
        });
    }

    public function getClientName(): string
    {
        return $this->clientId ? ("Chat con " . $this->clientNameCached) : 'Selecciona un cliente';
    }
}
