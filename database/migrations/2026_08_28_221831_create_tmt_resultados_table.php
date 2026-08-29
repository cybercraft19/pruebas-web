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
        Schema::create('tmt_resultados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intento_id')->constrained('intentos')->cascadeOnDelete();
            $table->enum('parte', ['A', 'B']);
            $table->unsignedInteger('tiempo_segundos');
            $table->unsignedInteger('errores')->default(0);
            $table->boolean('completado')->default(true);
            $table->timestamps();

            $table->unique(['intento_id', 'parte']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tmt_resultados');
    }
};
