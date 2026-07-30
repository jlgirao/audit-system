<?php

namespace App\Console\Commands;

use App\Jobs\GenerateEmbeddingJob;
use App\Models\EvidenceFile;
use Illuminate\Console\Command;

class GerarEmbeddingsPendentesCommand extends Command
{
    protected $signature = 'app:gerar-embeddings-pendentes';

    protected $description = 'Enfileira a geração de embedding para evidências já extraídas que ainda não têm (backfill ao instalar a Fase 3)';

    public function handle(): int
    {
        $evidencias = EvidenceFile::where('status_processamento', 'concluido')
            ->whereNotNull('texto_extraido')
            ->whereNull('embedding_vector')
            ->get();

        foreach ($evidencias as $evidencia) {
            GenerateEmbeddingJob::dispatch($evidencia->id);
        }

        $this->info("Embedding enfileirado para {$evidencias->count()} evidência(s).");

        return self::SUCCESS;
    }
}
