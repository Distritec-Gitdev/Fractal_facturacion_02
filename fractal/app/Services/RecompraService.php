<?php

namespace App\Services;

use App\Services\DuplicatesService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;


//---------------------- EVALUAR RECOMPRA -----------------------
class RecompraService
{
    public const RECOMPRA     = 'recompra';
    public const DUPLICADO    = 'duplicado';      // valor aparece en duplicados
    public const COMPRA_NUEVA = 'compra_nueva';
    public const DESCONOCIDO  = 'desconocido';

    public function __construct(private DuplicatesService $dups) {}

    public function procesarRecompra(
        string $esRecompra,
        string $datoABuscar,
        string $table,
        string $column,
        string $pk = 'id',
        string $mensage
    ): string {
        // Caso recompra marcada explícitamente
        /*if ($esRecompra === 'Si') {
            Notification::make()->title('Es una recompra')->success()->send();
            return self::RECOMPRA;
        }*/

        // No es recompra: consultar duplicados y buscar el valor
        if ($esRecompra === 'Si') {
            $payload = $this->dups->find($table, [$column], $pk);

            if ($this->estaEnDuplicados($payload, $table, $column, $datoABuscar)) {
                Notification::make()->title($mensage)->warning()->send();
                return self::DUPLICADO;
            }

            return self::COMPRA_NUEVA;
        }

        return self::DESCONOCIDO;
    }

    private function extraerGrupos(mixed $payload, string $table): array
    {
        if ($payload instanceof Collection) {
            $payload = $payload->toArray();
        }

        if (is_array($payload)) {
            if (isset($payload['groups']) && is_array($payload['groups'])) {
                return $payload['groups'];
            }
            if (isset($payload['data'][$table]) && is_array($payload['data'][$table])) {
                return $payload['data'][$table];
            }
            if (isset($payload[0]) && is_array($payload[0]) && array_key_exists('values', $payload[0])) {
                return $payload;
            }
        }

        return [];
    }

    private function estaEnDuplicados(mixed $payload, string $table, string $column, string $datoABuscar): bool
    {
        $grupos = $this->extraerGrupos($payload, $table);

        foreach ($grupos as $g) {
            // match por los valores agrupados
            $valorGrupo = $g['values'][$column] ?? null;
            if ($valorGrupo !== null && (string)$valorGrupo === (string)$datoABuscar) {
                return true;
            }

            // match por filas del grupo
            $rows = $g['rows'] ?? [];
            if ($rows instanceof Collection) $rows = $rows->all();

            foreach ($rows as $row) {
                $valorFila = is_array($row)
                    ? ($row[$column] ?? null)
                    : (is_object($row) ? ($row->{$column} ?? null) : null);

                if ($valorFila !== null && (string)$valorFila === (string)$datoABuscar) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @deprecated usa procesarRecompra() */
    public function evaluarCompra(string $esRecompra, string $datoABuscar, string $table, string $column, string $pk = 'id', string $mensage): string
    {
        @trigger_error(__METHOD__.' está deprecado; usa procesarRecompra()', E_USER_DEPRECATED);
        return $this->procesarRecompra($esRecompra, $datoABuscar, $table, $column, $pk, $mensage);
    }
}
