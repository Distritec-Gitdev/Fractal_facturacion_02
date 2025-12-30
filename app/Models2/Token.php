<?php

// app/Models/Token.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $table = 'token';
    protected $primaryKey = 'id_token';
    
    protected $fillable = [
        'id_cliente',
        'token',
        'authentication_token',  
        'confirmacion_token', 
        'hora_confirmacion',
        'envio_mssg',
        'envio_email',
        'envio_wtsapp',
            
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    // app/Models/Token.php
    protected static function booted()
    {
        static::updated(function (Token $token) {
            if ($token->confirmacion_token === 1) {
                event(new \App\Events\TokenConfirmed($token));
            }
        });
    }

   


}