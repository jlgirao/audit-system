<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('output_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('audit_processes');
            $table->integer('versao')->default(1);
            $table->string('caminho_arquivo', 600);
            $table->string('caminho_dropbox', 600)->nullable();
            $table->foreignId('gerado_por')->constrained('users');
            $table->timestamp('gerado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('output_files');
    }
};
