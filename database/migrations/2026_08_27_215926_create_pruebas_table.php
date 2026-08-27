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
        Schema::create('pruebas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creado_por')->constrained('users');
            $table->string('titulo');
            $table->text('instrucciones')->nullable();
            $table->unsignedInteger('tiempo_max_minutos')->nullable();
            $table->enum('estado', ['borrador', 'publicada', 'archivada'])->default('borrador');
            $table->string('pdf_referencia_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pruebas');
    }
};
