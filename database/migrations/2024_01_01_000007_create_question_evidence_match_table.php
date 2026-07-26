<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_evidence_match', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('audit_processes');
            $table->foreignId('question_id')->constrained('audit_questions');
            $table->foreignId('evidence_file_id')->constrained('evidence_files');
            $table->enum('origem', ['ia_sugerido', 'manual'])->default('ia_sugerido');
            $table->decimal('score_confianca', 5, 2)->nullable();
            $table->enum('status', ['sugerido', 'confirmado', 'rejeitado'])->default('sugerido');
            $table->foreignId('revisado_por')->nullable()->constrained('users');
            $table->timestamp('revisado_em')->nullable();
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_evidence_match');
    }
};
