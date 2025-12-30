<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ZTiempoConocerloNum extends Model
{
    protected $table = 'z_tiempo_conocerlo_num'; // Nombre de la tabla en la BD
    protected $primaryKey = 'idtiempo_conocerlonum'; // Clave primaria personalizada
    
    protected $fillable = [
        'numeros' 
    ];


    protected static function booted(): void
    {
        static::addGlobalScope('hide_na', function (Builder $q) {
            $q->whereRaw("UPPER(TRIM(numeros)) NOT IN (0)");
        });
    }
}
