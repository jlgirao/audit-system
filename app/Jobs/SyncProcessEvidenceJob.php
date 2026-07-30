<?php

namespace App\Jobs;

use App\Models\AuditProcess;
use App\Models\EvidenceFile;
use App\Services\Dropbox\DropboxClient;
use App\Services\Dropbox\DropboxCursorInvalidoException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProcessEvidenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Extensões suportadas e o tipo salvo em evidence_files. PNG/JPEG
     * vão direto para OCR (Fase 2); os demais tentam extração nativa
     * primeiro (mais rápida), com fallback automático para OCR quando
     * o PDF não tiver texto nativo (ex: documento escaneado).
     */
    private const EXTENSOES_SUPORTADAS = [
        'pdf' => 'pdf',
        'doc' => 'doc',
        'docx' => 'docx',
        'xls' => 'xls',
        'xlsx' => 'xlsx',
        'png' => 'png',
        'jpg' => 'jpeg',
        'jpeg' => 'jpeg',
    ];

    public function __construct(private readonly int $processId)
    {
    }

    public function handle(DropboxClient $client): void
    {
        $process = AuditProcess::find($this->processId);

        if (! $process) {
            return;
        }

        try {
            if ($process->dropbox_cursor) {
                $this->processarPagina($process, $client->continuarListagem($process->dropbox_cursor), $client, houveSincronizacaoAnterior: true);
            } else {
                $this->processarPagina($process, $client->listarPasta($process->dropbox_folder_path), $client, houveSincronizacaoAnterior: false);
            }
        } catch (DropboxCursorInvalidoException $e) {
            // Cursor não é mais válido (ex: pasta recriada) — reinicia do zero.
            Log::warning("Cursor inválido para o processo {$process->id}, reiniciando sincronização.", ['erro' => $e->getMessage()]);
            $process->update(['dropbox_cursor' => null]);
            $this->processarPagina($process, $client->listarPasta($process->dropbox_folder_path), $client, houveSincronizacaoAnterior: false);
        }
    }

    private function processarPagina(AuditProcess $process, array $pagina, DropboxClient $client, bool $houveSincronizacaoAnterior): void
    {
        $houveMudanca = $this->aplicarEntradas($process, $pagina['entries'] ?? []);

        // A API do Dropbox pagina resultados grandes — segue puxando
        // enquanto has_more=true, antes de considerar a sincronização completa.
        while ($pagina['has_more'] ?? false) {
            $pagina = $client->continuarListagem($pagina['cursor']);
            $houveMudanca = $this->aplicarEntradas($process, $pagina['entries'] ?? []) || $houveMudanca;
        }

        $process->update([
            'dropbox_cursor' => $pagina['cursor'],
            'tem_arquivos_novos' => $houveSincronizacaoAnterior && $houveMudanca,
        ]);
    }

    private function aplicarEntradas(AuditProcess $process, array $entradas): bool
    {
        $houveMudanca = false;

        foreach ($entradas as $entrada) {
            if (($entrada['.tag'] ?? null) === 'deleted') {
                $removido = EvidenceFile::where('process_id', $process->id)
                    ->where('caminho_dropbox', $entrada['path_lower'])
                    ->first();

                if ($removido) {
                    $removido->delete();
                    $houveMudanca = true;
                }

                continue;
            }

            if (($entrada['.tag'] ?? null) !== 'file') {
                continue; // pastas são ignoradas, só nos interessam arquivos
            }

            $extensao = strtolower(pathinfo($entrada['name'], PATHINFO_EXTENSION));
            $tipo = self::EXTENSOES_SUPORTADAS[$extensao] ?? null;

            if ($tipo === null) {
                Log::info("Arquivo ignorado (extensão não suportada): {$entrada['path_lower']}");

                continue;
            }

            $existente = EvidenceFile::where('process_id', $process->id)
                ->where('caminho_dropbox', $entrada['path_lower'])
                ->first();

            if ($existente && $existente->dropbox_rev === $entrada['rev']) {
                continue; // sem mudança real neste arquivo
            }

            $evidencia = EvidenceFile::updateOrCreate(
                ['process_id' => $process->id, 'caminho_dropbox' => $entrada['path_lower']],
                [
                    'nome_arquivo' => $entrada['name'],
                    'dropbox_rev' => $entrada['rev'],
                    'tipo_arquivo' => $tipo,
                    'status_processamento' => 'pendente',
                    'texto_extraido' => null,
                    'embedding_vector' => null,
                    'erro_detalhe' => null,
                ]
            );

            $houveMudanca = true;

            // PDFs/DOC/XLS entram na extração nativa (que já encadeia OCR
            // sozinha se o PDF não tiver texto nativo). Imagens vão direto
            // para o OCR, já que não têm "texto nativo" para tentar antes.
            if (in_array($tipo, ['png', 'jpeg'], true)) {
                OcrEvidenceJob::dispatch($evidencia->id);
            } else {
                ExtractEvidenceTextJob::dispatch($evidencia->id);
            }
        }

        return $houveMudanca;
    }
}
