<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('audit_processes');
            $table->string('nome_arquivo', 300);
            $table->string('caminho_dropbox', 600);
            $table->string('dropbox_rev', 100);
            $table->enum('tipo_arquivo', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpeg']);
            $table->char('content_hash', 64)->nullable();
            $table->longText('texto_extraido')->nullable();
            $table->json('embedding_vector')->nullable();
            $table->enum('origem_texto', ['nativo', 'ocr'])->nullable();
            $table->enum('classificacao', ['nao_analisado', 'usado', 'confirmado_nao_utilizado'])
                ->default('nao_analisado');
            $table->enum('status_processamento', ['pendente', 'processando', 'concluido', 'erro'])
                ->default('pendente');
            $table->text('erro_detalhe')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_files');
    }
};
