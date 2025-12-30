<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    use HasFactory;

    // protected $connection = 'prueba'; // Eliminada la especificación de conexión
    protected $table = 'tipo_documento'; // Asegúrate de que coincida con el nombre de tu tabla
    protected $primaryKey = 'ID_Identificacion_Tributaria'; // Asegúrate de que coincida con tu clave primaria
    public $timestamps = false; // Si tu tabla no tiene timestamps

    protected $fillable = [
        'desc_identificacion',
        // otros campos si los hay
    ];

    protected $casts = [
        'ID_Identificacion_Tributaria' => 'integer',
    ];

    // Define relaciones si es necesario
} 