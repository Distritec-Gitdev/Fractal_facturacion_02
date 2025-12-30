<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZCategoria extends Model
{
    protected $table = 'z_categoria';
    protected $primaryKey = 'id_categoria';
    public $timestamps = false;

    protected $fillable = ['categoria'];

    public function modelos(): HasMany
    {
        // FK en ZModelo = id_categoria
        // Owner key en ZCategoria = id_categoria (¡no "categoria"!)
        return $this->hasMany(ZModelo::class, 'id_categoria', 'id_categoria');
    }
}
