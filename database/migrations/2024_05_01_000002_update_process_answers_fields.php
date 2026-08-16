<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_answers', function (Blueprint $table) {
            $table->enum('ha_evidencia', ['sim', 'nao', 'nao_aplicavel'])->nullable()->after('question_id');
            $table->text('observacoes')->nullable()->after('ha_evidencia');
            $table->text('parecer')->nullable()->after('observacoes');
        });

        // Migra os dados que já existiam com o modelo antigo (tipo_resposta/
        // resposta_texto/justificativa) para os campos novos, antes de
        // remover as colunas antigas — evita perder respostas já preenchidas.
        DB::table('process_answers')->orderBy('id')->chunkById(100, function ($respostas) {
            foreach ($respostas as $resposta) {
                $observacoes = $resposta->tipo_resposta === 'sem_evidencia_documental'
                    ? $resposta->justificativa
                    : $resposta->resposta_texto;

                $haEvidencia = match ($resposta->tipo_resposta) {
                    'com_evidencia' => 'sim',
                    'sem_evidencia_documental' => 'nao',
                    default => null,
                };

                DB::table('process_answers')->where('id', $resposta->id)->update([
                    'observacoes' => $observacoes,
                    'ha_evidencia' => $haEvidencia,
                ]);
            }
        });

        Schema::table('process_answers', function (Blueprint $table) {
            $table->dropColumn(['tipo_resposta', 'resposta_texto', 'justificativa']);
        });
    }

    public function down(): void
    {
        Schema::table('process_answers', function (Blueprint $table) {
            $table->enum('tipo_resposta', ['com_evidencia', 'sem_evidencia_documental', 'em_branco'])
                ->default('em_branco');
            $table->text('resposta_texto')->nullable();
            $table->text('justificativa')->nullable();
        });

        Schema::table('process_answers', function (Blueprint $table) {
            $table->dropColumn(['ha_evidencia', 'observacoes', 'parecer']);
        });
    }
};
