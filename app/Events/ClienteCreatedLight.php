<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class ClienteCreatedLight implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = false;

    public function __construct(
        public int $clienteId,
        public ?string $cedula = null,
        public ?int $userId = null,
    ) {
        \Log::debug('🟢 [ClienteCreatedLight::__construct]', [
            'cliente_id' => $this->clienteId,
            'cedula'     => $this->cedula,
            'user_id'    => $this->userId,
        ]);
    }

    public function broadcastOn(): Channel|array
    {
        \Log::debug('📡 [ClienteCreatedLight@broadcastOn]', [
            'channel' => 'gestion-clientes',
        ]);

        return new Channel('gestion-clientes');
    }

    public function broadcastAs(): string
    {
        \Log::debug('🏷️ [ClienteCreatedLight@broadcastAs]', [
            'event' => 'ClienteCreatedLight',
        ]);

        return 'ClienteCreatedLight';
    }

    public function broadcastWith(): array
    {
        $payload = [
            'cliente_id' => $this->clienteId,
            'cedula'     => $this->cedula,
            'user_id'    => $this->userId,
            'action'     => 'created',
        ];

        \Log::debug('📦 [ClienteCreatedLight@broadcastWith]', $payload);

        return $payload;
    }

    public function broadcastWhen(): bool
    {
        $ok = $this->clienteId > 0;

        \Log::debug('🚦 [ClienteCreatedLight@broadcastWhen] ¿Se emite?', [
            'cliente_id' => $this->clienteId,
            'emitir'     => $ok,
        ]);

        return $ok;
    }
}
