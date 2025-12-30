<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GestorDistritec extends Model
{
    protected $table = 'z_gestores_distritec';
    protected $primaryKey = 'ID_Gestor';
    
    protected $fillable = [
       // 'ID_Gestor',
        'Nombre_gestor',
        'estado'
    ];
}