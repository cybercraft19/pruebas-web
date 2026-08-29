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
        Schema::table('pruebas', function (Blueprint $table) {
            $table->enum('tipo', ['cuestionario', 'tmt'])->default('cuestionario')->after('creado_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pruebas', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
