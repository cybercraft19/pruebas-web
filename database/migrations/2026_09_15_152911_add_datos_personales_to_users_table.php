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
        Schema::table('users', function (Blueprint $table) {
            $table->string('cedula')->nullable()->unique()->after('role');
            $table->string('telefono')->nullable()->after('cedula');
            $table->date('fecha_nacimiento')->nullable()->after('telefono');
            $table->string('acudiente_nombre')->nullable()->after('fecha_nacimiento');
            $table->string('acudiente_telefono')->nullable()->after('acudiente_nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cedula', 'telefono', 'fecha_nacimiento', 'acudiente_nombre', 'acudiente_telefono']);
        });
    }
};
