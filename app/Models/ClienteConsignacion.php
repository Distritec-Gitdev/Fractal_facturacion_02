<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClienteConsignacion extends Model
{
    use HasFactory;

    protected $connection = 'productos2';
    protected $table = 'cliente_consignacion';

    protected $primaryKey = 'id_cliente';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'cedula',
        'nombre1_cliente',
        'nombre2_cliente',
        'apellido1_cliente',
        'apellido2_cliente',
    ];

    protected $casts = [
        'id_cliente' => 'integer',
        'cedula' => 'string',
        'nombre1_cliente' => 'string',
        'nombre2_cliente' => 'string',
        'apellido1_cliente' => 'string',
        'apellido2_cliente' => 'string',
    ];

    public function consignaciones()
    {
        return $this->hasMany(Consignacion::class, 'id_cliente', 'id_cliente');
    }
}