<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZBodegaFacturacion extends Model
{
    use HasFactory;

    // Conexión (igual que tu otro modelo)
    protected $connection = 'inf_asesores';

    // Nombre exacto de la tabla
    protected $table = 'z_bodega_facturacion';

    // Clave primaria
    protected $primaryKey = 'ID_Bog';

    // La PK es autoincremental
    public $incrementing = true;

    // Tipo de la PK
    protected $keyType = 'int';

    // La tabla no tiene created_at / updated_at
    public $timestamps = false;

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'Nombre_Bodega',
        'Cod_Bog',
        'ID_Sede',
    ];

    // (Opcional) casts
    protected $casts = [
        'ID_Bog'  => 'integer',
        'ID_Sede' => 'integer',
    ];
}