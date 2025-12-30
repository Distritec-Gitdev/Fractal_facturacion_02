<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZPlataformaCredito extends Model
{
    protected $table = 'z_plataforma_credito'; // Nombre de la tabla en la BD
    protected $primaryKey = 'idplataforma'; // Clave primaria personalizada
    
    protected $fillable = [
        'plataforma' 
    ];
}
