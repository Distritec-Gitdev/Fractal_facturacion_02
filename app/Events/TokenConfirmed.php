<?php

// app/Events/TokenConfirmed.php
namespace App\Events;

use App\Models\Token;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class TokenConfirmed implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public Token $token;

    public function __construct(Token $token)
    {
        $this->token = $token;
    }

    public function broadcastOn()
    {
        // Canal privado por cliente
        return new Channel("token.{$this->token->id_cliente}");
    }

    public function broadcastAs()
    {
        return 'token.confirmed';
    }
}
