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
        Schema::table('opciones_respuesta', function (Blueprint $table) {
            $table->foreignId('categoria_evaluacion_id')->nullable()->after('pregunta_id')
                ->constrained('categorias_evaluacion')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opciones_respuesta', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categoria_evaluacion_id');
        });
    }
};
