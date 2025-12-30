<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ZComision extends Model
{
    protected $table = 'z_comision';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'comision'
       
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('hide_na', function (Builder $q) {
            $q->whereRaw("UPPER(TRIM(comision)) NOT IN ('N/A','NA','NO APLICA')");
        });
    }
}
