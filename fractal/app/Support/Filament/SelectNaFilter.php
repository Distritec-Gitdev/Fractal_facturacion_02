<?php

namespace App\Support\Filament;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;

final class SelectNaFilter
{
    /** Palabras a excluir (normalizadas) */
    private const BAN_LIST = ['N/A', 'NA', 'NO APLICA'];

    /**
     * Recorre el schema y a cada Select le agrega un filtro de opciones:
     * - Inmediato (por si ya hay opciones cargadas)
     * - Y también en afterStateHydrated (por si se vuelven a hidratar)
     */
    public static function apply(array $components): array
    {
        foreach ($components as $component) {
            if ($component instanceof Select) {
                // 1) Filtrado inmediato (si ya resolvió opciones)
                $opts = $component->getOptions();
                if (is_array($opts)) {
                    $component->options(self::filtered($opts));
                }

                // 2) Filtrado tras hidratar (cuando Livewire vuelve a montar el form)
                $component->afterStateHydrated(function (Select $select) {
                    $resolved = $select->getOptions();
                    if (is_array($resolved)) {
                        $select->options(SelectNaFilter::filtered($resolved));
                    }
                });
            }

            // Recurre en hijos (Sections, Grids, Repeaters, Steps, etc.)
            if (method_exists($component, 'getChildComponents')) {
                self::apply($component->getChildComponents());
            }
        }

        return $components;
    }

    /** Filtra etiquetas prohibidas sin romper keys */
    private static function filtered(array $options): array
    {
        $ban = array_map(
            fn ($s) => trim(mb_strtoupper($s)),
            self::BAN_LIST
        );

        return collect($options)
            ->reject(function ($label) use ($ban) {
                $labelNorm = trim(mb_strtoupper((string) $label));
                return in_array($labelNorm, $ban, true);
            })
            ->all();
    }
}
