<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Chat extends Model
{
    // ⚠️ Ajusta si tu tabla NO se llama "chats"
    protected $table = 'chats';

    protected $fillable = [
        // clave “nueva”
        'cliente_id', 'user_id', 'message', 'sender_type', 'read_at', 'audio_path',
        // claves/columnas legadas (por si se asignan al crear)
        'id_cliente', 'client_id', 'mensaje',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    // Cargar el usuario por defecto, evita N+1 en listados
    protected $with = ['user'];

    /*
    |--------------------------------------------------------------------------
    | Normalización de datos legados (id_cliente, client_id, mensaje)
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void
    {
        // Antes de crear/actualizar, normalizamos las claves legadas
        static::saving(function (self $model) {
            // cliente_id desde id_cliente / client_id si viene vacío
            if (empty($model->cliente_id)) {
                $model->cliente_id = $model->cliente_id
                    ?? $model->getAttribute('id_cliente')
                    ?? $model->getAttribute('client_id');
            }

            // message desde mensaje si viene vacío
            if (empty($model->message)) {
                $legacy = $model->getAttribute('mensaje');
                if (!empty($legacy)) {
                    $model->message = $legacy;
                }
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors / Mutators
    |--------------------------------------------------------------------------
    | message => leer desde "message" o, si no existe, desde "mensaje"
    */
    protected function message(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                if (!is_null($value)) {
                    return $value;
                }
                // fallback a columna vieja
                return $attributes['mensaje'] ?? null;
            },
            set: fn ($value) => ['message' => $value]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */
    public function cliente(): BelongsTo
    {
        // FK en esta tabla: cliente_id (normalizado en booted)
        // PK del cliente: id_cliente
        return $this->belongsTo(Cliente::class, 'cliente_id', 'id_cliente')
            ->withDefault();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes útiles
    |--------------------------------------------------------------------------
    */
    public function scopeForCliente($query, int $clienteId)
    {
        // Permite traer históricos con cualquiera de las columnas legadas
        return $query->where(function ($q) use ($clienteId) {
            $q->where('cliente_id', $clienteId)
              ->orWhere('id_cliente', $clienteId)
              ->orWhere('client_id', $clienteId);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->read_at = now();
            $this->save();
        }
    }
}
