<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfTrab extends Model
{
    protected $connection = 'inf_asesores'; // Nombre de la conexión externa
    protected $table = 'inf_trab'; // Nombre real de la tabla (sin pluralizar)
    protected $primaryKey = 'ID_Inf_trab';

    protected $fillable = [
        'Codigo_vendedor'
       
    ];

    // Opcional: Desactivar pluralización automática
    public $timestamps = false;

    // Relación con aa_prin
    public function aaPrin()
    {
        return $this->hasOne(AaPrin::class, 'ID_Inf_trab', 'ID_Inf_trab');
    }
}