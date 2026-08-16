<?php

namespace App\Jobs;

use App\Models\AuditProcess;
use App\Models\OutputFile;
use App\Services\ExcelOutputGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateOutputExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Só 1 tentativa: erros aqui costumam ser de conteúdo (aba faltando
     * no template) ou de recurso do servidor, não algo que uma segunda
     * tentativa automática resolveria sozinha.
     */
    public int $tries = 1;

    /**
     * Timeout maior que o padrão — templates pesados podem legitimamente
     * demorar mais que os 60s padrão do worker.
     */
    public int $timeout = 300;

    public function __construct(
        private readonly int $processId,
        private readonly int $outputFileId,
    ) {
    }

    public function handle(ExcelOutputGenerator $gerador): void
    {
        $process = AuditProcess::find($this->processId);
        $outputFile = OutputFile::find($this->outputFileId);

        if (! $process || ! $outputFile) {
            return;
        }

        try {
            $gerador->preencherEGravar($process, $outputFile);
        } catch (Throwable $e) {
            $outputFile->update([
                'status' => 'erro',
                'erro_detalhe' => $e->getMessage(),
            ]);
        }
    }
}
