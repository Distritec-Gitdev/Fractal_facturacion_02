<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ZParentescoPersonal extends Model
{
    protected $table = 'z_parentesco_personal'; // Nombre de la tabla en la BD
    protected $primaryKey = 'idparentesco'; // Clave primaria personalizada
    
    protected $fillable = [
        'parentesco' 
    ];



     /**
     * Oculta filas cuyo texto sea N/A, NA o NO APLICA (sin tocar los Selects).
     * Si en algún lugar necesitas verlos, usa:
     *   ZParentescoPersonal::withoutGlobalScope('hide_na')->get();
     */
    protected static function booted(): void
    {
        static::addGlobalScope('hide_na', function (Builder $q) {
            $q->whereRaw("UPPER(TRIM(parentesco)) NOT IN ('N/A','NA','NO APLICA')");
        });
    }



      // Relación inversa con ReferenciaPersonal1 (hasMany)
      public function referenciasPersonales1()
      {
          return $this->hasMany(
              ReferenciaPersonal1::class,
              'Parentesco_rf1',  // FK en referencia_personal1
              'idparentesco'     // PK en z_parentesco_personal
          );
      }

}
