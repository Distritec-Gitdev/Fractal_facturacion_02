<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CanalVentafd extends Model
{
     use HasFactory;

    // protected $connection = 'prueba'; // Eliminada la especificación de conexión
    protected $table = 'canal_venta_fd'; // Asegúrate de que coincida con el nombre de tu tabla
    protected $primaryKey = 'ID_Canal_venta'; // Asegúrate de que coincida con tu clave primaria
    public $timestamps = false; // Si tu tabla no tiene timestamps

    protected $fillable = [
        'canal'
        // otros campos si los hay
    ];

    protected $casts = [
        'ID_Canal_venta' => 'integer',
    ];
}
