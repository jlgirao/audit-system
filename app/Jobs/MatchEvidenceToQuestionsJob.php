<?php

namespace App\Jobs;

use App\Models\AiCallLog;
use App\Models\AiConfig;
use App\Models\AuditProcess;
use App\Models\AuditQuestion;
use App\Models\EvidenceFile;
use App\Models\QuestionEvidenceMatch;
use App\Services\Ollama\OllamaClient;
use App\Services\VectorMath;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class MatchEvidenceToQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 360;

    private const LIMITE_CARACTERES_PROMPT = 6000;

    public function __construct(private readonly int $evidenceFileId)
    {
    }

    public function handle(OllamaClient $ollama): void
    {
        $evidencia = EvidenceFile::find($this->evidenceFileId);

        if (! $evidencia || ! $evidencia->embedding_vector || ! $evidencia->texto_extraido) {
            return;
        }

        if (! AuditProcess::find($evidencia->process_id)) {
            return;
        }

        // Marca "processando" aqui também (não só no GenerateEmbeddingJob),
        // porque este job pode ser disparado direto quando o embedding já
        // existe (ex: botão "Rodar matching por IA" numa evidência já
        // processada antes).
        $evidencia->update(['status_ia' => 'processando']);

        $limiar = (float) AiConfig::get('limiar_similaridade_minimo', '0.55');
        $maxPorAba = (int) AiConfig::get('max_candidatos_por_aba', '2');
        $maxTotal = (int) AiConfig::get('max_candidatos_por_evidencia', '20');

        $perguntas = AuditQuestion::where('ativo', true)->get();
        $candidatos = [];
        $todasSimilaridades = [];

        foreach ($perguntas as $pergunta) {
            $embeddingPergunta = $pergunta->embedding_vector;

            // Embedding da pergunta é gerado uma vez e cacheado — as
            // próximas evidências reaproveitam sem chamar o Ollama de novo.
            if (empty($embeddingPergunta)) {
                $inicioEmbedding = microtime(true);

                try {
                    $embeddingPergunta = $ollama->gerarEmbedding($pergunta->texto_pergunta, 'query');
                    $pergunta->update(['embedding_vector' => $embeddingPergunta]);

                    AiCallLog::create([
                        'process_id' => $evidencia->process_id,
                        'evidence_file_id' => $evidencia->id,
                        'question_id' => $pergunta->id,
                        'tipo_chamada' => 'embedding_pergunta',
                        'sucesso' => true,
                        'duracao_ms' => (int) ((microtime(true) - $inicioEmbedding) * 1000),
                        'criado_em' => now(),
                    ]);
                } catch (Throwable $e) {
                    AiCallLog::create([
                        'process_id' => $evidencia->process_id,
                        'evidence_file_id' => $evidencia->id,
                        'question_id' => $pergunta->id,
                        'tipo_chamada' => 'embedding_pergunta',
                        'sucesso' => false,
                        'duracao_ms' => (int) ((microtime(true) - $inicioEmbedding) * 1000),
                        'erro_mensagem' => $e->getMessage(),
                        'criado_em' => now(),
                    ]);

                    Log::warning("Falha ao gerar embedding da pergunta {$pergunta->id}: ".$e->getMessage());

                    continue;
                }
            }

            $similaridade = VectorMath::similaridadeCosseno($evidencia->embedding_vector, $embeddingPergunta);
            $todasSimilaridades[] = ['pergunta' => $pergunta, 'similaridade' => $similaridade];

            if ($similaridade >= $limiar) {
                $candidatos[] = ['pergunta' => $pergunta, 'similaridade' => $similaridade];
            }
        }

        usort($todasSimilaridades, fn ($a, $b) => $b['similaridade'] <=> $a['similaridade']);

        if (! empty($todasSimilaridades)) {
            $top5 = array_slice($todasSimilaridades, 0, 5);
            $resumo = collect($top5)
                ->map(fn ($c) => sprintf('%s=%.3f', $c['pergunta']->codigo, $c['similaridade']))
                ->implode(', ');

            Log::info(sprintf(
                'Matching: top %d similaridades para a evidência %d (%s) — %s (limiar configurado: %.2f)',
                count($top5),
                $evidencia->id,
                $evidencia->nome_arquivo,
                $resumo,
                $limiar
            ));
        }

        usort($candidatos, fn ($a, $b) => $b['similaridade'] <=> $a['similaridade']);

        if (empty($candidatos)) {
            $evidencia->update(['status_ia' => 'concluido']);

            return;
        }

        // Seleção por ABA, não mais top-N global: agrupa os candidatos (já
        // filtrados pelo limiar) por "Aba no Excel" da pergunta, pega as
        // $maxPorAba melhores DE CADA aba, e só depois aplica um teto de
        // segurança total ($maxTotal) sobre o conjunto combinado. Isso
        // evita que uma aba com perguntas mais "genéricas" (que tendem a
        // ter similaridade moderada com qualquer documento) monopolize
        // todas as vagas e deixe perguntas de outras abas — mesmo com
        // similaridade real e válida — de fora da avaliação do LLM.
        $candidatosPorAba = collect($candidatos)->groupBy(fn ($c) => $c['pergunta']->aba_excel);

        $candidatosSelecionados = $candidatosPorAba
            ->flatMap(fn ($grupo) => collect($grupo)->sortByDesc('similaridade')->take($maxPorAba)->values())
            ->sortByDesc('similaridade')
            ->take($maxTotal)
            ->values();

        if ($candidatosSelecionados->isEmpty()) {
            $evidencia->update(['status_ia' => 'concluido']);

            return;
        }

        $resumoSelecao = $candidatosSelecionados
            ->map(fn ($c) => sprintf('%s(%s)=%.3f', $c['pergunta']->codigo, $c['pergunta']->aba_excel, $c['similaridade']))
            ->implode(', ');

        Log::info(sprintf(
            'Matching: candidatos selecionados para a evidência %d (%s) — %s (até %d por aba, %d no total)',
            $evidencia->id,
            $evidencia->nome_arquivo,
            $resumoSelecao,
            $maxPorAba,
            $maxTotal
        ));

        foreach ($candidatosSelecionados as $candidato) {
            // Confere de novo a cada chamada (não só uma vez no início) —
            // como cada chamada de LLM pode levar bastante tempo, o processo
            // pode ter sido excluído no meio da execução deste job.
            if (! AuditProcess::find($evidencia->process_id)) {
                return;
            }

            $this->confirmarComLlm($evidencia, $candidato['pergunta'], $candidato['similaridade'], $ollama);
        }

        if (AuditProcess::find($evidencia->process_id)) {
            $evidencia->update(['status_ia' => 'concluido']);
        }
    }

    private function confirmarComLlm(EvidenceFile $evidencia, AuditQuestion $pergunta, float $similaridade, OllamaClient $ollama): void
    {
        // Respeita decisão humana já tomada — não sobrescreve um match
        // que o analista/auditor já confirmou ou rejeitou manualmente.
        $existente = QuestionEvidenceMatch::where('process_id', $evidencia->process_id)
            ->where('question_id', $pergunta->id)
            ->where('evidence_file_id', $evidencia->id)
            ->first();

        if ($existente && in_array($existente->status, ['confirmado', 'rejeitado'], true)) {
            return;
        }

        $prompt = $this->montarPrompt($pergunta, $evidencia);

        $inicio = microtime(true);

        try {
            $dadosBrutos = $ollama->gerarRespostaJson($prompt);

            AiCallLog::create([
                'process_id' => $evidencia->process_id,
                'evidence_file_id' => $evidencia->id,
                'question_id' => $pergunta->id,
                'tipo_chamada' => 'matching',
                'sucesso' => true,
                'duracao_ms' => (int) ((microtime(true) - $inicio) * 1000),
                'criado_em' => now(),
            ]);
        } catch (Throwable $e) {
            AiCallLog::create([
                'process_id' => $evidencia->process_id,
                'evidence_file_id' => $evidencia->id,
                'question_id' => $pergunta->id,
                'tipo_chamada' => 'matching',
                'sucesso' => false,
                'duracao_ms' => (int) ((microtime(true) - $inicio) * 1000),
                'erro_mensagem' => $e->getMessage(),
                'criado_em' => now(),
            ]);

            Log::warning("Falha ao confirmar match (pergunta {$pergunta->id}, evidência {$evidencia->id}): ".$e->getMessage());

            return;
        }

        // O LLM às vezes varia a grafia das chaves do JSON (ex: "confiança"
        // com cedilha, em vez de "confianca" sem acento, mesmo pedindo
        // explicitamente a chave sem acento no prompt). Normalizamos as
        // chaves antes de ler, para não perder dados por causa disso.
        $dados = $this->normalizarChaves($dadosBrutos);

        if (! ($dados['responde_a_pergunta'] ?? false)) {
            Log::info(sprintf(
                'Matching: LLM avaliou que a evidência %d (%s) NÃO responde à pergunta %s. JSON retornado: %s',
                $evidencia->id,
                $evidencia->nome_arquivo,
                $pergunta->codigo,
                json_encode($dadosBrutos)
            ));

            return;
        }

        Log::info(sprintf(
            'Matching: LLM CONFIRMOU que a evidência %d (%s) responde à pergunta %s. JSON retornado: %s',
            $evidencia->id,
            $evidencia->nome_arquivo,
            $pergunta->codigo,
            json_encode($dadosBrutos)
        ));

        QuestionEvidenceMatch::updateOrCreate(
            [
                'process_id' => $evidencia->process_id,
                'question_id' => $pergunta->id,
                'evidence_file_id' => $evidencia->id,
            ],
            [
                'origem' => 'ia_sugerido',
                'status' => 'sugerido',
                'score_confianca' => round(($dados['confianca'] ?? $similaridade * 100), 2),
                'resposta_sugerida' => $dados['resposta_sugerida'] ?? null,
                'parecer_sugerido' => $dados['parecer_sugerido'] ?? null,
                'criado_em' => now(),
            ]
        );
    }

    /**
     * Normaliza as chaves do JSON retornado pelo LLM: remove acentos (ex:
     * "confiança" -> "confianca") e aceita nomes alternativos comuns para
     * cada campo esperado, já que o modelo nem sempre segue exatamente o
     * nome de chave pedido no prompt.
     *
     * @return array<string, mixed>
     */
    private function normalizarChaves(array $dadosBrutos): array
    {
        $semAcento = [];

        foreach ($dadosBrutos as $chave => $valor) {
            $chaveNormalizada = str_replace(
                ['á', 'à', 'ã', 'â', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ç'],
                ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'c'],
                mb_strtolower((string) $chave)
            );
            $semAcento[$chaveNormalizada] = $valor;
        }

        // Aliases: se a chave "oficial" não existir, tenta variações comuns
        // que o modelo às vezes usa no lugar.
        $aliases = [
            'responde_a_pergunta' => ['responde', 'respondeu', 'e_relevante', 'relevante'],
            'confianca' => ['confidence', 'score', 'pontuacao'],
            'resposta_sugerida' => ['resposta', 'sugestao_resposta', 'answer'],
            'parecer_sugerido' => ['parecer', 'justificativa', 'explicacao', 'motivo', 'comentario'],
        ];

        foreach ($aliases as $chaveOficial => $variacoes) {
            if (array_key_exists($chaveOficial, $semAcento)) {
                continue;
            }

            foreach ($variacoes as $variacao) {
                if (array_key_exists($variacao, $semAcento)) {
                    $semAcento[$chaveOficial] = $semAcento[$variacao];

                    break;
                }
            }
        }

        return $semAcento;
    }

    private function montarPrompt(AuditQuestion $pergunta, EvidenceFile $evidencia): string
    {
        $textoEvidencia = mb_substr($evidencia->texto_extraido, 0, self::LIMITE_CARACTERES_PROMPT);

        $promptBase = AiConfig::get('prompt_base_matching', $this->promptPadrao());

        $blocoContexto = trim((string) $pergunta->contexto_adicional) !== ''
            ? "Contexto adicional para interpretar esta pergunta (informado pelo auditor): {$pergunta->contexto_adicional}\n"
            : '';

        return str_replace(
            ['{pergunta}', '{evidencia}', '{contexto}'],
            [$pergunta->texto_pergunta, $textoEvidencia, $blocoContexto],
            $promptBase
        );
    }

    private function promptPadrao(): string
    {
        return <<<'PROMPT'
Você é um assistente de auditoria. Analise se o texto de evidência abaixo
responde à pergunta de auditoria informada.

Pergunta de auditoria: {pergunta}
{contexto}
Texto da evidência:
"""
{evidencia}
"""

Responda APENAS em JSON válido, neste formato exato:
{
  "responde_a_pergunta": true ou false,
  "confianca": número de 0 a 100,
  "resposta_sugerida": "sim", "nao" ou "nao_aplicavel",
  "parecer_sugerido": "um parecer objetivo de 1 a 3 frases, em português, explicando a conclusão"
}

Se a evidência claramente não tem relação com a pergunta, responda
"responde_a_pergunta": false.
PROMPT;
    }

    public function failed(?Throwable $exception): void
    {
        $evidencia = EvidenceFile::find($this->evidenceFileId);

        if ($evidencia && $evidencia->status_ia === 'processando') {
            $evidencia->update(['status_ia' => 'erro']);
        }
    }
}
