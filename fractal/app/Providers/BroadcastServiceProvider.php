<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ✅ IMPORTANTE:
        // Solo 'web' para que exista sesión/cookies/CSRF en /broadcasting/auth
        // La autorización fina se hace en routes/channels.php
        Broadcast::routes([
            'middleware' => ['web'],
        ]);

        require base_path('routes/channels.php');
    }
}
