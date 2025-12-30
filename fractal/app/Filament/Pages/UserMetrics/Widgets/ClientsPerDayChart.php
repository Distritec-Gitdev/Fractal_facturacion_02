<?php

namespace App\Filament\Pages\UserMetrics\Widgets;

use App\Models\Cliente;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class ClientsPerDayChart extends ChartWidget
{
    protected static ?string $pollingInterval = null;

    // Puedes dejarlo, pero lo sobreescribimos con getHeading()
    protected static ?string $heading = 'Clientes por día';

    protected int|string|array $columnSpan = [
        'md'  => 1,
        'lg'  => 1,
        'xl'  => 1,
        '2xl' => 1,
    ];

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
        // Soporta null cuando borras el filtro
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

    // 🔹 Encabezado dinámico con el rango activo
        public function getHeading(): ?string
        {
            if ($this->dateStart && $this->dateEnd) {
                return 'Clientes por día (' . $this->dateStart . ' → ' . $this->dateEnd . ')';
            }
            return 'Clientes por día (sin filtro)';
        }

    protected function getData(): array
    {
        $base = $this->scopeOwned(Cliente::query());

        // Si hay rango → filtramos y completamos días vacíos
        if ($this->dateStart && $this->dateEnd) {
            $inicio = Carbon::parse($this->dateStart)->startOfDay();
            $fin    = Carbon::parse($this->dateEnd)->endOfDay();

            $rows = (clone $base)
                ->selectRaw('DATE(clientes.created_at) as d, COUNT(*) as c')
                ->whereBetween('clientes.created_at', [$inicio, $fin])
                ->groupBy('d')
                ->orderBy('d')
                ->get()
                ->keyBy('d');

            $labels = [];
            $data   = [];

            for ($day = $inicio->copy(); $day->lte($fin); $day->addDay()) {
                $key      = $day->toDateString();
                $labels[] = $day->format('d/m');
                $data[]   = (int) ($rows[$key]->c ?? 0);
            }
        } else {
            // Sin rango (filtro borrado) → mostramos todo lo disponible agrupado por fecha
            $rows = (clone $base)
                ->selectRaw('DATE(clientes.created_at) as d, COUNT(*) as c')
                ->groupBy('d')
                ->orderBy('d')
                ->get();

            $labels = $rows->map(fn ($r) => Carbon::parse($r->d)->format('d/m'))->all();
            $data   = $rows->pluck('c')->map(fn ($n) => (int) $n)->all();
        }

        return [
            'datasets' => [[
                'label'   => 'Clientes',
                'data'    => $data,
                'fill'    => true,
                'tension' => 0.3,
            ]],
            'labels'   => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
