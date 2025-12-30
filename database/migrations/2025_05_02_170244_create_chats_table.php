<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       // database/migrations/[...]_create_chats_table.php
Schema::create('chats', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cliente_id')->constrained('clientes', 'id_cliente');
    $table->foreignId('user_id')->constrained();
    $table->text('message');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
