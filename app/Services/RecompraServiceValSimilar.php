<?php
declare(strict_types=1);

namespace App\Services;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class RecompraServiceValSimilar
{
    public const ENCONTRADO   = 'encontrado_en_bd';
    public const COMPRA_NUEVA = 'compra_nueva';
    public const DESCONOCIDO  = 'desconocido';

    public function __construct(private ValueSimilarService $similar) {}

    /**
     * Procesa validación de recompra (valor similar) sobre una o varias fuentes.
     *
     * @param ?string $esRecompra  Valor crudo del UI (radio/select). Si "sí", se busca.
     * @param ?string $valor       Valor a buscar.
     * @param string  $table       Tabla (si usas una sola fuente).
     * @param string  $column      Columna (si usas una sola fuente).
     * @param string  $mensaje     Título de notificación (si notify=true).
     * @param string  $strategy    Estrategia: like|valor_exacto|levenshtein|trigram|fulltext|phone_exact|phone
     * @param array   $options     Config extra:
     *                             - sources: array de [['tabla','columna', 'strategy'?,'options'?], ...]
     *                             - pattern: contains|startswith|endswith (para like)
     *                             - normalize: bool
     *                             - ignoreAccents: bool
     *                             - maxDistance: int (levenshtein)
     *                             - threshold: float (trigram)
     *                             - mode: string (fulltext mode)
     *                             - pk: string|null
     *                             - exclude: mixed|null (valor a excluir de pk)
     *                             - lastDigits: int (phone)
     *                             - notify: bool (false por defecto)
     */
    public function procesarRecompraPorValorSimilar(
        ?string $esRecompra,
        ?string $valor,
        string $table,
        string $column,
        string $mensaje,
        string $strategy = 'like',
        array $options = []
    ): string {
        try {
            // 1) ¿Debe correr?
            if (!$this->shouldRun($esRecompra)) {
                return self::COMPRA_NUEVA;
            }

            $valor = trim((string) $valor);
            if ($valor === '') {
                return self::COMPRA_NUEVA;
            }

            // 2) Fuentes: si viene options['sources'], usamos múltiples; si no, usamos table/column
            $sources = $options['sources'] ?? null;
            if ($sources !== null && !\is_array($sources)) {
                throw new InvalidArgumentException('options["sources"] debe ser un array de fuentes.');
            }

            $notify = (bool)($options['notify'] ?? false);

            // 3) Ejecutar búsqueda (una o varias fuentes)
            $found = false;

            if ($sources && \count($sources) > 0) {
                foreach ($sources as $src) {
                    // src = ['tabla','columna', 'strategy'?,'options'?]
                    if (!\is_array($src) || \count($src) < 2) {
                        throw new InvalidArgumentException('Cada fuente debe ser ["tabla","columna",("strategy")?,("options")?].');
                    }
                    [$t, $c] = [$src[0], $src[1]];
                    $strat   = $src[2] ?? $strategy;
                    $opts    = \is_array($src[3] ?? null) ? array_merge($options, $src[3]) : $options;

                    $found = $found || $this->runStrategy($strat, $t, $c, $valor, $opts);
                    if ($found) break; // si con una basta, cortamos
                }
            } else {
                // fuente única
                if ($table === '' || $column === '') {
                    throw new InvalidArgumentException('Los parámetros $table y $column no pueden estar vacíos.');
                }
                $found = $this->runStrategy($strategy, $table, $column, $valor, $options);
            }

            // 4) Notificación opcional
            if ($notify) {
                Notification::make()
                    ->title($found ? $mensaje : 'No encontrado')
                    ->{$found ? 'warning' : 'success'}()
                    ->send();
            }

            // 5) Log diagnóstico
            Log::info('Recompra similar', [
                'run'       => $this->shouldRun($esRecompra),
                'esRecompra'=> $esRecompra,
                'valor'     => $valor,
                'found'     => $found,
                'strategy'  => $strategy,
                'table'     => $table,
                'column'    => $column,
                'sources'   => $sources ? \count($sources) : 0,
            ]);

            return $found ? self::ENCONTRADO : self::COMPRA_NUEVA;

        } catch (\Throwable $e) {
            Log::error('Error en RecompraServiceValSimilar', [
                'msg' => $e->getMessage(),
                'line'=> $e->getLine(),
                'file'=> $e->getFile(),
            ]);
            // Opcional: notificar error
            // Notification::make()->title('Error validando recompra')->danger()->send();
            return self::DESCONOCIDO;
        }
    }

    /** Decide si debe ejecutarse la búsqueda según el valor del UI */
    private function shouldRun(?string $flag): bool
    {
        // Normaliza varios posibles valores de “Sí”
        $yes = ['1','2','si','sí','true','on','yes','y'];
        $f = strtolower(trim((string)$flag));
        return \in_array($f, $yes, true);
    }

    /** Ejecuta la estrategia indicada con parámetros normalizados */
    private function runStrategy(string $strategy, string $table, string $column, string $valor, array $options): bool
    {
        $strategy = strtolower(trim($strategy));
        $valid = [
            'valor_exacto','levenshtein','trigram','fulltext','phone_exact','phone','like',
        ];
        if (!\in_array($strategy, $valid, true)) {
            $strategy = 'like';
        }

        // Defaults
        $pattern       = isset($options['pattern']) && \in_array($options['pattern'], ['contains','startswith','endswith'], true)
            ? $options['pattern'] : 'contains';
        $normalize     = (bool)($options['normalize']     ?? true);
        $ignoreAccents = (bool)($options['ignoreAccents'] ?? false);
        $maxDistance   = (int) ($options['maxDistance']   ?? 2);
        $threshold     = (float)($options['threshold']    ?? 0.35);
        $mode          = is_string($options['mode'] ?? null) ? $options['mode'] : 'NATURAL LANGUAGE MODE';
        $pk            = $options['pk']      ?? null;
        $exclude       = $options['exclude'] ?? null;
        $lastDigits    = (int) ($options['lastDigits']    ?? 10);

        // Mapa estrategia -> callable
        return match ($strategy) {
            'valor_exacto' => $this->similar->existsPhoneExact($table, $column, $valor),
            'levenshtein'  => $this->similar->existsLevenshteinPg($table, $column, $valor, $maxDistance, $ignoreAccents),
            'trigram'      => $this->similar->existsTrigramPg($table, $column, $valor, $threshold, $ignoreAccents),
            'fulltext'     => $this->similar->existsFulltextMySQL($table, $column, $valor, $mode),
            'phone_exact'  => $this->similar->existsPhoneExact($table, $column, $valor, $pk, $exclude),
            'phone'        => $this->similar->existsPhoneNormalized($table, $column, $valor, $pk, $exclude, $lastDigits),
            default        => $this->similar->existsLike($table, $column, $valor, $pattern, $normalize, $ignoreAccents),
        };
    }
}
