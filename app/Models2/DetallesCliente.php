<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallesCliente extends Model
{
    protected $table = 'detalles_cliente';
    protected $primaryKey = 'ID_Detalles_cliente';
    public $timestamps = true;

    protected $fillable = [
        'id_cliente',
        'idplataforma',
        'ID_Asesor',
        'idsede',
        'idcomision',
        'tiene_recompra',
        'esta_el_producto',
        'Id_Dependencia',
        'codigo_asesor',
        'ID_PDV_agente',
        'id_tipo_credito',
    ];

    // (Opcional pero recomendable) castear a enteros
    protected $casts = [
        'id_cliente'      => 'int',
        'idplataforma'    => 'int',
        'ID_Asesor'       => 'int',
        'idsede'          => 'int',
        'idcomision'      => 'int',
        'tiene_recompra' => 'int',
        'esta_el_producto' => 'int',
        'Id_Dependencia' => 'int',
        'codigo_asesor'   => 'int',
        'ID_PDV_agente'   => 'int',
        'id_tipo_credito' => 'int',
    ];

    public function plataformaCredito(): BelongsTo
    {
        return $this->belongsTo(
            ZPlataformaCredito::class,
            'idplataforma', // FK en esta tabla
            'idplataforma'  // PK en z_plataformas_credito
        );
    }

    public function comision(): BelongsTo
    {
        return $this->belongsTo(
            ZComision::class,
            'idcomision', // FK en esta tabla
            'id'          // PK en z_comisiones
        );
    }

    // Relación con Asesor (el modelo Asesor ya apunta a 'inf_asesores')
    public function asesor(): BelongsTo
    {
        return $this->belongsTo(Asesor::class, 'ID_Asesor', 'ID_Asesor');
    }

    // Relación con Sede (el modelo Sede ya apunta a 'inf_asesores')
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'idsede', 'ID_Sede');
    }

    public function agenteDistritec(): BelongsTo
    {
        return $this->belongsTo(
            Agentes::class,
            'ID_PDV_agente',
            'id_tercero'
        );
    }

    public function tipoCredito(): BelongsTo
    {
        return $this->belongsTo(TipoCredito::class, 'id_tipo_credito', 'ID_Tipo_credito');
    }
}
