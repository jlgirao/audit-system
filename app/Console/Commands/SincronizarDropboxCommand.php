<?php

namespace App\Console\Commands;

use App\Jobs\SyncProcessEvidenceJob;
use App\Models\AuditProcess;
use App\Models\DropboxConfig;
use Illuminate\Console\Command;

class SincronizarDropboxCommand extends Command
{
    protected $signature = 'app:sincronizar-dropbox';

    protected $description = 'Sincroniza evidências de todos os processos ativos com o Dropbox (via cursor incremental)';

    public function handle(): int
    {
        if (! DropboxConfig::atual()->conectado()) {
            $this->warn('Dropbox não está conectado. Nada a sincronizar.');

            return self::SUCCESS;
        }

        $processos = AuditProcess::whereNotIn('status', ['concluido'])->get();

        foreach ($processos as $processo) {
            $processo->update(['status_sincronizacao' => 'na_fila']);
            SyncProcessEvidenceJob::dispatch($processo->id);
        }

        $this->info("Sincronização enfileirada para {$processos->count()} processo(s).");

        return self::SUCCESS;
    }
}
