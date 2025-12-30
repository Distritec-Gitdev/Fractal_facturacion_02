<?php

namespace App\Filament\Pages\UserMetrics\Widgets;

use App\Models\Cliente;
use App\Models\CanalVentafd;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class TopChannelsPieChart extends ChartWidget
{
    protected static ?string $pollingInterval = null;

    // Se sobrescribe con getHeading()
    protected static ?string $heading = 'Top canales de venta';

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
        // Soporta limpiar filtro (null)
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

    // 🔹 Encabezado dinámico
        public function getHeading(): ?string
        {
            if ($this->dateStart && $this->dateEnd) {
                return 'Top canales de venta (' . $this->dateStart . ' → ' . $this->dateEnd . ')';
            }
            return 'Top canales de venta (sin filtro)';
        }
    protected function getData(): array
    {
        $base = $this->scopeOwned(Cliente::query());

        // ✅ Aplica rango solo si está definido
        if ($this->dateStart && $this->dateEnd) {
            $inicio = Carbon::parse($this->dateStart)->startOfDay();
            $fin    = Carbon::parse($this->dateEnd)->endOfDay();
            $base->whereBetween('clientes.created_at', [$inicio, $fin]);
        }

        // Agrupa por canal (máx 6) y evita duplicados con DISTINCT por cliente
        $rows = $base
            ->selectRaw('clientes.ID_Canal_venta as canal_id, COUNT(DISTINCT clientes.id_cliente) as total')
            ->groupBy('canal_id')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        // Nombres de canales
        $labels = $rows->map(function ($r) {
            $canal = optional(CanalVentafd::find($r->canal_id))->canal;
            return $canal ?: ('Canal '.$r->canal_id);
        })->all();

        $data = $rows->pluck('total')->map(fn ($n) => (int) $n)->all();

        $colors = [
            '#60a5fa', // azul
            '#34d399', // verde
            '#fbbf24', // amarillo
            '#f87171', // rojo
            '#a78bfa', // violeta
            '#f472b6', // rosado
        ];

        return [
            'datasets' => [[
                'label'           => 'Clientes',
                'data'            => $data,
                'backgroundColor' => array_slice($colors, 0, count($data)),
                'hoverOffset'     => 10,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
