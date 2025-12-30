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
        Schema::table('clientes_contacto', function (Blueprint $table) {
            $table->string('empresa_labor')->nullable()->after('direccion');
            $table->string('tel_empresa')->nullable()->after('empresa_labor');
            $table->boolean('es_independiente')->default(false)->after('tel_empresa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes_contacto', function (Blueprint $table) {
            $table->dropColumn(['empresa_labor', 'tel_empresa', 'es_independiente']);
        });
    }
};
