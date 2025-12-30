<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Inicio';
    protected static ?string $title = 'Inicio';

    public function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\UserProfileWidget::class, // <- este
            \Filament\Widgets\AccountWidget::class,
           // \Filament\Widgets\FilamentInfoWidget::class,
            // tus widgets...
        ];
    }

    public function getWidgets(): array
    {
        return [
            // widgets para el cuerpo
        ];
    }
}
