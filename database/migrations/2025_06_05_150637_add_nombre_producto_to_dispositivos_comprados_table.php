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
        Schema::table('dispositivos_comprados', function (Blueprint $table) {
            $table->string('nombre_producto')->nullable()->after('imei');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispositivos_comprados', function (Blueprint $table) {
            $table->dropColumn('nombre_producto');
        });
    }
};
