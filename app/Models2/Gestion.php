<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gestion extends Model
{
    protected $table = 'gestion'; // Nombre de la tabla en la BD
    protected $primaryKey = 'id_gestion'; // Clave primaria personalizada
    
    protected $fillable = [
        'id_gestion',
        'id_cliente',
        'ID_Gestor',
        'ID_Estado_cr',
        'comentarios',
        'Estado_cr_created_at',
        'Estado_cr_updated_at',
    ];

    // Relación con GestorDistritec
    public function gestorDistritec(): BelongsTo
    {
        return $this->belongsTo(
            GestorDistritec::class,
            'ID_Gestor', // Columna en tabla gestion
            'ID_Gestor'  // Columna en tabla z_gestores_distritec
        );
    }

    // Relación con EstadoCredito
    public function estadoCredito(): BelongsTo
    {
        return $this->belongsTo(
            EstadoCredito::class,
            'ID_Estado_cr', // Columna en tabla gestion
            'ID_Estado_cr'  // Columna en tabla z_estdo_credito
        );
    }

     protected static function booted(): void
    {
        static::saving(function (self $gestion) {
            if ($gestion->isDirty('ID_Estado_cr')) {
                $now = now();

                $gestion->Estado_cr_updated_at = $now;

                if (is_null($gestion->Estado_cr_created_at)) {
                    $gestion->Estado_cr_created_at = $now;
                }
            }
        });
    }

    
}