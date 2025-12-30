<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Http\Livewire\ClienteDocumentacion;
use App\Http\Livewire\ClienteDocumentacionStandalone;

class LivewireServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Registra el alias 'cliente-documentacion' para tu componente
         Livewire::component('cliente-documentacion', ClienteDocumentacion::class);
         Livewire::component('cliente-documentacion-standalone', ClienteDocumentacionStandalone::class);
    }

    public function register()
    {
        //
    }
}
