<?php

namespace App\Http\Controllers;

use App\Models\AuditProcess;
use App\Models\AuditQuestion;
use App\Models\ProcessAnswer;
use App\Models\QuestionEvidenceMatch;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class ProcessAnswerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'permission:preencher-respostas',
        ];
    }

    public function edit(Request $request, AuditProcess $process)
    {
        abort_unless($process->podeSerEditadoPor($request->user()), 403);

        $abasDisponiveis = AuditQuestion::where('ativo', true)
            ->select('aba_excel')
            ->distinct()
            ->orderBy('aba_excel')
            ->pluck('aba_excel');

        $queryPerguntas = AuditQuestion::where('ativo', true);

        if ($aba = $request->input('aba')) {
            $queryPerguntas->where('aba_excel', $aba);
        }

        $filtro = $request->input('filtro');

        if ($filtro === 'com_sugestao') {
            $idsComSugestao = QuestionEvidenceMatch::where('process_id', $process->id)
                ->where('origem', 'ia_sugerido')
                ->pluck('question_id')
                ->unique();

            $queryPerguntas->whereIn('id', $idsComSugestao);
        } elseif ($filtro === 'sem_resposta') {
            $idsComResposta = $process->respostas()->pluck('question_id');

            $queryPerguntas->whereNotIn('id', $idsComResposta);
        }

        $perPage = in_array((int) $request->input('per_page'), [5, 10, 20, 50], true)
            ? (int) $request->input('per_page')
            : 10;

        $perguntas = $queryPerguntas->orderBy('ordem')->paginate($perPage)->withQueryString();

        // Respostas/matches continuam buscados para o processo inteiro (não
        // só a página atual) — a consulta é barata (por process_id, com
        // índice) e evita complicar a lógica de "usar sugestão da IA" para
        // perguntas que não estão na página exibida agora.
        $respostas = $process->respostas()->get()->keyBy('question_id');
        $evidencias = $process->evidencias()->orderBy('nome_arquivo')->get();

        $matchesConfirmados = QuestionEvidenceMatch::where('process_id', $process->id)
            ->where('status', 'confirmado')
            ->get()
            ->groupBy('question_id');

        $matchesSugeridos = QuestionEvidenceMatch::where('process_id', $process->id)
            ->where('status', 'sugerido')
            ->where('origem', 'ia_sugerido')
            ->with('evidencia')
            ->get()
            ->groupBy('question_id');

        return view('processes.respostas', [
            'processo' => $process,
            'perguntas' => $perguntas,
            'abasDisponiveis' => $abasDisponiveis,
            'respostas' => $respostas,
            'evidencias' => $evidencias,
            'matchesConfirmados' => $matchesConfirmados,
            'matchesSugeridos' => $matchesSugeridos,
        ]);
    }

    public function update(Request $request, AuditProcess $process)
    {
        abort_unless($process->podeSerEditadoPor($request->user()), 403);

        $dados = $request->validate([
            'respostas' => ['required', 'array'],
            // Não é mais 'required': o formulário lista TODAS as perguntas
            // de uma vez, e o analista pode preencher só algumas por
            // submissão. Se fosse obrigatório para todas, uma única
            // pergunta ainda não respondida bloquearia o salvamento do
            // restante inteiro (foi exatamente esse o bug relatado).
            'respostas.*.ha_evidencia' => ['nullable', 'in:sim,nao,nao_aplicavel'],
            'respostas.*.observacoes' => ['nullable', 'string'],
            'respostas.*.parecer' => ['nullable', 'string'],
            'respostas.*.evidencias' => ['nullable', 'array'],
            'respostas.*.evidencias.*' => ['integer', 'exists:evidence_files,id'],
        ]);

        $totalRespondidoNestaSubmissao = 0;

        foreach ($dados['respostas'] as $questionId => $item) {
            // Pergunta ainda não respondida nesta submissão — não mexe em
            // nada (nem resposta, nem vínculos de evidência dela).
            if (empty($item['ha_evidencia'])) {
                continue;
            }

            $totalRespondidoNestaSubmissao++;

            // Observações não bloqueia mais o salvamento se ficar em branco
            // com resposta "Não" — em vez disso, preenche automaticamente
            // com "N/A" (a justificativa real continua sendo incentivada
            // na tela, isso só evita travar o fluxo quando o analista não
            // escreveu nada).
            $observacoes = $item['observacoes'] ?? null;

            if ($item['ha_evidencia'] === 'nao' && trim((string) $observacoes) === '') {
                $observacoes = 'N/A';
            }

            ProcessAnswer::updateOrCreate(
                ['process_id' => $process->id, 'question_id' => $questionId],
                [
                    'ha_evidencia' => $item['ha_evidencia'],
                    'observacoes' => $observacoes,
                    'parecer' => $item['parecer'] ?? null,
                    'preenchido_por' => $request->user()->id,
                    'preenchido_em' => now(),
                ]
            );

            // Ressincroniza os vínculos desta pergunta. Diferente da versão
            // anterior (que só apagava matches manuais), agora precisamos
            // tratar também as sugestões da IA: se a IA sugeriu uma
            // evidência e ela NÃO está mais selecionada no formulário,
            // marcamos como "rejeitado" (mantém o histórico) em vez de
            // apagar; vínculos manuais que saíram da seleção são removidos.
            $idsSelecionados = collect($item['evidencias'] ?? [])->map(fn ($id) => (int) $id);

            $matchesExistentes = QuestionEvidenceMatch::where('process_id', $process->id)
                ->where('question_id', $questionId)
                ->get();

            foreach ($matchesExistentes as $match) {
                if ($idsSelecionados->contains($match->evidence_file_id)) {
                    $match->update([
                        'status' => 'confirmado',
                        'revisado_por' => $request->user()->id,
                        'revisado_em' => now(),
                    ]);
                    $idsSelecionados = $idsSelecionados->reject(fn ($id) => $id === $match->evidence_file_id)->values();
                } elseif ($match->origem === 'ia_sugerido') {
                    $match->update([
                        'status' => 'rejeitado',
                        'revisado_por' => $request->user()->id,
                        'revisado_em' => now(),
                    ]);
                } else {
                    $match->delete();
                }
            }

            foreach ($idsSelecionados as $evidenciaId) {
                QuestionEvidenceMatch::create([
                    'process_id' => $process->id,
                    'question_id' => $questionId,
                    'evidence_file_id' => $evidenciaId,
                    'origem' => 'manual',
                    'status' => 'confirmado',
                    'revisado_por' => $request->user()->id,
                    'revisado_em' => now(),
                    'criado_em' => now(),
                ]);
            }
        }

        $idsUsados = QuestionEvidenceMatch::where('process_id', $process->id)
            ->where('status', 'confirmado')
            ->pluck('evidence_file_id')
            ->unique();

        $process->evidencias()->whereIn('id', $idsUsados)->update(['classificacao' => 'usado']);

        // Transição automática: assim que a primeira resposta real é salva,
        // o processo sai de "Criado" para "Em análise" sozinho — sem exigir
        // um clique manual extra. Só dispara se algo foi de fato respondido
        // nesta submissão (evita marcar "em análise" num salvamento vazio),
        // e só parte de "criado" (não mexe em nenhum outro status).
        if ($totalRespondidoNestaSubmissao > 0 && $process->status === 'criado') {
            $process->transicionarStatus('em_analise', $request->user()->id, 'Transição automática: primeira resposta salva.');
        }

        return redirect()->route('processes.respostas.edit', array_merge(
            ['process' => $process->id],
            $request->only(['aba', 'filtro', 'page', 'per_page'])
        ))->with('status', 'Respostas salvas com sucesso.');
    }

    /**
     * Ação em lote: para toda pergunta ativa que ainda NÃO tem resposta
     * salva e NÃO tem nenhuma evidência confirmada, define automaticamente
     * Resposta = "Não" e Observações = "N/A". Não sobrescreve nada que já
     * tenha sido preenchido (manual ou anteriormente por este mesmo botão)
     * nem perguntas com evidência confirmada — é só um atalho para fechar
     * rapidamente as pendências reais, mantendo o registro de quem/quando
     * aplicou (preenchido_por/preenchido_em).
     */
    public function aplicarNaoAsPendentes(Request $request, AuditProcess $process)
    {
        abort_unless($process->podeSerEditadoPor($request->user()), 403);

        $perguntas = AuditQuestion::where('ativo', true)->get();
        $idsComResposta = $process->respostas()->pluck('question_id');

        $idsComEvidenciaConfirmada = QuestionEvidenceMatch::where('process_id', $process->id)
            ->where('status', 'confirmado')
            ->pluck('question_id')
            ->unique();

        $totalAplicado = 0;

        foreach ($perguntas as $pergunta) {
            if ($idsComResposta->contains($pergunta->id) || $idsComEvidenciaConfirmada->contains($pergunta->id)) {
                continue;
            }

            ProcessAnswer::create([
                'process_id' => $process->id,
                'question_id' => $pergunta->id,
                'ha_evidencia' => 'nao',
                'observacoes' => 'N/A',
                'preenchido_por' => $request->user()->id,
                'preenchido_em' => now(),
            ]);

            $totalAplicado++;
        }

        if ($totalAplicado > 0 && $process->status === 'criado') {
            $process->transicionarStatus('em_analise', $request->user()->id, 'Transição automática: primeira resposta salva.');
        }

        return redirect()->route('processes.respostas.edit', array_merge(
            ['process' => $process->id],
            $request->only(['aba', 'filtro', 'page', 'per_page'])
        ))->with('status', "{$totalAplicado} pergunta(s) sem evidência definida(s) como \"Não\" automaticamente. Revise antes de gerar o Excel.");
    }
}
