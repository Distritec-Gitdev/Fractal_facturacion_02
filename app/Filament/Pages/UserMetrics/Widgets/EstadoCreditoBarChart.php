<?php

namespace App\Filament\Pages\UserMetrics\Widgets;

use App\Models\Cliente;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class EstadoCreditoBarChart extends ChartWidget
{
    protected static ?string $pollingInterval = null;

    // Se sobrescribe con getHeading()
    protected static ?string $heading = 'Clientes por estado de crédito';

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
            return 'Clientes por estado de crédito (' . $this->dateStart . ' → ' . $this->dateEnd . ')';
        }
        return 'Clientes por estado de crédito (sin filtro)';
    }

    protected function getData(): array
    {
        $base = $this->scopeOwned(Cliente::query())
            ->leftJoin('gestion as g', 'g.id_cliente', '=', 'clientes.id_cliente')
            ->leftJoin('z_estdo_credito as ec', 'ec.ID_Estado_cr', '=', 'g.ID_Estado_cr');

        // ✅ Si hay rango, filtramos; si no, mostramos todo
        if ($this->dateStart && $this->dateEnd) {
            $inicio = Carbon::parse($this->dateStart)->startOfDay();
            $fin    = Carbon::parse($this->dateEnd)->endOfDay();

            // Si prefieres filtrar por fecha de gestión, cambia 'clientes.created_at' por 'g.created_at'
            $base->whereBetween('clientes.created_at', [$inicio, $fin]);
        }

        // ⚠️ COUNT(DISTINCT ...) para evitar duplicados si un cliente tiene varias gestiones
        $rows = $base
            ->selectRaw('COALESCE(ec.Estado_Credito, "Sin estado") as estado, COUNT(DISTINCT clientes.id_cliente) as total')
            ->groupBy('estado')
            ->orderByDesc('total')
            ->get();

        $labels = $rows->pluck('estado')->all();
        $data   = $rows->pluck('total')->all();

        return [
            'datasets' => [[
                'label'           => 'Clientes',
                'data'            => $data,
                'backgroundColor' => [
                    '#60a5fa', // azul
                    '#34d399', // verde
                    '#fbbf24', // amarillo
                    '#f87171', // rojo
                    '#a78bfa', // violeta
                    '#f472b6', // rosado
                    '#6b7280', // gris
                ],
                'borderWidth'     => 1,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
