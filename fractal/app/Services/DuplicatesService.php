<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DuplicatesService
{
    public function find(string $table, array $by, string $pk = 'id'): array
    {
        $groups = DB::table($table)
            ->select(array_merge($by, [DB::raw('COUNT(*) as dup_count')]))
            ->groupBy($by)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($groups->isEmpty()) return [];

        $result = [];
        foreach ($groups as $g) {
            $where = [];
            foreach ($by as $col) $where[$col] = $g->{$col};

            $rows = DB::table($table)
                ->where($where)
                ->orderByDesc($pk)
                ->limit(500)
                ->get();

            $result[] = [
                'by'        => $by,
                'values'    => $where,
                'dup_count' => (int) $g->dup_count,
                'rows'      => $rows,
            ];
        }

        return $result;
    }

    /** ¿Existe al menos 1 duplicado con estos valores? */
    public function exists(string $table, array $where): bool
    {
        return DB::table($table)->where($where)->exists();
    }
}
