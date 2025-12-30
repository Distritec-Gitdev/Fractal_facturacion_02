<?php 

namespace App\Filament\Resources\GestionClienteResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Cliente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class GestionClienteStats extends BaseWidget
{
    protected static ?string $pollingInterval = null; // ❌ sin polling
    
    protected function getStats(): array
    {
        $u = auth()->user();
        $super = config('filament-shield.super_admin.name', 'super_admin');
        $adminish = $u?->hasAnyRole([$super, 'admin']) ?? false;

        $base = Cliente::query();
        if (! $adminish) {
            $base->where('user_id', $u->id);
        }

        $total = (clone $base)->count();
        $hoy   = (clone $base)->whereDate('created_at', now()->toDateString())->count();
        $ult7  = (clone $base)->where('created_at', '>=', now()->subDays(7))->count();

        // 🔴 Documentación pendiente:
        $clienteIds = (clone $base)->pluck('id_cliente');

        $pendDocs = 0;
        if ($clienteIds->isNotEmpty()) {
            $pendDocs = DB::table('gestion')
                ->whereIn('id_cliente', $clienteIds)
                ->whereIn('ID_Estado_cr', [1, 2])
                ->where(function ($q) {
                    $q->whereNull('contImagenes_ID_SI_NO')
                      ->orWhere('contImagenes_ID_SI_NO', '<>', 1);
                })
                ->distinct('id_cliente')
                ->count('id_cliente');
        }

        return [
            Stat::make('Total registros', $total)
                ->description($adminish ? 'Global' : 'Tuyos')
                ->extraAttributes(['class' => 'text-lg']),

            Stat::make('Hoy', $hoy),

            Stat::make('Últimos 7 días', $ult7),

            // 🧾 Nueva casilla con "botón" en la descripción y click para mostrar cédulas
            Stat::make('Documentación pendiente', $pendDocs)
                ->description(
                    new HtmlString(
                        '<span style="
                            display:inline-block;
                            padding:6px 10px;
                            border:1px solid #ffffffd0;
                            border-radius:10px;
                            background:#fff3f3;
                            color:#7f1d1d;
                            font-weight:600;
                            font-size:12px;
                            line-height:1;
                            text-transform:none;
                            text-decoration:none;
                            cursor:pointer;
                            user-select:none;
                        ">Ver cédulas pendientes</span>'
                    )
                )
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle')
                ->extraAttributes([
                    'wire:click' => 'showPendientes', // abre ventana emergente (Notification)
                    'class'      => 'cursor-pointer select-none',
                    'onmouseover'=> "this.style.boxShadow='0 0 0 2px rgba(127,29,29,.15)';",
                    'onmouseout' => "this.style.boxShadow='';",
                    'style'      => implode(';', [
                        'background-color: #ff7070ff',
                        'border:1px solid #8d2828ff',
                        'color:#7f1d1d',
                        'border-radius:0.75rem',
                        'box-shadow:0 1px 2px rgb(0 0 0 / 0.05)',
                    ]),
                ]),
        ];
    }

    // 🔔 Al hacer clic, mostrar hasta 50 cédulas en una ventana emergente (Notification)
    public function showPendientes(): void
    {
        $u = auth()->user();
        $super = config('filament-shield.super_admin.name', 'super_admin');
        $adminish = $u?->hasAnyRole([$super, 'admin']) ?? false;

        $base = Cliente::query();
        if (! $adminish) {
            $base->where('user_id', $u->id);
        }

        $clienteIds = (clone $base)->pluck('id_cliente');

        $pendienteIds = collect();
        if ($clienteIds->isNotEmpty()) {
            $pendienteIds = DB::table('gestion')
                ->whereIn('id_cliente', $clienteIds)
                ->whereIn('ID_Estado_cr', [1, 2])
                ->where(function ($q) {
                    $q->whereNull('contImagenes_ID_SI_NO')
                      ->orWhere('contImagenes_ID_SI_NO', '<>', 1);
                })
                ->distinct('id_cliente')
                ->pluck('id_cliente');
        }

        // Traer hasta 50 cédulas
        $cedulas = collect();
        if ($pendienteIds->isNotEmpty()) {
            $cedulas = Cliente::query()
                ->whereIn('id_cliente', $pendienteIds)
                ->whereNotNull('cedula')
                ->orderBy('cedula')
                ->limit(50)
                ->pluck('cedula');
        }

        $totalPend  = $pendienteIds->count();
        $mostradas  = $cedulas->count();
        $restantes  = max(0, $totalPend - $mostradas);

        $lineas = $cedulas->map(fn ($c) => "• {$c}")->implode("\n");
        if ($lineas === '') {
            $lineas = 'No hay cédulas disponibles.';
        } elseif ($restantes > 0) {
            $lineas .= "\n… y {$restantes} más.";
        }

        Notification::make()
            ->title("Cédulas pendientes ({$mostradas}/{$totalPend})")
            ->body($lineas)   // lista (hasta 50)
            ->danger()        // rojo para mantener coherencia visual
            ->persistent()    // queda visible hasta cerrar
            ->send();
    }
}
