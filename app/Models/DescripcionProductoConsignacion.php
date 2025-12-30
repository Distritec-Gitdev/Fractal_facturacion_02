<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DescripcionProductoConsignacion extends Model
{
    use HasFactory;

    // Conexión a la base de datos
    protected $connection = 'productos2';

    // Nombre de la tabla
    protected $table = 'descripcion_productos_consignacion';

    // Clave primaria
    protected $primaryKey = 'id';

    // La PK es autoincremental
    public $incrementing = true;

    // Tipo de la clave primaria
    protected $keyType = 'int';

    // Sin timestamps (created_at, updated_at)
    public $timestamps = false;

    // Campos asignables en masa
    protected $fillable = [
        'descripcion',
    ];

    // Casts de tipos de datos
    protected $casts = [
        'id' => 'integer',
        'descripcion' => 'string',
    ];


    public function productos()
    {
        // Ejemplo si tienes una relación con productos
        // return $this->hasMany(Producto::class, 'descripcion_consignacion_id', 'id');
    }
}