<?php

// app/Models/Cliente.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Events\ClienteUpdated; // 👈 Asegúrate de importar el evento
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne; // Asegúrate de importar esto
use Illuminate\Support\Facades\Log;
use App\Models\ClienteTrabajo;


class Cliente extends Model
{
    protected $table = 'clientes'; // Nombre de la tabla
    protected $primaryKey = 'id_cliente'; // Clave primaria personalizada
    public $incrementing = true; // Si el ID no es autoincremental
    public $timestamps = true; 

    

    protected $fillable = [
        'id_cliente',
        'cedula',
        'fecha_registro',
        'hora',
        'fecha_nac',
        'ID_Cliente_Nombre',
        'ID_Cliente_Contacto',
        'id_departamento',
        'id_municipio',
        'ID_tipo_credito',
        'ID_Identificacion_Cliente',
        'ID_Canal_venta',
        'user_id'
    ];

    protected $casts = [
        'fecha_registro' => 'date',
        //'hora' => 'datetime:H:i:s', // Formato de hora
        'ID_Identificacion_Cliente' => 'integer',
        'ID_Canal_venta' => 'integer',
    ];


    public function gestion()
{
    return $this->hasOne(Gestion::class, 'id_cliente', 'id_cliente');
}

// app/Models/Cliente.php
public function token()
{
    return $this->hasOne(Token::class, 'id_cliente', 'id_cliente');
}
// app/Models/Cliente.php
public function clientesNombreCompleto(): HasOne
{
    return $this->hasOne(ClientesNombreCompleto::class, 'ID_Cliente', 'id_cliente');
}


public function clientesContacto(): HasOne
    {
        return $this->hasOne(ClientesContacto::class, 'id_cliente', 'id_cliente');
    }

public function clienteTrabajo(): HasOne
    {
        return $this->hasOne(clienteTrabajo::class, 'id_cliente', 'id_cliente');
    }


public function dispositivosComprados()
{
    return $this->hasMany(DispositivosComprados::class, 'id_cliente', 'id_cliente');
}

public function dispositivosPago()
{
    return $this->hasMany(DispositivosPago::class, 'id_cliente', 'id_cliente');
}


  // ✅ Pivot referencias_personales (1 por cliente)
    public function referenciasPersonales(): HasOne
    {
        return $this->hasOne(\App\Models\ReferenciasPersonales::class, 'id_cliente', 'id_cliente');
    }

public function referenciasPersonales1()
{
    return $this->hasMany(
        ReferenciaPersonal1::class,
        'ID_Cliente', 
        'id_cliente'
      
    );
}

public function referenciasPersonales2()
{
    return $this->hasMany(
        ReferenciaPersonal2::class,
        'ID_Cliente', 
        'id_cliente'
      
    );
}

public function detallesCliente()
{
    return $this->hasMany(
        DetallesCliente::class,
        'id_cliente', 
        'id_cliente'
      
    );
}

public function departamento()
{
    return $this->belongsTo(ZDepartamentos::class, 'id_departamento', 'id');
}

public function municipio()
{
    return $this->belongsTo(ZMunicipios::class, 'id_municipio', 'id');
}


protected static function booted()
{
    static::updated(function ($cliente) {
        \Log::debug('🔔 Emisión ClienteUpdated para cliente '.$cliente->id_cliente);
event(new ClienteUpdated($cliente));
\Log::debug('✅ Emisión ClienteUpdated completada');

    });
}


public function chats()
{
    return $this->hasMany(Chat::class, 'cliente_id', 'id_cliente');
}


// app/Models/Cliente.php
public function user()
{
    return $this->belongsTo(User::class);
}

public function tipoDocumento()
{
    return $this->belongsTo(TipoDocumento::class, 'ID_Identificacion_Cliente', 'ID_Identificacion_Tributaria');
}

public function tipoCredito()
{
    return $this->belongsTo(TipoCredito::class, 'ID_Tipo_credito', 'ID_Tipo_credito');
}

public function trabajo()
{
    return $this->hasOne(ClienteTrabajo::class, 'id_cliente', 'id_cliente');
}

public function estadoCredito()
{
    return $this->hasOne(EstadoCredito::class, 'ID_Estado_cr', 'ID_Estado_cr');

}


public function canalFD()
{
    return $this->belongsTo(CanalVentafd::class, 'ID_Canal_venta', 'ID_Canal_venta');
}

// En Cliente
public function detalleClienteUltimo()
{
    return $this->hasOne(DetallesCliente::class, 'id_cliente', 'id_cliente')
        ->latestOfMany('ID_Detalles_cliente');
}

// Método helper para obtener el nombre completo del cliente
public function getNombreCompletoAttribute()
{
    if (!$this->clientesNombreCompleto) {
        return null;
    }

    return trim(
        ($this->clientesNombreCompleto->Primer_nombre_cliente ?? '') . ' ' .
        ($this->clientesNombreCompleto->Segundo_nombre_cliente ?? '') . ' ' .
        ($this->clientesNombreCompleto->Primer_apellido_cliente ?? '') . ' ' .
        ($this->clientesNombreCompleto->Segundo_apellido_cliente ?? '')
    );
}

  /**
     * ✅ Helper: sincroniza/crea fila en referencias_personales con las últimas (o únicas) ref1/ref2.
     *    Úsalo desde Create/Edit después de guardar el formulario.
     */
    public function syncPivotReferencias(?int $idRef1 = null, ?int $idRef2 = null): void
    {
        // Si no nos pasaron IDs, intentamos inferirlos (tienes maxItems(1) por Repeater)
        if ($idRef1 === null) {
            $idRef1 = optional(
                $this->referenciasPersonales1()->latest('ID_Referencia1')->first()
            )->ID_Referencia1;
        }

        if ($idRef2 === null) {
            $idRef2 = optional(
                $this->referenciasPersonales2()->latest('ID_Referencia2')->first()
            )->ID_Referencia2;
        }

        if (! $idRef1 && ! $idRef2) {
            return; // nada que enlazar
        }

        \App\Models\ReferenciasPersonales::updateOrCreate(
            ['id_cliente' => $this->id_cliente],
            [
                'ID_Referencia1' => $idRef1,
                'ID_Referencia2' => $idRef2,
            ]
        );
    }




}