<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodigoProductoConsignacion extends Model
{
    use HasFactory;

    protected $connection = 'productos2';

    // Nombre de la tabla
    protected $table = 'codigos_productos_consignacion';

    // Clave primaria
    protected $primaryKey = 'id';

    // La PK es autoincremental
    public $incrementing = true;

    // Tipo de la clave primaria
    protected $keyType = 'int';

    // Sin timestamps 
    public $timestamps = false;

    // Campos asignables en masa
    protected $fillable = [
        'codigo',
    ];

    // Casts de tipos de datos
    protected $casts = [
        'id' => 'integer',
        'codigo' => 'string',
    ];

}