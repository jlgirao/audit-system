<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('output_files', function (Blueprint $table) {
            // 'concluido' como default para não quebrar os registros que
            // já existiam antes da geração virar assíncrona.
            $table->enum('status', ['processando', 'concluido', 'erro'])
                ->default('concluido')
                ->after('versao');
            $table->text('erro_detalhe')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('output_files', function (Blueprint $table) {
            $table->dropColumn(['status', 'erro_detalhe']);
        });
    }
};
