<?php

namespace App\Jobs;

use App\Models\AuditProcess;
use App\Models\EvidenceFile;
use App\Services\Dropbox\DropboxClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

class ExtractEvidenceTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private readonly int $evidenceFileId)
    {
    }

    public function handle(DropboxClient $client): void
    {
        $evidencia = EvidenceFile::find($this->evidenceFileId);

        if (! $evidencia) {
            return;
        }

        // Se o processo dono foi excluído nesse meio tempo, não vale a pena
        // continuar — AuditProcess::find() já respeita o soft delete (não
        // encontra processos excluídos), então isso detecta exatamente esse
        // caso sem precisar de nenhuma coluna nova.
        if (! AuditProcess::find($evidencia->process_id)) {
            return;
        }

        $evidencia->update(['status_processamento' => 'processando']);

        $caminhoTemp = null;

        try {
            $conteudo = $client->baixarArquivo($evidencia->caminho_dropbox);
            $evidencia->content_hash = hash('sha256', $conteudo);

            $caminhoTemp = tempnam(sys_get_temp_dir(), 'evidencia_').'.'.$evidencia->tipo_arquivo;
            file_put_contents($caminhoTemp, $conteudo);

            $texto = match ($evidencia->tipo_arquivo) {
                'pdf' => $this->extrairPdf($caminhoTemp),
                'doc', 'docx' => $this->extrairWord($caminhoTemp),
                'xls', 'xlsx' => $this->extrairPlanilha($caminhoTemp),
                default => null,
            };

            $texto = trim((string) $texto);

            if ($texto === '') {
                // Provavelmente um PDF escaneado (sem texto nativo) —
                // encadeia automaticamente para o OCR (Fase 2), sem
                // precisar de ação manual.
                $evidencia->update([
                    'status_processamento' => 'processando',
                    'erro_detalhe' => null,
                ]);

                OcrEvidenceJob::dispatch($evidencia->id);

                return;
            }

            $evidencia->update([
                'texto_extraido' => $texto,
                'origem_texto' => 'nativo',
                'status_processamento' => 'concluido',
                'erro_detalhe' => null,
            ]);

            GenerateEmbeddingJob::dispatch($evidencia->id);
        } catch (Throwable $e) {
            // Para PDFs, uma falha na extração nativa (ex: "Secured PDF
            // file are currently not supported", arquivo corrompido, etc.)
            // não significa necessariamente que o arquivo é ilegível —
            // muitas vezes o Ghostscript+OCR ainda conseguem processá-lo
            // (ele lida com PDFs protegidos por permissão, só não com
            // senha de abertura de verdade). Vale tentar antes de desistir.
            // DOC/XLS/XLSX não têm esse caminho alternativo (o OCR só
            // sabe lidar com PDF/PNG/JPEG), então esses continuam indo
            // direto para "erro".
            if ($evidencia->tipo_arquivo === 'pdf') {
                Log::warning("Extração nativa falhou para a evidência {$evidencia->id}, tentando OCR como alternativa: ".$e->getMessage());

                $evidencia->update([
                    'status_processamento' => 'processando',
                    'erro_detalhe' => null,
                ]);

                OcrEvidenceJob::dispatch($evidencia->id);

                return;
            }

            $evidencia->update([
                'status_processamento' => 'erro',
                'erro_detalhe' => $e->getMessage(),
            ]);
        } finally {
            if ($caminhoTemp && file_exists($caminhoTemp)) {
                unlink($caminhoTemp);
            }
        }
    }

    private function extrairPdf(string $caminho): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($caminho);

        return $pdf->getText();
    }

    private function extrairWord(string $caminho): string
    {
        $documento = WordIOFactory::load($caminho);
        $texto = [];

        foreach ($documento->getSections() as $secao) {
            foreach ($secao->getElements() as $elemento) {
                if (method_exists($elemento, 'getText')) {
                    $texto[] = is_array($elemento->getText()) ? implode(' ', $elemento->getText()) : $elemento->getText();
                }
            }
        }

        return implode("\n", array_filter($texto));
    }

    private function extrairPlanilha(string $caminho): string
    {
        $planilha = SpreadsheetIOFactory::load($caminho);
        $texto = [];

        foreach ($planilha->getAllSheets() as $aba) {
            foreach ($aba->toArray(null, true, false) as $linha) {
                $texto[] = implode(' | ', array_map(fn ($v) => (string) $v, $linha));
            }
        }

        return implode("\n", $texto);
    }

    /**
     * Chamado pelo Laravel quando o job falha definitivamente (esgotou as
     * tentativas). Cobre casos que o try/catch dentro do handle() não
     * capturaria sozinho — garante que a evidência não fique presa em
     * "processando" para sempre por causa de uma falha que o Laravel
     * conseguiu detectar (não cobre travamento físico total do processo,
     * tipo estouro de memória — para isso, veja o comando de "desbloqueio"
     * agendado).
     */
    public function failed(?Throwable $exception): void
    {
        $evidencia = EvidenceFile::find($this->evidenceFileId);

        if ($evidencia && $evidencia->status_processamento === 'processando') {
            $evidencia->update([
                'status_processamento' => 'erro',
                'erro_detalhe' => $exception?->getMessage() ?? 'Job falhou sem mensagem de erro específica.',
            ]);
        }
    }
}
