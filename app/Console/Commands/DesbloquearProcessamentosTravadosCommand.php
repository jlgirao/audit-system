<?php

namespace App\Console\Commands;

use App\Models\AuditProcess;
use App\Models\EvidenceFile;
use App\Models\OutputFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DesbloquearProcessamentosTravadosCommand extends Command
{
    protected $signature = 'app:desbloquear-travados {--minutos=20 : Tempo parado, em minutos, para considerar travado}';

    protected $description = 'Rede de segurança: marca como "erro" qualquer evidência/processo travado em "processando" há tempo demais (ex: worker morto por estouro de memória, sem chance de atualizar o status sozinho)';

    public function handle(): int
    {
        $minutos = (int) $this->option('minutos');
        $limite = now()->subMinutes($minutos);
        $total = 0;

        $evidenciasExtracao = EvidenceFile::where('status_processamento', 'processando')
            ->where('updated_at', '<', $limite)
            ->get();

        foreach ($evidenciasExtracao as $evidencia) {
            $evidencia->update([
                'status_processamento' => 'erro',
                'erro_detalhe' => "Processamento travado há mais de {$minutos} minutos sem concluir — provavelmente o worker foi encerrado no meio da execução (ex: estouro de memória). Use o botão Reprocessar.",
            ]);
            $total++;
        }

        $evidenciasIa = EvidenceFile::where('status_ia', 'processando')
            ->where('updated_at', '<', $limite)
            ->get();

        foreach ($evidenciasIa as $evidencia) {
            $evidencia->update(['status_ia' => 'erro']);
            $total++;
        }

        $processosSincronizacao = AuditProcess::whereIn('status_sincronizacao', ['na_fila', 'sincronizando'])
            ->where('updated_at', '<', $limite)
            ->get();

        foreach ($processosSincronizacao as $processo) {
            $processo->update(['status_sincronizacao' => 'erro']);
            $total++;
        }

        $arquivosSaida = OutputFile::where('status', 'processando')
            ->get();

        // output_files não tem updated_at (timestamps desativado no model) —
        // usa gerado_em como referência de quando começou.
        foreach ($arquivosSaida as $arquivo) {
            if ($arquivo->gerado_em && now()->diffInMinutes($arquivo->gerado_em) >= $minutos) {
                $arquivo->update(['status' => 'erro', 'erro_detalhe' => "Geração travada há mais de {$minutos} minutos."]);
                $total++;
            }
        }

        if ($total > 0) {
            Log::warning("Desbloqueio automático: {$total} item(ns) travado(s) em 'processando' foram marcados como erro.");
        }

        $this->info("{$total} item(ns) desbloqueado(s).");

        return self::SUCCESS;
    }
}
