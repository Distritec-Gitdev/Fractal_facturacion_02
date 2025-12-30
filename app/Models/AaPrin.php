<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AaPrin extends Model
{
    protected $connection = 'inf_asesores';
    protected $table = 'aa_prin';
    protected $primaryKey = 'ID_Inf_trab';

    protected $fillable = [
        'ID_Inf_trab',
        'ID_Asesor',
        'ID_Sede',
        'ID_Estado',
       
    ];
    

    // Relación con Asesor (belongsTo)
    public function asesor()
    {
        return $this->belongsTo(Asesor::class, 'ID_Asesor', 'ID_Asesor');
    }

    // Relación con Sede (belongsTo)
    public function sede()
    {
        return $this->belongsTo(Sede::class, 'ID_Sede', 'ID_Sede');
    }
}