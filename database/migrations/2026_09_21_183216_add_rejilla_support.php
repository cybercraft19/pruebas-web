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
            $table->string('tipo')->default('cuestionario')->change();
        });

        Schema::create('rejilla_celdas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prueba_id')->constrained('pruebas')->cascadeOnDelete();
            $table->string('variante');
            $table->unsignedTinyInteger('posicion');
            $table->unsignedTinyInteger('numero');
            $table->timestamps();

            $table->unique(['prueba_id', 'variante', 'posicion']);
        });

        Schema::create('rejilla_resultados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intento_id')->constrained('intentos')->cascadeOnDelete();
            $table->string('variante');
            $table->unsignedTinyInteger('aciertos');
            $table->unsignedInteger('errores')->default(0);
            $table->timestamps();

            $table->unique(['intento_id', 'variante']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rejilla_resultados');
        Schema::dropIfExists('rejilla_celdas');

        Schema::table('pruebas', function (Blueprint $table) {
            $table->enum('tipo', ['cuestionario', 'tmt'])->default('cuestionario')->change();
        });
    }
};
