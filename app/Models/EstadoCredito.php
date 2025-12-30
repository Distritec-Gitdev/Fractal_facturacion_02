<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoCredito extends Model
{
    protected $table = 'z_estdo_credito';
    protected $primaryKey = 'ID_Estado_cr';
    
    protected $fillable = [
        'ID_Estado_cr',
        'Estado_Credito'
    ];
}