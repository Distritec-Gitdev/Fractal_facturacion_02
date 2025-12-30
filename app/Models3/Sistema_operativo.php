<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sistema_operativo extends Model
{
    use HasFactory;

    protected $table = 'sistema_operativo_equipo';
    protected $primaryKey = 'ID_Sit_operativo';
    
    protected $fillable = [
        'name_sisOpet',
        
    ];

   
}
