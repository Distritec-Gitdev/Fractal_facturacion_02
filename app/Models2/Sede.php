<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    protected $connection = 'inf_asesores';
    protected $table = 'sedes';
    protected $primaryKey = 'ID_Sede';

    protected $fillable = [
        'Name_Sede', 
        'ID_Socio  ',
        'ID_Sede', 
    ];
}