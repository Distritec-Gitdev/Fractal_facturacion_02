<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dependencias extends Model
{
    use HasFactory;

    // protected $connection = 'prueba'; // Eliminada la especificación de conexión
    protected $table = 'dependencias'; // Asegúrate de que coincida con el nombre de tu tabla
    protected $primaryKey = 'Id_Dependencia'; // Asegúrate de que coincida con tu clave primaria
    public $timestamps = false; // Si tu tabla no tiene timestamps

    protected $fillable = [
        'Nombre_Dependencia',
        'Codigo_Dependencia',
    ];

    protected $casts = [
        'Id_Dependencia' => 'integer',
    ];

    // Define relaciones si es necesario
} 