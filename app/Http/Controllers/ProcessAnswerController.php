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

    public function edit(AuditProcess $process)
    {
        abort_unless($process->podeSerEditadoPor(request()->user()), 403);

        $perguntas = AuditQuestion::where('ativo', true)->orderBy('ordem')->get();
        $respostas = $process->respostas()->get()->keyBy('question_id');
        $evidencias = $process->evidencias()->orderBy('nome_arquivo')->get();

        $matchesConfirmados = QuestionEvidenceMatch::where('process_id', $process->id)
            ->where('status', 'confirmado')
            ->get()
            ->groupBy('question_id');

        return view('processes.respostas', [
            'processo' => $process,
            'perguntas' => $perguntas,
            'respostas' => $respostas,
            'evidencias' => $evidencias,
            'matchesConfirmados' => $matchesConfirmados,
        ]);
    }

    public function update(Request $request, AuditProcess $process)
    {
        abort_unless($process->podeSerEditadoPor($request->user()), 403);

        $dados = $request->validate([
            'respostas' => ['required', 'array'],
            'respostas.*.ha_evidencia' => ['required', 'in:sim,nao,nao_aplicavel'],
            // Observações é obrigatória quando a resposta for "Não" —
            // o required_if compara o campo irmão no MESMO índice do array.
            'respostas.*.observacoes' => ['nullable', 'string', 'required_if:respostas.*.ha_evidencia,nao'],
            'respostas.*.parecer' => ['nullable', 'string'],
            'respostas.*.evidencias' => ['nullable', 'array'],
            'respostas.*.evidencias.*' => ['integer', 'exists:evidence_files,id'],
        ], [
            'respostas.*.observacoes.required_if' => 'Observações é obrigatório quando a resposta é "Não".',
        ]);

        foreach ($dados['respostas'] as $questionId => $item) {
            ProcessAnswer::updateOrCreate(
                ['process_id' => $process->id, 'question_id' => $questionId],
                [
                    'ha_evidencia' => $item['ha_evidencia'],
                    'observacoes' => $item['observacoes'] ?? null,
                    'parecer' => $item['parecer'] ?? null,
                    'preenchido_por' => $request->user()->id,
                    'preenchido_em' => now(),
                ]
            );

            // Ressincroniza os vínculos manuais desta pergunta: mais simples
            // e previsível do que calcular diffs de adição/remoção.
            QuestionEvidenceMatch::where('process_id', $process->id)
                ->where('question_id', $questionId)
                ->where('origem', 'manual')
                ->delete();

            foreach ($item['evidencias'] ?? [] as $evidenciaId) {
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

        return redirect()->route('processes.show', $process)->with('status', 'Respostas salvas com sucesso.');
    }
}
