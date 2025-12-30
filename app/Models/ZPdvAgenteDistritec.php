<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZPdvAgenteDistritec extends Model
{
    protected $table = 'z_pdv_agente_distritec'; // Nombre de la tabla en la BD
    protected $primaryKey = 'ID_PDV_agente'; // Clave primaria personalizada
    
    protected $fillable = [
        'PDV_agente' 
    ];

}
