<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YMedioPago extends Model
{
    use HasFactory;

    protected $connection = 'inf_asesores';

    // Nombre real de la tabla
    protected $table = 'y_medio_pago';

    // Clave primaria
    protected $primaryKey = 'Id_Medio_Pago';

    public $incrementing = true;
    protected $keyType   = 'int';

    // No tiene timestamps
    public $timestamps = false;

    protected $fillable = [
        'Nombre_Medio_Pago',
        'Codigo_forma_Pago',
    ];
}
