<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YCuentaBanco extends Model
{
    use HasFactory;

    protected $connection = 'inf_asesores';

    // Nombre real de la tabla
    protected $table = 'y_cuenta_banco';

    // Clave primaria
    protected $primaryKey = 'ID_Banco';

    public $incrementing = true;
    protected $keyType   = 'int';

    // No tiene timestamps
    public $timestamps = false;

    protected $fillable = [
        'Nombre_Banco',
        'Cuenta_Banco',
    ];
}
