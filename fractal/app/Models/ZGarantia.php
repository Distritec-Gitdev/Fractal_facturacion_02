<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ZGarantia extends Model
{
    protected $table = 'z_garantia';
    protected $primaryKey = 'idgarantia';

    protected $fillable = ['garantia'];

    protected static function booted(): void
    {
        static::addGlobalScope('hide_na', function (Builder $q) {
            $q->whereRaw("UPPER(TRIM(garantia)) NOT IN ('N/A','NA','NO APLICA')");
        });
    }
}
