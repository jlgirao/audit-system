<?php

namespace App\Services;

use App\Models\AuditProcess;
use App\Models\AuditQuestion;
use App\Models\OutputFile;
use App\Models\QuestionEvidenceMatch;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class ExcelOutputGenerator
{
    /**
     * Reserva uma nova versão para o processo (cria o registro com status
     * "processando", sem gerar o arquivo ainda). Chamado de forma síncrona
     * no controller, antes de despachar o job — assim o número da versão
     * já fica visível na tela imediatamente, mesmo antes do job rodar.
     */
    public function reservarVersao(AuditProcess $process, int $usuarioId): OutputFile
    {
        $proximaVersao = ($process->arquivosSaida()->max('versao') ?? 0) + 1;

        return $process->arquivosSaida()->create([
            'versao' => $proximaVersao,
            'status' => 'processando',
            'gerado_por' => $usuarioId,
            'gerado_em' => now(),
        ]);
    }

    /**
     * Preenche o template e grava o arquivo, atualizando o OutputFile já
     * reservado. Chamado dentro do job em fila — pode ser pesado
     * (templates grandes, muitos estilos), por isso não roda mais na
     * requisição web.
     */
    public function preencherEGravar(AuditProcess $process, OutputFile $outputFile): void
    {
        $caminhoTemplate = config('auditoria.template_path');

        if (! file_exists($caminhoTemplate)) {
            throw new RuntimeException(
                'Template do Excel de auditoria não encontrado. Peça para o admin fazer o upload em /admin/template.'
            );
        }

        $planilha = IOFactory::load($caminhoTemplate);

        $perguntas = AuditQuestion::where('ativo', true)->orderBy('ordem')->get();
        $respostas = $process->respostas()->get()->keyBy('question_id');

        $abasFaltando = [];
        $colunasFaltando = [];

        foreach ($perguntas as $pergunta) {
            if (! $pergunta->coluna_ha_evidencia || ! $pergunta->coluna_parecer) {
                $colunasFaltando[] = $pergunta->codigo;

                continue;
            }

            if (! $planilha->sheetNameExists($pergunta->aba_excel)) {
                $abasFaltando[$pergunta->aba_excel] = true;

                continue;
            }

            $aba = $planilha->getSheetByName($pergunta->aba_excel);
            $resposta = $respostas->get($pergunta->id);

            $aba->setCellValue($pergunta->coluna_ha_evidencia.$pergunta->linha_excel, $this->textoDaResposta($resposta));
            $aba->setCellValue($pergunta->coluna_observacoes.$pergunta->linha_excel, $resposta?->observacoes ?? '');
            $aba->setCellValue($pergunta->coluna_evidencia.$pergunta->linha_excel, $this->enderecosDasEvidencias($process, $pergunta));
            $aba->setCellValue($pergunta->coluna_parecer.$pergunta->linha_excel, $resposta?->parecer ?? '');
        }

        if (! empty($colunasFaltando)) {
            throw new RuntimeException(
                'As perguntas a seguir não têm as colunas "Resposta" e/ou "Parecer" configuradas: '.
                implode(', ', $colunasFaltando).'. Edite-as em /perguntas antes de gerar o Excel.'
            );
        }

        if (! empty($abasFaltando)) {
            throw new RuntimeException(
                'O template não tem as abas: '.implode(', ', array_keys($abasFaltando)).
                '. Confira se o arquivo enviado em /admin/template é o correto.'
            );
        }

        $nomeArquivo = "processo_{$process->uuid}_v{$outputFile->versao}.xlsx";
        $caminhoRelativo = "outputs/{$process->uuid}/{$nomeArquivo}";
        $caminhoCompleto = Storage::path($caminhoRelativo);

        Storage::makeDirectory("outputs/{$process->uuid}");
        IOFactory::createWriter($planilha, 'Xlsx')->save($caminhoCompleto);

        // Libera a memória da planilha explicitamente — templates pesados
        // (muitos estilos/bordas) consomem bastante RAM, e isso ajuda o
        // worker de fila a não acumular uso de memória entre execuções.
        $planilha->disconnectWorksheets();
        unset($planilha);

        $outputFile->update([
            'caminho_arquivo' => $caminhoRelativo,
            'status' => 'concluido',
            'erro_detalhe' => null,
        ]);
    }

    private function textoDaResposta(?object $resposta): string
    {
        if (! $resposta || ! $resposta->ha_evidencia) {
            return '';
        }

        return match ($resposta->ha_evidencia) {
            'sim' => 'Sim',
            'nao' => 'Não',
            'nao_aplicavel' => 'Não aplicável',
            default => '',
        };
    }

    private function enderecosDasEvidencias(AuditProcess $process, AuditQuestion $pergunta): string
    {
        $confirmadas = QuestionEvidenceMatch::query()
            ->where('process_id', $process->id)
            ->where('question_id', $pergunta->id)
            ->where('status', 'confirmado')
            ->with('evidencia')
            ->get();

        if ($confirmadas->isEmpty()) {
            return '—';
        }

        return $confirmadas
            ->map(fn ($match) => $match->evidencia->nome_arquivo.' ('.$match->evidencia->caminho_dropbox.')')
            ->implode('; ');
    }
}
