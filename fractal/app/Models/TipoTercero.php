<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoTercero extends Model
{
    // Conexión 'mysql'
    protected $table = 'tipo_tercero';
    protected $primaryKey = 'id';

    //  IMPORTANTE:
    public $incrementing = true;   // porque 'id' es AUTO_INCREMENT
    protected $keyType = 'int';
    public $timestamps = false;    // la tabla no tiene created_at/updated_at

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    /**
     * Relación con terceros (Agentes, Clientes, etc.)
     */
    public function agentes(): HasMany
    {
        return $this->hasMany(Agentes::class, 'id_tipo_tercero', 'id');
    }
}
