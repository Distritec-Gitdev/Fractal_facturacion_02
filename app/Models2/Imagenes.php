<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Imagenes extends Model
{
    protected $table = 'imagenes';
    protected $primaryKey = 'id'; // Cambia si tu PK es diferente
    public $timestamps = false;

    protected $fillable = [
        'id_cliente',
        'imagen_cedula_cara_delantera',
        'imagen_cedula_cara_trasera',
        'imagen_persona_con_cedula',
        'Carta_de_Garantías',
        'Carta_de_Garantia2',
        'Carta_antifraude',
        'Carta_antifraude_alo',
        'recibo_publico',
        'terminosycondiciones',
        'estado_archivos',
    ];
}