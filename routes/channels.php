<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Auth;
use App\Models\Token;
use App\Models\Cliente;

/**
 * Helpers seguros: NO asumen que existen guards como "filament" o "admin".
 */
$guardExists = function (string $guard): bool {
    $guards = config('auth.guards', []);
    return array_key_exists($guard, $guards);
};

$guardsToTry = function () use ($guardExists): array {
    // Filament a veces usa 'web'. Si tienes otro guard configurado, lo incluye.
    $filamentGuard = config('filament.auth.guard', 'web');

    $candidates = array_unique([
        'web',
        $filamentGuard,
        'filament',
        'admin',
    ]);

    return array_values(array_filter($candidates, fn ($g) => $guardExists($g)));
};

$anyAuthenticated = function () use ($guardsToTry): bool {
    foreach ($guardsToTry() as $g) {
        try {
            if (Auth::guard($g)->check()) return true;
        } catch (\Throwable $e) {
            // Si un guard está mal definido, lo ignoramos.
        }
    }
    return false;
};

$getRealUser = function () use ($guardsToTry) {
    foreach ($guardsToTry() as $g) {
        try {
            $u = Auth::guard($g)->user();
            if ($u) return $u;
        } catch (\Throwable $e) {
        }
    }
    return null;
};

/**
 * Canal default de Laravel (déjalo así)
 */
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) ($user->id ?? 0) === (int) $id;
});

/**
 * ✅ Chat: cualquiera autenticado (en cualquier guard existente)
 * IMPORTANTE: Esto permite escuchar cualquier chat.cliente.{clienteId}.
 * Si quieres restringirlo, aquí es donde se hace.
 */
Broadcast::channel('chat.cliente.{clienteId}', function ($user, $clienteId) use ($anyAuthenticated) {
    return $anyAuthenticated();
});

/**
 * Token channel: solo usuarios autenticados + token válido para ese cliente
 */
Broadcast::channel('cliente.{clienteId}.token.{token}', function ($user, $clienteId, $token) use ($anyAuthenticated) {
    if (! $anyAuthenticated()) return false;

    return Token::where('id_cliente', (int) $clienteId)
        ->where('token', (string) $token)
        ->exists();
});

/**
 * Gestion clientes: solo admin/superadmin o dueño del cliente
 */
Broadcast::channel('gestion-clientes.{clienteId}', function ($user, $clienteId) use ($anyAuthenticated, $getRealUser) {
    if (! $anyAuthenticated()) return false;

    $cliente = Cliente::where('id_cliente', (int) $clienteId)->first();
    if (! $cliente) return false;

    $realUser = $getRealUser();
    if (! $realUser) return false;

    $super = config('filament-shield.super_admin.name', 'super_admin');

    $esAdmin = method_exists($realUser, 'hasAnyRole')
        ? $realUser->hasAnyRole([$super, 'admin'])
        : false;

    $esDueno = ((int) ($cliente->user_id ?? 0) === (int) $realUser->id);

    return $esAdmin || $esDueno;
});
