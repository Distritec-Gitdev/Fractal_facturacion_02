<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferenciaPersonal1 extends Model
{
    protected $table = 'referencia_personal1'; // Nombre de la tabla en la BD
    protected $primaryKey = 'ID_Referencia1'; // Clave primaria personalizada
    
    protected $fillable = [
        'Primer_Nombre_rf1',
        'Segundo_Nombre_rf1',
        'Primer_Apellido_rf1',
        'Segundo_Apellido_rf1',
        'Celular_rf1',
        'Parentesco_rf1',
        'idtiempo_conocerlonum',
        'idtiempoconocerlo'
    ];


      // Relación inversa con ReferenciasPersonales (hasMany)
      public function referenciasPersonales()
      {
          return $this->hasMany(
              ReferenciasPersonales::class,
              'ID_Referencia1',  // FK en referencias_personales
              'ID_Referencia1'    // PK en referencia_personal1
          );
      }

     // Relación con Z_PARENTESCO_PERSONAL
     public function zparentescoPersonal(): BelongsTo
     {
         return $this->belongsTo(
             ZParentescoPersonal::class,
             'Parentesco_rf1', // Columna en tabla referencia_personal1
             'idparentesco'  // Columna en tabla z_parentesco_personal
         );
     }


     public function zparentescoPersonal2(): BelongsTo
     {
         return $this->belongsTo(
             ZParentescoPersonal::class,
             'Parentesco_rf1', // Columna en tabla referencia_personal1
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
        'ID_Referencia1',
        'id_cliente'
    );
}
 
 

}
