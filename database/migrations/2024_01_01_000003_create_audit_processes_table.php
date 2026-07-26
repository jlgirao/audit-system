<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_processes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nome', 200);
            $table->enum('status', [
                'criado', 'em_analise', 'em_revisao', 'devolvido',
                'aprovado', 'concluido', 'reaberto',
            ])->default('criado');
            $table->string('dropbox_folder_path', 500);
            $table->text('dropbox_cursor')->nullable();
            $table->boolean('tem_arquivos_novos')->default(false);
            $table->foreignId('criado_por')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_processes');
    }
};
