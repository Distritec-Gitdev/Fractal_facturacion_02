<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispositivosComprados extends Model
{
    protected $table = 'dispositivos_comprados'; // Nombre de la tabla en la BD
    protected $primaryKey = 'id_dispositivo'; // Clave primaria personalizada
    
    protected $fillable = [
        'id_cliente',
        'id_marca',
        'idmodelo',
        'imei',
        'idgarantia',
        'producto_convenio',
        'nombre_producto',
        'estado_bateria',
        'estado_pantalla',
        'ID_Sit_operativo',
        'observacion_equipo',
    ];

    // Relación con GestorDistritec
    // app/Models/DispositivosComprados.php
    public function marca()
    {
        return $this->belongsTo(ZMarca::class, 'id_marca', 'idmarca');
    }

    // Relación con EstadoCredito
    public function modelo(): BelongsTo
    {
        return $this->belongsTo(
            ZModelo::class,
            'idmodelo', // Columna en tabla z_modelo
            'idmodelo'  // Columna en tabla dispositivos_comprados
        );
    }

    public function garantia(): BelongsTo
    {
        return $this->belongsTo(
            ZGarantia::class,
            'idgarantia', // Columna en tabla z_garantia
            'idgarantia'  // Columna en tabla dispositivos_comprados
        );
    }

     public function sistemaOperativo(): BelongsTo
    {
        return $this->belongsTo(
            Sistema_operativo::class,
            'ID_Sit_operativo', 
            'ID_Sit_operativo'  
        );
    }
}