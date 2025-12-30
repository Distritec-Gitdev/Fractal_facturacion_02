<?php

namespace App\Support\Filament;

use Illuminate\Database\Eloquent\Model;

trait AdminOnlyResourceAccess
{
    protected static function adminish(): bool
    {
        $u = auth()->user();
        if (! $u) return false;

        // Nombre del super admin según Shield (por defecto: super_admin)
        $super = config('filament-shield.super_admin.name', 'super_admin');

        return $u->hasAnyRole([$super, 'admin']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::adminish();
    }

    public static function canViewAny(): bool
    {
        return static::adminish();
    }

    public static function canView(Model $record): bool
    {
        return static::adminish();
    }

    public static function canCreate(): bool
    {
        return static::adminish();
    }

    public static function canEdit(Model $record): bool
    {
        return static::adminish();
    }

    public static function canDelete(Model $record): bool
    {
        return static::adminish();
    }

    public static function canDeleteAny(): bool
    {
        return static::adminish();
    }
}
