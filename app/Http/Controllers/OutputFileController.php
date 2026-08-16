<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateOutputExcelJob;
use App\Models\AuditProcess;
use App\Models\OutputFile;
use App\Services\ExcelOutputGenerator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Storage;

class OutputFileController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['auth'];
    }

    /**
     * Reserva a versão e despacha a geração para a fila — não roda mais
     * na própria requisição web, já que templates pesados podem estourar
     * memória/tempo se processados de forma síncrona.
     */
    public function gerar(Request $request, AuditProcess $process, ExcelOutputGenerator $gerador)
    {
        abort_unless($process->podeSerEditadoPor($request->user()), 403);

        $outputFile = $gerador->reservarVersao($process, $request->user()->id);

        GenerateOutputExcelJob::dispatch($process->id, $outputFile->id);

        return redirect()->route('processes.show', $process)
            ->with('status', "Geração da versão v{$outputFile->versao} do Excel iniciada — atualize a página em instantes para acompanhar.");
    }

    public function download(Request $request, AuditProcess $process, OutputFile $outputFile)
    {
        abort_unless($process->podeSerEditadoPor($request->user()), 403);
        abort_unless($outputFile->process_id === $process->id, 404);

        if ($outputFile->status !== 'concluido') {
            return back()->withErrors(['excel' => 'Esta versão ainda não terminou de ser gerada (ou falhou). Veja o status na tabela.']);
        }

        if (! Storage::exists($outputFile->caminho_arquivo)) {
            abort(404, 'Arquivo não encontrado no armazenamento.');
        }

        $nomeParaDownload = "auditoria_{$process->nome}_v{$outputFile->versao}.xlsx";

        return Storage::download($outputFile->caminho_arquivo, $nomeParaDownload);
    }
}
