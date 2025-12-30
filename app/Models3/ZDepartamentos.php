<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZDepartamentos extends Model
{
    protected $table = 'z_departamentos';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'name_departamento',
    ];

   
}
