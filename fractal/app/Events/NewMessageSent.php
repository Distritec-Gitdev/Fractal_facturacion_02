<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Chat;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\Channel;

class NewMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = false;

    public function __construct(
        public int $clientId,
        public Chat $message,
        public string $kind = 'message',        // 'message' | 'seen'
        public ?int $peerLastSeenId = null,
        public ?int $readerId = null,
    ) {}

    public function broadcastOn(): Channel|array
    {
        return new PrivateChannel("chat.cliente.{$this->clientId}");
    }

    public function broadcastAs(): string
    {
        return 'mensaje-nuevo';
    }

    public function broadcastWith(): array
    {
        if ($this->kind === 'message' && ! $this->message->relationLoaded('user')) {
            $this->message->load('user:id,name');
        }

        $text = $this->message->message ?? $this->message->mensaje ?? '';

        return [
            'client_id'       => $this->clientId,
            'kind'            => $this->kind,
            'peer_last_seen'  => $this->peerLastSeenId,
            'reader_id'       => $this->readerId,
            'message'         => [
                'id'         => (int) ($this->message->id ?? 0),
                'message'    => (string) $text,
                'content'    => (string) $text,
                'created_at' => optional($this->message->created_at)->toDateTimeString(),
                'user'       => $this->message->user
                    ? [
                        'id'   => (int) $this->message->user->id,
                        'name' => (string) $this->message->user->name,
                    ]
                    : null,
            ],
        ];
    }
}
