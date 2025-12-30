<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Clase de Eloquent para el modelo de productos / modelos.
 *
 * Tabla: z_modelo
 * PK: idmodelo
 * Timestamps: NO (control manual)
 *
 * Campo clave:
 * - estado (boolean/int 0/1): 1 = Habilitado, 0 = Inhabilitado
 *
 * Notas:
 * - Agregamos 'estado' a $fillable para permitir asignación masiva (mass assignment) desde Filament.
 * - Definimos $casts['estado'] para que Filament y Eloquent lo traten coherentemente.
 * - $attributes['estado'] define un valor por defecto a nivel de modelo (útil al crear registros).
 * - Global Scope: oculta filas con name_modelo en ('N/A', 'NA', 'NO APLICA').
 */
class ZModelo extends Model
{
    /** @var string Nombre de la tabla */
    protected $table = 'z_modelo';

    /** @var string Clave primaria */
    protected $primaryKey = 'idmodelo';

    /** @var bool Deshabilitar timestamps por no existir en la tabla */
    public $timestamps = false;

    /**
     * Campos que se pueden asignar masivamente.
     * IMPORTANTE: incluir 'estado' para que los botones de Filament puedan persistir cambios.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'idmarca',        // FK -> z_marca.idmarca
        'id_categoria',   // FK -> z_categoria.id_categoria
        'name_modelo',
        'estado',         // Necesario para update(['estado' => 0/1]) y ToggleColumn
    ];

    /**
     * Atributos por defecto cuando se instancia el modelo.
     * @var array<string, mixed>
     */
    protected $attributes = [
        // 1 = Habilitado por defecto (puedes cambiarlo si tu negocio lo requiere)
        'estado' => 1,
    ];

    /**
     * Conversión de tipos (casts) para atributos.
     * 'boolean' hace que 0/1 se maneje como false/true en PHP.
     * Si prefieres trabajar con enteros, usa 'integer'.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'estado' => 'boolean',
    ];

    /**
     * Boot del modelo: agrega un Global Scope que oculta registros “N/A”.
     * Esto evita que se listen opciones inválidas o placeholders.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('hide_na', function (Builder $q) {
            $q->whereRaw("UPPER(TRIM(name_modelo)) NOT IN ('N/A','NA','NO APLICA')");
        });
    }

    /**
     * Relación con Marca.
     *
     * @return BelongsTo<ZMarca, ZModelo>
     */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(ZMarca::class, 'idmarca', 'idmarca');
    }

    /**
     * Relación con Categoría.
     *
     * @return BelongsTo<ZCategoria, ZModelo>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(ZCategoria::class, 'id_categoria', 'id_categoria');
    }
}
