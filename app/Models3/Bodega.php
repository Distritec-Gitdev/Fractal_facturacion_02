<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bodega extends Model
{
    protected $connection = 'productos2';

    protected $table      = 'bodegas';
    protected $primaryKey = 'id';
    public $timestamps    = false;

    protected $fillable = [
        'codigo',   
        'ID_Sede',
    ];
}
