<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_call_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('audit_processes');
            $table->foreignId('evidence_file_id')->nullable()->constrained('evidence_files');
            $table->foreignId('question_id')->nullable()->constrained('audit_questions');
            $table->enum('tipo_chamada', ['embedding_evidencia', 'embedding_pergunta', 'matching']);
            $table->boolean('sucesso');
            $table->integer('duracao_ms')->nullable();
            $table->text('erro_mensagem')->nullable();
            $table->timestamp('criado_em')->useCurrent();

            $table->index(['process_id', 'tipo_chamada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_call_log');
    }
};
