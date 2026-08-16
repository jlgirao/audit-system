<?php

namespace App\Http\Controllers;

use App\Models\AuditProcess;
use App\Models\QuestionEvidenceMatch;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'permission:ver-metricas-ia',
        ];
    }

    public function index()
    {
        $geral = QuestionEvidenceMatch::where('origem', 'ia_sugerido')
            ->selectRaw("
                count(*) as total,
                sum(status = 'confirmado') as confirmadas,
                sum(status = 'rejeitado') as rejeitadas,
                sum(status = 'sugerido') as pendentes,
                avg(case when status = 'confirmado' then score_confianca end) as confianca_media_confirmadas,
                avg(case when status = 'rejeitado' then score_confianca end) as confianca_media_rejeitadas
            ")
            ->first();

        $totalJulgadas = ($geral->confirmadas ?? 0) + ($geral->rejeitadas ?? 0);
        $taxaAcertoGeral = $totalJulgadas > 0
            ? round($geral->confirmadas / $totalJulgadas * 100, 1)
            : null;

        $porPergunta = DB::table('question_evidence_match')
            ->join('audit_questions', 'audit_questions.id', '=', 'question_evidence_match.question_id')
            ->where('question_evidence_match.origem', 'ia_sugerido')
            ->selectRaw("
                audit_questions.id as question_id,
                audit_questions.codigo,
                audit_questions.texto_pergunta,
                count(*) as total,
                sum(question_evidence_match.status = 'confirmado') as confirmadas,
                sum(question_evidence_match.status = 'rejeitado') as rejeitadas,
                sum(question_evidence_match.status = 'sugerido') as pendentes
            ")
            ->groupBy('audit_questions.id', 'audit_questions.codigo', 'audit_questions.texto_pergunta')
            ->orderByDesc('total')
            ->get()
            ->map(function ($linha) {
                $julgadas = $linha->confirmadas + $linha->rejeitadas;
                $linha->taxa_acerto = $julgadas > 0 ? round($linha->confirmadas / $julgadas * 100, 1) : null;

                return $linha;
            });

        $porProcesso = DB::table('question_evidence_match')
            ->join('audit_processes', 'audit_processes.id', '=', 'question_evidence_match.process_id')
            ->where('question_evidence_match.origem', 'ia_sugerido')
            ->selectRaw("
                audit_processes.id as process_id,
                audit_processes.nome,
                count(*) as total,
                sum(question_evidence_match.status = 'confirmado') as confirmadas,
                sum(question_evidence_match.status = 'rejeitado') as rejeitadas,
                sum(question_evidence_match.status = 'sugerido') as pendentes
            ")
            ->groupBy('audit_processes.id', 'audit_processes.nome')
            ->orderByDesc('total')
            ->get()
            ->map(function ($linha) {
                $julgadas = $linha->confirmadas + $linha->rejeitadas;
                $linha->taxa_acerto = $julgadas > 0 ? round($linha->confirmadas / $julgadas * 100, 1) : null;

                return $linha;
            });

        return view('metricas.index', [
            'geral' => $geral,
            'taxaAcertoGeral' => $taxaAcertoGeral,
            'porPergunta' => $porPergunta,
            'porProcesso' => $porProcesso,
            'acompanhamento' => $this->acompanhamentoPorProcesso(),
        ]);
    }

    /**
     * Painel operacional por processo: lead time (da criação até o status
     * "concluido", ou até agora se ainda não concluiu), quantidade de
     * evidências, total de chamadas de IA feitas, quantas falharam, e a
     * duração das chamadas de IA (da primeira à última chamada registrada
     * em ai_call_log para aquele processo — mede só o tempo de IA em si,
     * diferente do lead time, que inclui todo o fluxo humano).
     */
    private function acompanhamentoPorProcesso()
    {
        return AuditProcess::query()
            ->withCount('evidencias')
            ->withCount(['chamadasIa as chamadas_ia_total'])
            ->withCount(['chamadasIa as chamadas_ia_falhas' => fn ($q) => $q->where('sucesso', false)])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AuditProcess $processo) {
                $primeiraTransicao = $processo->historicoStatus()->orderBy('criado_em')->value('criado_em');
                $transicaoConclusao = $processo->historicoStatus()
                    ->where('status_novo', 'concluido')
                    ->orderBy('criado_em')
                    ->value('criado_em');

                $inicio = $primeiraTransicao ?? $processo->created_at;
                $fim = $transicaoConclusao ?? now();

                $processo->lead_time_horas = $inicio ? round($inicio->diffInMinutes($fim) / 60, 1) : null;
                $processo->esta_concluido = (bool) $transicaoConclusao;

                // NOVO: duração das chamadas de IA (primeira à última).
                $primeiraChamada = $processo->chamadasIa()->min('criado_em');
                $ultimaChamada = $processo->chamadasIa()->max('criado_em');

                if ($primeiraChamada && $ultimaChamada) {
                    $minutos = Carbon::parse($primeiraChamada)->diffInMinutes(Carbon::parse($ultimaChamada));
                    $processo->duracao_ia_texto = $minutos < 60
                        ? round($minutos).' min'
                        : round($minutos / 60, 1).' h';
                } else {
                    $processo->duracao_ia_texto = null;
                }

                $processo->ia_ainda_processando = $processo->evidencias()
                    ->where('status_ia', 'processando')
                    ->exists();

                return $processo;
            });
    }
}
