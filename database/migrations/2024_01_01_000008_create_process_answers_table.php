<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('audit_processes');
            $table->foreignId('question_id')->constrained('audit_questions');
            $table->text('resposta_texto')->nullable();
            $table->enum('tipo_resposta', ['com_evidencia', 'sem_evidencia_documental', 'em_branco'])
                ->default('em_branco');
            $table->text('justificativa')->nullable();
            $table->foreignId('preenchido_por')->nullable()->constrained('users');
            $table->timestamp('preenchido_em')->nullable();
            $table->timestamps();

            $table->unique(['process_id', 'question_id'], 'uq_process_question');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_answers');
    }
};
