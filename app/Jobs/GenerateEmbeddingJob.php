<?php

namespace App\Jobs;

use App\Models\AiConfig;
use App\Models\EvidenceFile;
use App\Services\Ollama\OllamaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(private readonly int $evidenceFileId)
    {
    }

    public function handle(OllamaClient $ollama): void
    {
        $evidencia = EvidenceFile::find($this->evidenceFileId);

        if (! $evidencia || ! $evidencia->texto_extraido) {
            return;
        }

        $evidencia->update(['status_ia' => 'processando']);

        try {
            // Modelos de embedding costumam ter um limite de contexto BEM
            // menor que os modelos de linguagem (ex: nomic-embed-text costuma
            // aceitar ~2048 tokens por padrão no Ollama). Textos maiores que
            // isso são rejeitados com erro "input length exceeds the context
            // length" — por isso o limite é bem mais conservador aqui do que
            // no prompt do LLM de matching, e configurável para ajustar caso
            // o modelo usado tenha um contexto diferente.
            $limiteCaracteres = (int) AiConfig::get('limite_caracteres_embedding', '2000');
            $texto = mb_substr($evidencia->texto_extraido, 0, $limiteCaracteres);
            $vetor = $ollama->gerarEmbedding($texto);

            $evidencia->update(['embedding_vector' => $vetor]);

            // status_ia continua "processando" — só vira "concluido" no
            // final do MatchEvidenceToQuestionsJob, que é disparado a seguir.
            MatchEvidenceToQuestionsJob::dispatch($evidencia->id);
        } catch (Throwable $e) {
            // Não altera o status_processamento da evidência — a extração
            // de texto já foi concluída com sucesso, isso é só uma etapa
            // adicional (IA) que pode ser tentada de novo depois que a
            // configuração do Ollama for corrigida.
            $evidencia->update(['status_ia' => 'erro']);
            Log::warning("Falha ao gerar embedding da evidência {$evidencia->id}: ".$e->getMessage());
        }
    }
}
