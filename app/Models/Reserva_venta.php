<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva_venta extends Model
{
    use HasFactory;

     protected $connection = 'inf_asesores';
    protected $table = 'reserva_venta';
    protected $primaryKey = 'ID_Reserva';

    protected $fillable = [
        'ID_Asesor',
        'Cedula_Vendedor',
        'ID_Sede_Asesor',
        'ID_Sede_Visita',
        'Nombre_Cliente',
        'Telefono_Cliente',
        'Tipo_Documento_Cliente',
        'N_Documento_Cliente',
        'Fecha_Registro_Venta',
        'Hora_Registro_Venta',
        'Estado',
        
       
    ];
}
