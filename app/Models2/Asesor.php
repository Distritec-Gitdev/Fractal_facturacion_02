<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// app/Models/Asesor.php
class Asesor extends Model
{
    protected $connection = 'inf_asesores';
    protected $table = 'asesores';
    protected $primaryKey = 'ID_Asesor';

    protected $fillable = [
        'Nombre',
        'Cedula'
        
       
    ];
}