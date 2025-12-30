<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ValueSimilarService
{
    /**
     * Coincidencia básica por LIKE: contains | startswith | endswith
     */
    public function existsLike(
        string $table,
        string $column,
        string $value,
        string $pattern = 'contains', // contains|startswith|endswith
        bool $normalize = true,       // LOWER+TRIM
        bool $ignoreAccents = false   // solo Postgres (requiere extension unaccent)
    ): bool {
        $conn    = DB::connection();
        $grammar = $conn->getQueryGrammar();
        $driver  = $conn->getDriverName();
        $qb      = $conn->table($table);

        $wrapped = $grammar->wrap($column);
        $pattern = strtolower($pattern);
        if (!in_array($pattern, ['contains','startswith','endswith'], true)) {
            $pattern = 'contains';
        }

        // Construir patrón
        $needle = $value;
        if ($pattern === 'contains')   $needle = "%{$value}%";
        if ($pattern === 'startswith') $needle = "{$value}%";
        if ($pattern === 'endswith')   $needle = "%{$value}";

        // Normalización en SQL
        if ($normalize) {
            if ($driver === 'pgsql') {
                $left = "LOWER(TRIM({$wrapped}))";
                if ($ignoreAccents) $left = "unaccent($left)";
                $qb->whereRaw("$left LIKE LOWER(TRIM(?))", [$needle]);
            } else {
                // MySQL/MariaDB/SQLite
                $left = "LOWER(TRIM({$wrapped}))";
                $qb->whereRaw("$left LIKE LOWER(TRIM(?))", [$needle]);
            }
            return $qb->exists();
        }

        // Sin normalización
        return $qb->where($column, 'LIKE', $needle)->exists();
    }

    /**
     * Coincidencia fonética (MySQL/MariaDB/SQLite): SOUNDEX
     * Nota: muy grosera pero útil para apellidos (Gonzales/Gonzalez).
     */
    public function existsphone_exact(
        string $table,
        string $column,
        string $value
    ): bool {
        $conn    = DB::connection();
        $grammar = $conn->getQueryGrammar();
        $qb      = $conn->table($table);
        $wrapped = $grammar->wrap($column);

        return $qb->whereRaw("SOUNDEX($wrapped) = SOUNDEX(?)", [$value])->exists();
    }

    /**
     * PostgreSQL – Distancia de edición (Levenshtein)
     * Requiere: CREATE EXTENSION IF NOT EXISTS fuzzystrmatch;
     */
    public function existsLevenshteinPg(
        string $table,
        string $column,
        string $value,
        int $maxDistance = 2,
        bool $ignoreAccents = false
    ): bool {
        $conn    = DB::connection();
        if ($conn->getDriverName() !== 'pgsql') {
            // No es Postgres: no disponible
            return false;
        }

        $grammar = $conn->getQueryGrammar();
        $qb      = $conn->table($table);
        $wrapped = $grammar->wrap($column);

        $left = "LOWER($wrapped)";
        $rightVal = $value;

        if ($ignoreAccents) {
            $left = "unaccent($left)";
        }

        // levenshtein(left, right) <= maxDistance
        return $qb->whereRaw("levenshtein($left, LOWER(?)) <= ?", [$rightVal, $maxDistance])->exists();
    }

    /**
     * PostgreSQL – Trigramas (similaridad)
     * Requiere: CREATE EXTENSION IF NOT EXISTS pg_trgm;
     * Ajusta threshold (0..1). Típicamente 0.3 - 0.6.
     */
    public function existsTrigramPg(
        string $table,
        string $column,
        string $value,
        float $threshold = 0.35,
        bool $ignoreAccents = false
    ): bool {
        $conn    = DB::connection();
        if ($conn->getDriverName() !== 'pgsql') {
            return false;
        }

        $grammar = $conn->getQueryGrammar();
        $qb      = $conn->table($table);
        $wrapped = $grammar->wrap($column);

        $left = $wrapped;
        $rightVal = $value;

        if ($ignoreAccents) {
            $left = "unaccent($left)";
            $rightVal = $this->unaccentPhp($rightVal);
        }

        // similarity(col, ?) >= threshold
        return $qb->whereRaw("similarity($left, ?) >= ?", [$rightVal, $threshold])->exists();
        // Alternativa corta: WHERE $left % ?  (usa el threshold global)
    }

    /**
     * Búsqueda tipo “full text” (MySQL) – útil para campos largos.
     * Requiere índice FULLTEXT en la columna.
     */
    public function existsFulltextMySQL(
        string $table,
        string $column,
        string $value,
        string $mode = 'NATURAL LANGUAGE MODE' // o 'BOOLEAN MODE' / 'WITH QUERY EXPANSION'
    ): bool {
        $conn = DB::connection();
        if (!in_array($conn->getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        $qb = $conn->table($table);
        return $qb->whereRaw("MATCH({$column}) AGAINST (? IN {$mode})", [$value])->exists();
    }

    /**
     * Pequeño helper para quitar acentos en PHP (fallback cuando no usas unaccent en SQL).
     */
    private function unaccentPhp(string $s): string
    {
        $map = [
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N',
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n'
        ];
        return strtr($s, $map);
    }

    /* ===================== NUEVO: BÚSQUEDA DE TELÉFONOS ===================== */

    /**
     * Teléfono EXACTO por dígitos: compara el input y la columna tras quitar
     * todos los caracteres no numéricos. Si coinciden los dígitos, existe.
     *
     * Útil cuando quieres igualdad estricta de número, ignorando formato (+57, espacios, guiones, etc).
     */
    public function existsPhoneExact(
        string $table,
        string $column,
        string $phoneRaw,
        ?string $pk = null,
        mixed $excludeId = null
    ): bool {
        $conn    = DB::connection();
        $grammar = $conn->getQueryGrammar();
        $driver  = $conn->getDriverName();
        $qb      = $conn->table($table);

        // Normaliza input → solo dígitos
        $digits = preg_replace('/\D+/', '', (string)$phoneRaw) ?? '';

        $wrapped = $grammar->wrap($column);

        if ($driver === 'pgsql') {
            $colDigits = "REGEXP_REPLACE($wrapped, '[^0-9]', '', 'g')";
        } else {
            // MySQL/MariaDB/SQLite
            $supportsRegexp = true;
            try {
                $conn->select("SELECT REGEXP_REPLACE('a1 b2','[^0-9]','') AS t");
            } catch (\Throwable $e) {
                $supportsRegexp = false;
            }
            $colDigits = $supportsRegexp
                ? "REGEXP_REPLACE($wrapped, '[^0-9]', '')"
                : "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($wrapped,' ',''),'-',''),'(',''),')',''),'+',''),'.','')";
        }

        $qb->whereRaw("$colDigits = ?", [$digits]);

        if ($pk && $excludeId !== null) {
            $qb->where($pk, '!=', $excludeId);
        }

        return $qb->exists();
    }

    /**
     * Teléfono por dígitos normalizados comparando los ÚLTIMOS N dígitos (p.ej. 10).
     * Útil cuando unos números tienen prefijo país y otros no, pero el nacional
     * (últimos 10) debe coincidir.
     *
     * Si $lastDigits = 0, compara todos los dígitos (equivale a existsPhoneExact).
     */
    public function existsPhoneNormalized(
        string $table,
        string $column,
        string $phoneRaw,
        ?string $pk = null,
        mixed $excludeId = null,
        int $lastDigits = 10
    ): bool {
        $conn    = DB::connection();
        $grammar = $conn->getQueryGrammar();
        $driver  = $conn->getDriverName();
        $qb      = $conn->table($table);

        $digits = preg_replace('/\D+/', '', (string)$phoneRaw) ?? '';
        $needle = ($lastDigits > 0) ? substr($digits, -$lastDigits) : $digits;

        $wrapped = $grammar->wrap($column);

        if ($driver === 'pgsql') {
            $colDigits = "REGEXP_REPLACE($wrapped, '[^0-9]', '', 'g')";
            if ($lastDigits > 0) {
                $qb->whereRaw("RIGHT($colDigits, ?) = ?", [$lastDigits, $needle]);
            } else {
                $qb->whereRaw("$colDigits = ?", [$needle]);
            }
        } else {
            $supportsRegexp = true;
            try {
                $conn->select("SELECT REGEXP_REPLACE('a1 b2','[^0-9]','') AS t");
            } catch (\Throwable $e) {
                $supportsRegexp = false;
            }

            if ($supportsRegexp) {
                $colDigits = "REGEXP_REPLACE($wrapped, '[^0-9]', '')";
            } else {
                $colDigits = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($wrapped,' ',''),'-',''),'(',''),')',''),'+',''),'.','')";
            }

            if ($lastDigits > 0) {
                $qb->whereRaw("RIGHT($colDigits, ?) = ?", [$lastDigits, $needle]);
            } else {
                $qb->whereRaw("$colDigits = ?", [$needle]);
            }
        }

        if ($pk && $excludeId !== null) {
            $qb->where($pk, '!=', $excludeId);
        }

        return $qb->exists();
    }
}
