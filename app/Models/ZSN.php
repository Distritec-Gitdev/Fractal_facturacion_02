<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ZSN extends Model
{
    use HasFactory;

    protected $table = 'z_si_no';
    protected $primaryKey = 'ID_SI_NO';
    public $timestamps = false;

    protected $fillable = ['Valor'];

    protected static function booted(): void
    {
        static::addGlobalScope('sin_na', function (Builder $query) {
            $query->whereRaw("UPPER(TRIM(Valor)) NOT IN ('M/A', 'N/A')");
        });
    }
}
