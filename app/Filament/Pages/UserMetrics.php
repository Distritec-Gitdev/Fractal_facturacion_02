<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Livewire\Attributes\On; // 👈 Para escuchar eventos Livewire
use Carbon\Carbon;

class UserMetrics extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Métricas';
    protected static ?string $title           = 'Métricas';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int    $navigationSort  = 50;

    protected static string $view = 'filament.pages.user-metrics';

    public ?string $dateStart = null;
    public ?string $dateEnd   = null;

    public function mount(): void
    {
        $this->dateEnd   = now()->format('Y-m-d');
        $this->dateStart = now()->subDays(30)->format('Y-m-d');
    }

    #[On('dateRangeUpdated')] // si luego quieres usarlo, lo puedes dejar
    public function updateDateRange($range): void
    {
        $this->dateStart = $range['start'];
        $this->dateEnd   = $range['end'];
        $this->dispatch('updateMetricsRange', range: $range);
    }

    // ✅ Método llamado desde el botón "Aplicar" (sin JS del lado del cliente)
    public function applyDateRange(): void
    {
        $range = ['start' => $this->dateStart, 'end' => $this->dateEnd];
        $this->dispatch('updateMetricsRange', range: $range);
    }


    public function clearFilter(): void
    {
        // Deja el rango vacío = sin filtrar por fecha
        $this->dateStart = null;
        $this->dateEnd   = null;

        $this->dispatch('updateMetricsRange', range: ['start' => null, 'end' => null]);
    }

    public function resetLast30(): void
    {
        $this->dateEnd   = now()->format('Y-m-d');
        $this->dateStart = now()->subDays(30)->format('Y-m-d');

        $this->dispatch('updateMetricsRange', range: [
            'start' => $this->dateStart,
            'end'   => $this->dateEnd,
        ]);
    }
}