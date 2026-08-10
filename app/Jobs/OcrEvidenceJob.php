<?php

namespace App\Jobs;

use App\Models\AuditProcess;
use App\Models\EvidenceFile;
use App\Services\Dropbox\DropboxClient;
use App\Services\Ocr\PdfToImageConverter;
use App\Services\Ocr\TesseractRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class OcrEvidenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Timeout generoso — OCR de PDFs com várias páginas em resolução alta
     * pode legitimamente demorar mais que jobs de extração nativa.
     */
    public int $timeout = 300;

    public function __construct(private readonly int $evidenceFileId)
    {
    }

    public function handle(DropboxClient $client, PdfToImageConverter $conversor, TesseractRunner $tesseract): void
    {
        $evidencia = EvidenceFile::find($this->evidenceFileId);

        if (! $evidencia) {
            return;
        }

        if (! AuditProcess::find($evidencia->process_id)) {
            return;
        }

        $evidencia->update(['status_processamento' => 'processando']);

        $diretorioTemp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ocr_'.uniqid();

        try {
            mkdir($diretorioTemp);
            $conteudo = $client->baixarArquivo($evidencia->caminho_dropbox);

            if (in_array($evidencia->tipo_arquivo, ['png', 'jpeg'], true)) {
                $caminhoImagem = $diretorioTemp.DIRECTORY_SEPARATOR.'imagem.'.$evidencia->tipo_arquivo;
                file_put_contents($caminhoImagem, $conteudo);
                $texto = $tesseract->extrairTexto($caminhoImagem);
            } else {
                // PDF escaneado: converte cada página em imagem, depois
                // roda OCR página a página e concatena o texto.
                $caminhoPdf = $diretorioTemp.DIRECTORY_SEPARATOR.'arquivo.pdf';
                file_put_contents($caminhoPdf, $conteudo);
                $imagens = $conversor->converter($caminhoPdf, $diretorioTemp);

                if (empty($imagens)) {
                    throw new RuntimeException('Nenhuma página foi convertida em imagem a partir do PDF.');
                }

                $texto = collect($imagens)
                    ->map(fn ($imagem) => $tesseract->extrairTexto($imagem))
                    ->implode("\n\n--- página seguinte ---\n\n");
            }

            $texto = trim($texto);

            if ($texto === '') {
                $evidencia->update([
                    'status_processamento' => 'erro',
                    'erro_detalhe' => 'OCR rodou normalmente, mas não encontrou nenhum texto legível no arquivo.',
                ]);

                return;
            }

            $evidencia->update([
                'texto_extraido' => $texto,
                'origem_texto' => 'ocr',
                'status_processamento' => 'concluido',
                'erro_detalhe' => null,
            ]);

            GenerateEmbeddingJob::dispatch($evidencia->id);
        } catch (Throwable $e) {
            $evidencia->update([
                'status_processamento' => 'erro',
                'erro_detalhe' => $e->getMessage(),
            ]);
        } finally {
            $this->limparDiretorioTemp($diretorioTemp);
        }
    }

    private function limparDiretorioTemp(string $diretorio): void
    {
        if (! is_dir($diretorio)) {
            return;
        }

        foreach (glob($diretorio.DIRECTORY_SEPARATOR.'*') ?: [] as $arquivo) {
            @unlink($arquivo);
        }

        @rmdir($diretorio);
    }

    public function failed(?Throwable $exception): void
    {
        $evidencia = EvidenceFile::find($this->evidenceFileId);

        if ($evidencia && $evidencia->status_processamento === 'processando') {
            $evidencia->update([
                'status_processamento' => 'erro',
                'erro_detalhe' => $exception?->getMessage() ?? 'Job de OCR falhou sem mensagem de erro específica.',
            ]);
        }
    }
}
