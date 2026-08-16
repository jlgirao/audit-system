<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_evidence_match', function (Blueprint $table) {
            $table->string('resposta_sugerida', 20)->nullable()->after('score_confianca');
            $table->text('parecer_sugerido')->nullable()->after('resposta_sugerida');
        });
    }

    public function down(): void
    {
        Schema::table('question_evidence_match', function (Blueprint $table) {
            $table->dropColumn(['resposta_sugerida', 'parecer_sugerido']);
        });
    }
};
