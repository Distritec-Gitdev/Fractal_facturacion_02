<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispositivosPago extends Model
{
    use HasFactory;

    protected $table = 'dispositivos_pago';
    protected $primaryKey = 'id_pagos'; 
    protected $fillable = [
        'fecha_pc',
        'idpago',
        'cuota_inicial',
        'num_cuotas',
        'valor_cuotas'
    ];

    protected $casts = [
        'cuota_inicial' => 'integer',
        'valor_cuotas' => 'integer',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(
            ZPago::class,
            'idpago', 
            'idpago'  
        );
    }


    public function cuotas(): BelongsTo
    {
        return $this->belongsTo(
            Zcuotas::class,
            'num_cuotas', 
            'idcuotas'  
        );
    }
}
