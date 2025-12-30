<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class YTipoVenta extends Model
{
    use HasFactory;
    
    protected $connection = 'inf_asesores';

    // Nombre exacto de la tabla
    protected $table = 'y_tipo_venta';

    // Clave primaria
    protected $primaryKey = 'ID_Tipo_Venta';

    // Si la PK es autoincremental (por lo que se ve, sí)
    public $incrementing = true;

    // Tipo de la PK (int)
    protected $keyType = 'int';

    // La tabla no tiene created_at / updated_at
    public $timestamps = false;

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'Cod_Patron_Contable',
        'Nombre_Patron_Contable',
        'Nombre_Tipo_Venta',
    ];

    // Si usas una conexión distinta a la default "mysql",
    // descomenta y ajusta esto:
    // protected $connection = 'inf_asesores_test';
}
