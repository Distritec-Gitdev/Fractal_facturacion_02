<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferenciaPersonal2 extends Model
{
    protected $table = 'referencia_personal2'; // Nombre de la tabla en la BD
    protected $primaryKey = 'ID_Referencia2'; // Clave primaria personalizada
    
    protected $fillable = [
        'Primer_Nombre_rf2',
        'Segundo_Nombre_rf2',
        'Primer_Apellido_rf2',
        'Segundo_Apellido_rf2',
        'Celular_rf2',
        'Parentesco_rf2',
        'idtiempo_conocerlonum',
        'idtiempoconocerlo'
    ];


      // Relación inversa con ReferenciasPersonales (hasMany)
      public function referenciasPersonales()
      {
          return $this->hasMany(
              ReferenciasPersonales::class,
              'ID_Referencia2',  // FK en referencias_personales
              'ID_Referencia2'    // PK en referencia_personal2
          );
      }

     // Relación con Z_PARENTESCO_PERSONAL
     public function zparentescoPersonal(): BelongsTo
     {
         return $this->belongsTo(
             ZParentescoPersonal::class,
             'Parentesco_rf2', // Columna en tabla referencia_personal2
             'idparentesco'  // Columna en tabla z_parentesco_personal
         );
     }


     public function ztiempoconocerlonum(): BelongsTo
     {
         return $this->belongsTo(
            ZTiempoConocerloNum::class,
             'idtiempo_conocerlonum', 
             'idtiempo_conocerlonum'  
         );
     }

     public function ztiempoconocerlo(): BelongsTo
     {
         return $this->belongsTo(
            ZTiempoConocerlo::class,
             'idtiempoconocerlo', 
             'idtiempoconocerlo'  
         );
     }



     public function clientes()
{
    return $this->belongsToMany(
        Cliente::class,
        'referencias_personales',
        'ID_Referencia2',
        'id_cliente'
    );
}
 
 

}
