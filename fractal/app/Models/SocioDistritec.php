<?php
// app/Models/SocioDistritec.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocioDistritec extends Model
{
    protected $connection = 'inf_asesores'; // 👈 AJUSTA a tu conexión real
    protected $table = 'socios_distritec';
    protected $primaryKey = 'ID_Socio';
    public $timestamps = false;

    protected $fillable = [
        'ID_Socio',
        'Cedula',
        'ID_Estado',
        'soc_cod_vendedor',
        'Socio',
    ];
}
