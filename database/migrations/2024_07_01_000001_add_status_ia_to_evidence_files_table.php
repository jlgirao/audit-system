<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence_files', function (Blueprint $table) {
            // Status do processamento de IA (embedding + matching),
            // separado do status_processamento (que é da extração/OCR).
            // Marcado pelos próprios jobs no início e no fim, para o
            // indicador "⏳ Processando" da tela refletir o momento real
            // em que embedding/matching estão rodando, não só antes/depois.
            $table->enum('status_ia', ['pendente', 'processando', 'concluido', 'erro'])
                ->default('pendente')
                ->after('status_processamento');
        });
    }

    public function down(): void
    {
        Schema::table('evidence_files', function (Blueprint $table) {
            $table->dropColumn('status_ia');
        });
    }
};
