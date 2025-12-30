<?php

namespace App\Providers;

use Filament\Forms\Form;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

use App\Models\Chat;
use App\Repositories\Interfaces\TercerosRepositoryInterface;
use App\Repositories\TercerosApiRepository;
use App\Support\Filament\SelectNaFilter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TercerosRepositoryInterface::class, TercerosApiRepository::class);
    }

    public function boot(): void
    {
        Action::macro('modalMinimizeButton', function (bool $shouldShow = true): Action {
            if (! $shouldShow) {
                return $this;
            }

            $existing = method_exists($this, 'getModalHeaderActions')
                ? $this->getModalHeaderActions()
                : [];

            $this->modalHeaderActions(array_merge([
                Action::make('minimize')
                    ->icon('heroicon-o-minus')
                    ->tooltip('Minimizar')
                    ->button()
                    ->attributes([
                        'x-on:click.prevent' => 'isMinimized = ! isMinimized',
                    ]),
            ], Arr::wrap($existing)));

            return $this;
        });

        /**
         * Optimizado:
         * - Antes: consulta a chats en CADA render de CUALQUIER vista.
         * - Ahora: cache por usuario (60s) y sin orWhere ilógico.
         */
        View::composer('*', function ($view) {
            if (!Auth::check()) return;

            $userId = (int) Auth::id();

            $clientIds = Cache::remember("chat:clientIds:user:{$userId}", 60, function () use ($userId) {
                // Si tu tabla usa otra FK (id_cliente/client_id), aquí toca ajustar.
                // En tu código principal ya estás estandarizando a cliente_id,
                // así que dejamos cliente_id para no volver esto un Frankenstein.

                return Chat::query()
                    ->where('user_id', $userId)
                    ->whereNotNull('cliente_id')
                    ->pluck('cliente_id')
                    ->unique()
                    ->values()
                    ->toArray();
            });

            $view->with('globalChatClientIds', $clientIds);
        });

        // Macro: schemaFiltered() => igual que schema(), pero limpiando N/A en todos los Select
        Form::macro('schemaFiltered', function (array $schema) {
            /** @var \Filament\Forms\Form $this */
            return $this->schema(SelectNaFilter::apply($schema));
        });
    }
}
