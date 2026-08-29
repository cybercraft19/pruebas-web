<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('creado_por')->nullable()->after('role')->constrained('users')->nullOnDelete();
        });

        // Los estudiantes existentes (creados antes de que esta columna existiera) quedan
        // asignados al primer evaluador, para no dejar cuentas huérfanas sin dueño.
        $primerEvaluadorId = DB::table('users')->where('role', 'evaluador')->orderBy('id')->value('id');

        if ($primerEvaluadorId) {
            DB::table('users')->where('role', 'estudiante')->update(['creado_por' => $primerEvaluadorId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creado_por');
        });
    }
};
