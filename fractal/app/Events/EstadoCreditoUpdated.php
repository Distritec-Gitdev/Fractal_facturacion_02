<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EstadoCreditoUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = false;

    public function __construct(
        public int $clienteId,
        public ?int $estadoId = null,
        public ?string $estadoTexto = null,
        public ?int $userId = null,
    ) {
        $this->dontBroadcastToCurrentUser();

        Log::debug(' [EstadoCreditoUpdated::__construct] Evento instanciado', [
            'cliente_id'   => $this->clienteId,
            'estado_id'    => $this->estadoId,
            'estado_texto' => $this->estadoTexto,
            'user_id'      => $this->userId,
        ]);
    }

    public function broadcastOn(): array
    {
        $channel = "gestion-clientes.{$this->clienteId}";

        Log::debug(' [EstadoCreditoUpdated@broadcastOn] Enviando por canal PRIVADO', [
            'channel' => $channel,
            'cliente_id' => $this->clienteId,
        ]);

        return [
            new PrivateChannel($channel),
        ];
    }

    public function broadcastAs(): string
    {
        Log::debug(' [EstadoCreditoUpdated@broadcastAs] Nombre de evento', [
            'event' => 'EstadoCreditoUpdated',
        ]);

        return 'EstadoCreditoUpdated';
    }

    public function broadcastWith(): array
    {
        $payload = [
            'cliente_id'   => $this->clienteId,
            'estado_id'    => $this->estadoId,
            'estado_texto' => $this->estadoTexto,
            'user_id'      => $this->userId,
        ];

        Log::debug(' [EstadoCreditoUpdated@broadcastWith] Payload enviado al WS', $payload);

        return $payload;
    }

    public function broadcastWhen(): bool
    {
        $ok = $this->estadoId !== null;

        Log::debug(' [EstadoCreditoUpdated@broadcastWhen] ¿Se emite el evento?', [
            'estado_id' => $this->estadoId,
            'emitir'    => $ok,
        ]);

        return $ok;
    }
}
