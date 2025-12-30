<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZMunicipios extends Model
{
    protected $table = 'z_municipios';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'name_municipio',
        'departamento_id'
    ];
}
