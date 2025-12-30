<?php

namespace App\Filament\Pages\UserMetrics\Widgets;

use App\Models\Cliente;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class UserMetricsStats extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    public ?string $dateStart = null;
    public ?string $dateEnd   = null;

    protected $listeners = ['updateMetricsRange' => 'applyDateRange'];

    public function mount($dateStart = null, $dateEnd = null): void
    {
        $this->dateStart = $dateStart ?? now()->subDays(30)->format('Y-m-d');
        $this->dateEnd   = $dateEnd   ?? now()->format('Y-m-d');
    }

    public function applyDateRange(array $range): void
    {
        $this->dateStart = $range['start'] ?? null;
        $this->dateEnd   = $range['end']   ?? null;
        $this->dispatch('$refresh');
    }

    private function scopeOwned(Builder $q): Builder
    {
        $u = auth()->user();
        $super = config('filament-shield.super_admin.name', 'super_admin');

        return $u?->hasAnyRole([$super, 'admin', 'gestor cartera'])
            ? $q
            : $q->where('clientes.user_id', $u->id);
    }

    // (Opcional) Muestra el rango activo arriba del widget
    protected function getHeading(): ?string
    {
        if ($this->dateStart && $this->dateEnd) {
            return 'Resumen (' . $this->dateStart . ' → ' . $this->dateEnd . ')';
        }
        return 'Resumen (sin filtro)';
    }

    protected function getStats(): array
    {
        $base = $this->scopeOwned(Cliente::query());

        $hasRange = $this->dateStart && $this->dateEnd;

        // Ventanas estándar
        $today       = Carbon::today();
        $now         = Carbon::now();
        $last7Start  = Carbon::today()->subDays(6);    // 7 días incluyendo hoy
        $monthStart  = Carbon::now()->startOfMonth();

        if ($hasRange) {
            $inicio = Carbon::parse($this->dateStart)->startOfDay();
            $fin    = Carbon::parse($this->dateEnd)->endOfDay();

            // Base acotada al rango
            $inRange = (clone $base)->whereBetween('clientes.created_at', [$inicio, $fin]);

            $total      = (clone $inRange)->count();

            // Estas métricas se calculan DENTRO del rango activo
            $hoyCount   = (clone $inRange)->whereBetween('clientes.created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();

            // Intersección del rango con “últimos 7 días”
            $ult7Start  = max($last7Start->timestamp, $inicio->timestamp);
            $ult7End    = min($now->timestamp, $fin->timestamp);
            $ult7       = $ult7Start <= $ult7End
                ? (clone $inRange)->whereBetween('clientes.created_at', [Carbon::createFromTimestamp($ult7Start), Carbon::createFromTimestamp($ult7End)])->count()
                : 0;

            // Intersección del rango con “este mes”
            $mesStart   = max($monthStart->timestamp, $inicio->timestamp);
            $mesEnd     = min($now->timestamp, $fin->timestamp);
            $esteMes    = $mesStart <= $mesEnd
                ? (clone $inRange)->whereBetween('clientes.created_at', [Carbon::createFromTimestamp($mesStart), Carbon::createFromTimestamp($mesEnd)])->count()
                : 0;

            $conGestion = (clone $inRange)->whereHas('gestion')->count();
        } else {
            // SIN filtro: métricas globales con sus ventanas normales
            $total      = (clone $base)->count();
            $hoyCount   = (clone $base)->whereBetween('clientes.created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();
            $ult7       = (clone $base)->whereBetween('clientes.created_at', [$last7Start, $now])->count();
            $esteMes    = (clone $base)->whereBetween('clientes.created_at', [$monthStart, $now])->count();
            $conGestion = (clone $base)->whereHas('gestion')->count();
        }

        return [
            Stat::make('Total clientes', number_format($total))
                ->description($hasRange ? 'Registrados en el rango' : 'Acumulado')
                ->icon('heroicon-o-users')
                ->color('primary'),

            Stat::make('Hoy', number_format($hoyCount))
                ->description($hasRange ? 'Hoy ' : 'Creados hoy')
                ->icon('heroicon-o-calendar')
                ->color('success'),

            Stat::make('Últimos 7 días', number_format($ult7))
                ->description($hasRange ? 'Últimos 7 días' : 'Últimos 7 días')
                ->icon('heroicon-o-clock')
                ->color('info'),

            Stat::make('Este mes', number_format($esteMes))
                ->description($hasRange ? 'Este mes' : 'Mes actual')
                ->icon('heroicon-o-chart-bar')
                ->color('warning'),

            Stat::make('Con gestión', number_format($conGestion))
                ->description($hasRange ? 'Con gestión ' : 'Clientes con gestión')
                ->icon('heroicon-o-briefcase')
                ->color('secondary'),
        ];
    }
}
