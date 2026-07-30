<?php

namespace App\Services\Ollama;

use App\Models\AiConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaClient
{
    private function endpoint(): string
    {
        return rtrim(AiConfig::get('ollama_endpoint', 'http://localhost:11434'), '/');
    }

    /**
     * Gera o vetor de embedding de um texto, usado tanto para as
     * perguntas (cacheado uma vez) quanto para o texto das evidências.
     *
     * @param string $tipo 'query' (textos curtos como perguntas) ou
     *                     'document' (textos longos como evidências).
     *                     Modelos da família nomic-embed-text exigem esses
     *                     prefixos de instrução para gerar embeddings
     *                     comparáveis entre si — sem isso, a similaridade
     *                     fica artificialmente baixa mesmo entre textos
     *                     claramente relacionados.
     * @return array<int, float>
     */
    public function gerarEmbedding(string $texto, string $tipo = 'document'): array
    {
        $modelo = AiConfig::get('modelo_embedding', 'nomic-embed-text');
        $textoComPrefixo = $this->aplicarPrefixoDeInstrucao($texto, $tipo, $modelo);

        $resposta = Http::timeout(90)->post($this->endpoint().'/api/embeddings', [
            'model' => $modelo,
            'prompt' => $textoComPrefixo,
        ]);

        if ($resposta->failed()) {
            throw new RuntimeException(
                'Falha ao gerar embedding no Ollama (modelo "'.$modelo.'"). '.
                'Confirme que o Ollama está rodando e que o modelo foi baixado (`ollama pull '.$modelo.'`). '.
                'Detalhe: '.$resposta->body()
            );
        }

        $embedding = $resposta->json('embedding');

        if (! is_array($embedding) || empty($embedding)) {
            throw new RuntimeException('O Ollama respondeu sem um embedding válido.');
        }

        return $embedding;
    }

    private function aplicarPrefixoDeInstrucao(string $texto, string $tipo, string $modelo): string
    {
        // Prefixo é uma convenção específica da família nomic-embed-text.
        // Se o admin trocar para outro modelo de embedding sem essa
        // convenção, o texto é enviado puro, sem prefixo.
        if (! str_contains(strtolower($modelo), 'nomic')) {
            return $texto;
        }

        return ($tipo === 'query' ? 'search_query: ' : 'search_document: ').$texto;
    }

    /**
     * Chama o LLM de confirmação/matching, forçando saída em JSON
     * (suportado nativamente pelo Ollama via "format": "json").
     */
    public function gerarRespostaJson(string $prompt): array
    {
        $modelo = AiConfig::get('modelo_matching', 'qwen2.5:14b');

        $resposta = Http::timeout(300)->post($this->endpoint().'/api/generate', [
            'model' => $modelo,
            'prompt' => $prompt,
            'stream' => false,
            'format' => 'json',
        ]);

        if ($resposta->failed()) {
            throw new RuntimeException(
                'Falha ao chamar o modelo de matching no Ollama (modelo "'.$modelo.'"). '.
                'Confirme que o Ollama está rodando e que o modelo foi baixado (`ollama pull '.$modelo.'`). '.
                'Detalhe: '.$resposta->body()
            );
        }

        $texto = $resposta->json('response', '');
        $dados = json_decode($texto, true);

        if (! is_array($dados)) {
            throw new RuntimeException('O modelo de matching não retornou um JSON válido: '.$texto);
        }

        return $dados;
    }
}
