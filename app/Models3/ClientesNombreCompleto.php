<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientesNombreCompleto extends Model
{
    protected $table = 'clientes_nombre_completo';
    protected $primaryKey = 'ID_Cliente_Nombre';

    protected $fillable = [
        'ID_Cliente_Nombre',
        'id_cliente',
        'Primer_nombre_cliente',
        'Segundo_nombre_cliente',
        'Primer_apellido_cliente',
        'Segundo_apellido_cliente',
    ];  

        

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }
}