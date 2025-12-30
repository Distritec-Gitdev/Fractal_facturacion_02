<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientesContacto extends Model
{
    protected $table = 'clientes_contacto';
    protected $primaryKey = 'id_contacto';
    
    protected $fillable = [
        'id_cliente',
        'correo',
        'tel',
        'tel_alternativo',
        'residencia_id_departamento',
        'residencia_id_municipio',
        'direccion',
        'empresa_labor',
        'tel_empresa',
        'es_independiente',
        'fecha_expedicion',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function departamento()
    {
        return $this->belongsTo(ZDepartamentos::class, 'residencia_id_departamento', 'id');
    }

    public function municipios()
    {
        return $this->belongsTo(ZMunicipios::class, 'residencia_id_municipio', 'id');
    }
}