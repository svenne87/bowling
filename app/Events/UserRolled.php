<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class UserRolled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $matchIdentifier;
    public $playerIdentifier;
    public $playerName;
    public $message;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($matchIdentifier = false, $playerIdentifier = false, $playerName = false, $message = false)
    {
        $this->matchIdentifier = $matchIdentifier;
        $this->playerIdentifier = $playerIdentifier;
        $this->playerName = $playerName;
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('user-rolled');
    }
}
