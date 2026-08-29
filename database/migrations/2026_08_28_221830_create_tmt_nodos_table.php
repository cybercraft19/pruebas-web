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
        Schema::create('tmt_nodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prueba_id')->constrained('pruebas')->cascadeOnDelete();
            $table->enum('parte', ['A', 'B']);
            $table->boolean('practica')->default(false);
            $table->unsignedInteger('orden');
            $table->string('etiqueta');
            $table->decimal('pos_x', 5, 2);
            $table->decimal('pos_y', 5, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tmt_nodos');
    }
};
