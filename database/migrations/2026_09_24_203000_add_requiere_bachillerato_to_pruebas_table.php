<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pruebas que, según el ejemplo de informe oficial (docs/Ejemplo informe con todas
     * las pruebas de 6 a 11.docx), solo se aplican de 6° a 11°: orientación vocacional,
     * hábitos de estudio y procrastinación no tienen sentido para primaria (3° a 5°).
     *
     * @var array<int, string>
     */
    private const TITULOS_BACHILLERATO = [
        'Test de Orientación Vocacional (CHASIDE)',
        'Cuestionario sobre Hábitos de Estudio y Motivación para el Aprendizaje (HEMA)',
        'Escala de Procrastinación Académica',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pruebas', function (Blueprint $table) {
            $table->boolean('requiere_bachillerato')->default(false)->after('tipo');
        });

        DB::table('pruebas')->whereIn('titulo', self::TITULOS_BACHILLERATO)->update(['requiere_bachillerato' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pruebas', function (Blueprint $table) {
            $table->dropColumn('requiere_bachillerato');
        });
    }
};
