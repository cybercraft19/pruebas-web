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
        Schema::table('resultados_informe', function (Blueprint $table) {
            $table->text('recomendacion')->nullable()->after('etiqueta_interpretacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resultados_informe', function (Blueprint $table) {
            $table->dropColumn('recomendacion');
        });
    }
};
