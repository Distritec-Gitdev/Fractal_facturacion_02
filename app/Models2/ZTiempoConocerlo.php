<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ZTiempoConocerlo extends Model
{
    protected $table = 'z_tiempo_conocerlo'; // Nombre de la tabla en la BD
    protected $primaryKey = 'idtiempoconocerlo'; // Clave primaria personalizada
    
    protected $fillable = [
        'tiempo' 
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('hide_na', function (Builder $q) {
            $q->whereRaw("UPPER(TRIM(tiempo)) NOT IN ('N/A','NA','NO APLICA')");
        });
    }
}
