<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VarianteConsignacion extends Model
{
    protected $connection = 'productos2';
    protected $table = 'variantes_consignacion';
    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'producto_id',
        'variante',
        'estado',
        'fecha_devolucion',
        'id_responsable_devolucion',
        'cantidad',
    ];


}
