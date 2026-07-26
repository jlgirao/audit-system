<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_questions', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->text('texto_pergunta');
            $table->string('categoria', 100)->nullable();
            $table->string('aba_excel', 100);
            $table->integer('linha_excel');
            $table->string('coluna_resposta', 5);
            $table->string('coluna_evidencia', 5);
            $table->json('embedding_vector')->nullable();
            $table->boolean('ativo')->default(true);
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_questions');
    }
};
