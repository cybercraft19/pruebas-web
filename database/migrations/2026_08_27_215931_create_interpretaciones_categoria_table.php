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
        Schema::create('interpretaciones_categoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_evaluacion_id')->constrained('categorias_evaluacion')->cascadeOnDelete();
            $table->decimal('valor_min', 8, 2);
            $table->decimal('valor_max', 8, 2);
            $table->string('etiqueta');
            $table->text('recomendacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interpretaciones_categoria');
    }
};
