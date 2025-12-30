<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZMarca extends Model
{
    protected $table = 'z_marca';
    protected $primaryKey = 'idmarca';
    public $timestamps = false;

    protected $fillable = ['name_marca'];

    protected static function booted(): void
    {
        static::addGlobalScope('hide_na', function (Builder $q) {
            $q->whereRaw("UPPER(TRIM(name_marca)) NOT IN ('N/A','NA','NO APLICA')");
        });
    }

    public function modelos(): HasMany
    {
        return $this->hasMany(ZModelo::class, 'idmarca', 'idmarca');
    }
}
