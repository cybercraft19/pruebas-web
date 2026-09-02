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
        Schema::table('intentos', function (Blueprint $table) {
            $table->timestamp('firmado_at')->nullable()->after('finalizado_at');
            $table->foreignId('firmado_por')->nullable()->after('firmado_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('intentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('firmado_por');
            $table->dropColumn('firmado_at');
        });
    }
};
