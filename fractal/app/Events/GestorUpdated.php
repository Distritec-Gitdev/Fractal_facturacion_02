<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GestorUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // Igual que el de EstadoCredito: no esperamos a afterCommit
    public bool $afterCommit = false;

    public function __construct(
        public int $clienteId,
        public ?int $gestorId = null,
        public ?string $gestorNombre = null,
        public ?int $userId = null,
    ) {
        // Que no se lo trague el mismo usuario que disparó el cambio
        $this->dontBroadcastToCurrentUser();

        Log::debug(' [GestorUpdated::__construct] Evento instanciado', [
            'cliente_id'    => $this->clienteId,
            'gestor_id'     => $this->gestorId,
            'gestor_nombre' => $this->gestorNombre,
            'user_id'       => $this->userId,
        ]);
    }

    public function broadcastOn(): Channel|array
    {
        Log::debug('📡 [GestorUpdated@broadcastOn] Enviando por canal', [
            'channel'    => 'gestion-clientes',
            'cliente_id' => $this->clienteId,
        ]);

        return new Channel('gestion-clientes');
    }

    public function broadcastAs(): string
    {
        Log::debug(' [GestorUpdated@broadcastAs] Nombre de evento', [
            'event' => 'GestorUpdated',
        ]);

        return 'GestorUpdated';
    }

    public function broadcastWith(): array
    {
        $payload = [
            'cliente_id'    => $this->clienteId,
            'gestor_id'     => $this->gestorId,
            'gestor_nombre' => $this->gestorNombre,
            'user_id'       => $this->userId,
        ];

        Log::debug(' [GestorUpdated@broadcastWith] Payload enviado al WS', $payload);

        return $payload;
    }

    public function broadcastWhen(): bool
    {
        $ok = $this->gestorId !== null;

        Log::debug('🚦 [GestorUpdated@broadcastWhen] ¿Se emite el evento?', [
            'gestor_id' => $this->gestorId,
            'emitir'    => $ok,
        ]);

        return $ok;
    }
}
