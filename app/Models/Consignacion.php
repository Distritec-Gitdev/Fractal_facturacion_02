<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consignacion extends Model
{
    protected $connection = 'productos2';
    protected $table = 'consignaciones';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    // Tu tabla NO tiene created_at / updated_at
    public $timestamps = false;

    /**
     * Si quieres tratar "fecha" como la fecha principal del modelo (útil para orden, casts, etc.)
     */
    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $fillable = [
        'vendedor_id',
        'producto_id',
        'id_cliente',
        'bodega_id',
        'cantidad',
        'fecha', // por si la seteas manualmente (aunque tenga CURRENT_TIMESTAMP)
    ];

    protected $casts = [
        'id'          => 'integer',
        'vendedor_id' => 'integer',
        'producto_id' => 'integer',
        'id_cliente'  => 'integer',
        'bodega_id'   => 'integer',
        'cantidad'    => 'integer',
        'fecha'       => 'datetime', // importante
    ];

    // =========================
    // Relaciones
    // =========================

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(ClienteConsignacion::class, 'id_cliente', 'id_cliente');
    }

    public function bodega(): BelongsTo
    {
        // OJO: este modelo debe tener bien $connection y $table
        return $this->belongsTo(ZBodegaFacturacion::class, 'bodega_id', 'ID_Bog');
    }

    public function producto(): BelongsTo
    {
        // Ajusta la PK real del modelo Producto si no es 'producto_id'
        return $this->belongsTo(Producto::class, 'producto_id', 'producto_id');
    }

    /**
     * Opcional: solo si tienes el modelo Vendedor y lo vas a usar en Filament
     * Si no existe, comenta o elimínalo para que no vuelva a explotar.
     */
    // public function vendedor(): BelongsTo
    // {
    //     return $this->belongsTo(Vendedor::class, 'vendedor_id', 'id');
    // }

    // =========================
    // Accessors
    // =========================

    public function getNombreClienteAttribute(): ?string
    {
        $cliente = $this->cliente;

        if (! $cliente) {
            return null;
        }

        $nombre = trim(implode(' ', array_filter([
            $cliente->nombre1_cliente ?? null,
            $cliente->nombre2_cliente ?? null,
            $cliente->apellido1_cliente ?? null,
            $cliente->apellido2_cliente ?? null,
        ])));

        return $nombre !== '' ? $nombre : null;
    }
}
