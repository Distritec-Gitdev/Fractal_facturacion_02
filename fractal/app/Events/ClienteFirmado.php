<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class ClienteFirmado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $clienteId,
        public string $token,
        public ?int $asesorUserId = null, // opcional
    ) {}

    public function broadcastOn(): array
    {
        // Canal hiper-específico por cliente + token
        return [
            new PrivateChannel("cliente.{$this->clienteId}.token.{$this->token}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'cliente.firmado';
    }

    public function broadcastWith(): array
    {
        return [
            'clienteId' => $this->clienteId,
            'token'     => $this->token,
            'ts'        => now()->toISOString(),
        ];
    }

    public function broadcastWhen(): bool
    {
        // Solo tiene sentido emitir cuando ya quedó confirmado (2)
        return true;
    }
}
