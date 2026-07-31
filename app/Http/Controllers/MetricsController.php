<?php

namespace App\Http\Controllers;

use App\Models\QuestionEvidenceMatch;
use Illuminate\Routing\Controllers\HasMiddleware;
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
        ]);
    }
}
