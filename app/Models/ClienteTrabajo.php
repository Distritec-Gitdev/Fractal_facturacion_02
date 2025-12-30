<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteTrabajo extends Model
{
    protected $table = 'clientes_trabajo';
    protected $primaryKey = 'id_trabajo';
    public $timestamps = false;

    protected $fillable = [
        'id_cliente',
        'empresa_labor',
        'num_empresa',
        'es_independiente',
    ];

    // Opcional: para que siempre lo obtengas como booleano
    protected $casts = [
        'es_independiente' => 'boolean',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }
}