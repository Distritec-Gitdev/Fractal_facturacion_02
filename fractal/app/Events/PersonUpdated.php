<?php

namespace App\Events;

use App\Models\Person;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PersonUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $person;

    public function __construct(Person $person)
    {
        $this->person = $person;
    }

    public function broadcastOn()
    {
        return new Channel('cliente');
    }

    public function broadcastAs()
    {
        return 'PersonUpdated';
    }
}
