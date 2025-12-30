<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    protected $connection = 'productos2';
    protected $table      = 'estados';
    protected $primaryKey = 'id';
    public $timestamps    = false;

    protected $fillable = [
        'id',
        'tipo',
    ];

  
}
