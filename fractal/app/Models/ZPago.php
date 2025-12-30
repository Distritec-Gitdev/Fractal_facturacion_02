<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ZPago extends Model
{
    protected $table = 'z_pago';
    protected $primaryKey = 'idpago';
    
    protected $fillable = [
        'periodo_pago'
       
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('hide_na', function (Builder $q) {
            $q->whereRaw("UPPER(TRIM(periodo_pago)) NOT IN ('N/A','NA','NO APLICA')");
        });
    }
}
