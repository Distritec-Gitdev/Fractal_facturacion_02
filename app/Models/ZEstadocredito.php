<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZEstadocredito extends Model
{
     protected $table = 'z_estdo_credito';
    protected $primaryKey = 'ID_Estado_cr';

    protected $fillable = ['Estado_Credito'];
}
