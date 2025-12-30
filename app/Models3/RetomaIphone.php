<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetomaIphone extends Model
{
    protected $connection = 'productos2';
    protected $table = 'retoma_iphones';

    protected $fillable = [
        'idmarca','idmodelo','id_bodega',
        'imei','precio_compra',
        'estado_pantalla','bateria_porcentaje','estado_general',
        'observaciones',
        'codigo_vendedor','nombre_asesor',
        'score','calificacion',
    ];

    public $timestamps = true;

    protected static function booted(): void
    {
        static::saving(function (self $r) {
            [$score, $calif] = self::calcularScoreYCalificacion(
                (string) $r->estado_pantalla,
                (int) $r->bateria_porcentaje,
                (string) $r->estado_general
            );

            $r->score = $score;
            $r->calificacion = $calif;
        });
    }

    private static function calcularScoreYCalificacion(string $pantalla, int $bateria, string $general): array
    {
        $scorePantalla = match ($pantalla) {
            'excelente' => 5,
            'buena'     => 4,
            'rayada'    => 3,
            'rota'      => 1,
            default     => 2,
        };

        $scoreGeneral = match ($general) {
            'como_nuevo' => 5,
            'bueno'      => 4,
            'regular'    => 3,
            'malo'       => 1,
            default      => 2,
        };

        $scoreBateria = match (true) {
            $bateria >= 90 => 5,
            $bateria >= 80 => 4,
            $bateria >= 70 => 3,
            $bateria >= 60 => 2,
            default        => 1,
        };

        $total = $scorePantalla + $scoreBateria + $scoreGeneral; // 3..15
        $score = (int) round(($total / 15) * 100);

        $calif = match (true) {
            $score >= 85 => 'A',
            $score >= 70 => 'B',
            $score >= 55 => 'C',
            default      => 'D',
        };

        return [$score, $calif];
    }
}
