<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    protected $connection = 'inf_asesores';
    protected $table = 'sedes';
    protected $primaryKey = 'ID_Sede';
    public $timestamps = false;

    protected $fillable = [
        'ID_Sede',
        'Name_Sede',
        'ID_Socio',
        'Codigo_de_sucursal',
        'Codigo_caja',
        'Prefijo',
        'Centro_costo',
        'Estado',
    ];
}
