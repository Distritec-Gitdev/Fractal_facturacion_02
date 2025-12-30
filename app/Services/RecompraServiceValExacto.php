<?php

namespace App\Services;

use Filament\Notifications\Notification;

class RecompraServiceValExacto
{
    public const ENCONTRADO   = 'encontrado_en_bd';
    public const COMPRA_NUEVA = 'compra_nueva';
    public const DESCONOCIDO  = 'desconocido';

    public function __construct(private ValueEqualsService $equals) {}

    public function procesarRecompraPorValorIgual(
        string $esRecompra,    // 'Si' | 'No'
        string $datoABuscar,
        string $table,
        string $column,
        string $pk,
        string $mensaje,
        bool $normalizar = false,
        mixed $excludeId = null
    ): string {
        if (trim($esRecompra) !== 'No') {
            return self::DESCONOCIDO;
        }

        $valor = is_string($datoABuscar) ? trim($datoABuscar) : $datoABuscar;

        $exists = $excludeId === null
            ? $this->equals->existsInColumn($table, $column, $valor, $normalizar)
            : $this->equals->existsInColumnExceptPk($table, $column, $valor, $pk, $excludeId, $normalizar);

        if ($exists) {
            Notification::make()->title($mensaje)->warning()->send();
            return self::ENCONTRADO;
        }

        return self::COMPRA_NUEVA;
    }
}
