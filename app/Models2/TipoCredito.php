<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoCredito extends Model
{
    use HasFactory;

    protected $table = 'tipo_credito';
    protected $primaryKey = 'ID_Tipo_credito';
    
    protected $fillable = [
        'ID_Tipo_credito',
        'Tipo',
        'Descripcion'
    ];

    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'ID_tipo_credito', 'ID_Tipo_credito');
    }
}
