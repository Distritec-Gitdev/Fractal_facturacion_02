<?php

// app/Events/ClienteUpdated.php
namespace App\Events;

use App\Models\Cliente;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClienteUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Cliente $cliente;
    public string $action; // 'created' | 'updated' | etc.

    public function __construct(Cliente $cliente, string $action = 'updated')
    {
        // Seguimos guardando las props por si luego quieres reactivarlo
        $this->cliente = $cliente;
        $this->action  = $action;
    }

    /**
     * 🔌 EVENTO DESACTIVADO:
     * No se va a emitir a ningún canal.
     */
    public function broadcastOn(): array
    {
        // Antes:
        // return [
        //     new Channel('cliente'),
        //     new Channel('gestion-clientes'),
        // ];

        return []; // Nada de nada
    }

    /**
     * Payload vacío para no procesar ni serializar el modelo.
     */
    public function broadcastWith(): array
    {
        // Antes hacía ->toArray() y logueaba todo.
        // Lo dejamos vacío para no hacer trabajo inútil.
        return [];
    }

    public function broadcastAs(): string
    {
        // El nombre da igual porque no se emite, pero lo dejamos por compatibilidad.
        return 'ClienteUpdated';
    }

    /**
     * 👇 Clave: siempre false ⇒ nunca se broadcastéa.
     */
    public function broadcastWhen(): bool
    {
        return false;
    }
}
