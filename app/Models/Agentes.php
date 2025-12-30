<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agentes extends Model
{
    // Conexión 'mysql'
    protected $table = 'terceros';
    protected $primaryKey = 'id_tercero';

    // ✅ IMPORTANTE:
    public $incrementing = true;   // se deja en true porque la columna es auto_increment
    protected $keyType = 'int';
    public $timestamps = true;    // tu tabla no tiene created_at/updated_at

    protected $fillable = [
        'nombre_pv',
        'nombre_tercero',
        'tipo_documento',
        'numero_documento',
        'correo',
        'telefono',
        'estado',
        'id_sede',
        'id_tipo_tercero',
    ];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'id_sede', 'ID_Sede');
        return $this->belongsTo(TipoTercero::class, 'id_tipo_tercero', 'id');
    }

     public function tipoTercero(): BelongsTo
    {
        return $this->belongsTo(TipoTercero::class, 'id_tipo_tercero', 'id');
    }

      public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento', 'ID_identificacion_tributaria');
    }
}
