<?php

namespace App\Http\Controllers;

use App\Jobs\ExtractEvidenceTextJob;
use App\Jobs\OcrEvidenceJob;
use App\Models\AuditProcess;
use App\Models\EvidenceFile;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class EvidenceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['auth'];
    }

    /**
     * Reenvia a evidência para processamento — útil quando o OCR falhou
     * por problema de instalação (Tesseract/Ghostscript) e precisa ser
     * tentado de novo depois de corrigido, sem ter que ressincronizar o
     * processo inteiro.
     */
    public function reprocessar(Request $request, AuditProcess $process, EvidenceFile $evidence)
    {
        abort_unless($process->podeSerEditadoPor($request->user()), 403);
        abort_unless($evidence->process_id === $process->id, 404);

        if (in_array($evidence->tipo_arquivo, ['png', 'jpeg'], true)) {
            OcrEvidenceJob::dispatch($evidence->id);
        } else {
            // PDFs voltam pela extração nativa, que já encadeia OCR
            // sozinha se necessário.
            ExtractEvidenceTextJob::dispatch($evidence->id);
        }

        $evidence->update(['status_processamento' => 'processando', 'erro_detalhe' => null]);

        return redirect()->route('processes.show', $process)->with('status', 'Reprocessamento iniciado.');
    }
}
