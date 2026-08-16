<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename via SQL bruto para não depender do pacote doctrine/dbal.
        DB::statement('ALTER TABLE audit_questions CHANGE coluna_resposta coluna_observacoes VARCHAR(5) NOT NULL');

        Schema::table('audit_questions', function (Blueprint $table) {
            $table->string('coluna_ha_evidencia', 5)->nullable()->after('aba_excel');
            $table->string('coluna_parecer', 5)->nullable()->after('coluna_evidencia');
        });
    }

    public function down(): void
    {
        Schema::table('audit_questions', function (Blueprint $table) {
            $table->dropColumn(['coluna_ha_evidencia', 'coluna_parecer']);
        });

        DB::statement('ALTER TABLE audit_questions CHANGE coluna_observacoes coluna_resposta VARCHAR(5) NOT NULL');
    }
};
