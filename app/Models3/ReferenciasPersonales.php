<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferenciasPersonales extends Model
{
    protected $table = 'referencias_personales'; // Nombre de la tabla en la BD
    protected $primaryKey = 'id_referencia'; // Clave primaria personalizada
    
    protected $fillable = [
        'id_cliente',
        'ID_Referencia1',
        'ID_Referencia2'
    ];

   // Modelo ReferenciaPersonal.php
public function cliente()
{
    return $this->belongsTo(Cliente::class, 'id_cliente');
}
// Modelo ReferenciasPersonales.php (tu tabla pivot)
public function referenciaPersonal1()
{
    return $this->belongsTo(ReferenciaPersonal1::class, 'ID_Referencia1');
}
    public function referenciaPersonal2(): BelongsTo
    {
        return $this->belongsTo(
            ReferenciaPersonal2::class,
            'ID_Referencia2', // Columna en tabla referencias_personales
            'ID_Referencia2'  // Columna en tabla referencia_personal2
        );
    }

}
