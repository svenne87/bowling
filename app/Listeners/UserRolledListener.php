<?php

namespace App\Listeners;

use App\Events\UserRolled;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserRolledListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    /**
     * Handle the event.
     *
     * @param  UserRolled  $event
     * @return void
     */
    public function handle(UserRolled $event)
    {
        

    }
}
