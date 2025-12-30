<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zcuotas extends Model
{
    protected $table = 'z_cuotas';
    protected $primaryKey = 'idcuotas';
    
    protected $fillable = [
        'num_cuotas',
        'idcuotas'
       
    ];
}
