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
        Schema::create('resultados_informe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intento_id')->constrained('intentos')->cascadeOnDelete();
            $table->foreignId('categoria_evaluacion_id')->constrained('categorias_evaluacion')->cascadeOnDelete();
            $table->decimal('puntaje', 8, 2);
            $table->string('etiqueta_interpretacion')->nullable();
            $table->timestamps();

            $table->unique(['intento_id', 'categoria_evaluacion_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resultados_informe');
    }
};
