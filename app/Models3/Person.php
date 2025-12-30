<?php

namespace App\Models;

use App\Events\PersonUpdated;
use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $fillable = ['nombre', 'cedula', 'estado'];

    protected static function booted()
{
    static::created(function ($person) {
        broadcast(new PersonUpdated($person))->toOthers(); // 👈 Usa broadcast() en lugar de event()
    });

    static::updated(function ($person) {
        broadcast(new PersonUpdated($person))->toOthers();
    });
}
}
