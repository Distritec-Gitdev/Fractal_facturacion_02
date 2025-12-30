<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $connection = 'productos2';
    protected $table      = 'productos';
    protected $primaryKey = 'producto_id';
    public $timestamps    = false;

    protected $fillable = [
        'producto_id',
        'id_consignacion',
        'codigo',
        'descripcion',
        'estado',
        'valor_unitario',
        'id_bodega',
        'created_at',
    ];

    /**
     * Consignaciones asociadas a este producto.
     */
    public function consignaciones()
    {
        return $this->hasMany(Consignacion::class, 'producto_id', 'producto_id');
    }

    /**
     * Variante de consignación asociada al producto (si existe).
     */
    public function variante()
    {
        return $this->hasOne(VarianteConsignacion::class, 'producto_id', 'producto_id');
    }
}