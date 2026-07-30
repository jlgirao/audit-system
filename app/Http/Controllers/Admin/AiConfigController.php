<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiConfig;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class AiConfigController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'permission:configurar-ia',
        ];
    }

    private const CAMPOS_COM_PADRAO = [
        'ollama_endpoint' => 'http://localhost:11434',
        'modelo_embedding' => 'nomic-embed-text',
        'modelo_matching' => 'qwen2.5:14b',
        'limiar_similaridade_minimo' => '0.55',
        'max_candidatos_por_evidencia' => '3',
        'limite_caracteres_embedding' => '2000',
    ];

    public function index()
    {
        $valores = [];

        foreach (self::CAMPOS_COM_PADRAO as $chave => $padrao) {
            $valores[$chave] = AiConfig::get($chave, $padrao);
        }

        $valores['prompt_base_matching'] = AiConfig::get('prompt_base_matching', $this->promptPadrao());

        return view('admin.ia.index', ['valores' => $valores]);
    }

    public function update(Request $request)
    {
        $dados = $request->validate([
            'ollama_endpoint' => ['required', 'url'],
            'modelo_embedding' => ['required', 'string', 'max:100'],
            'modelo_matching' => ['required', 'string', 'max:100'],
            'limiar_similaridade_minimo' => ['required', 'numeric', 'min:0', 'max:1'],
            'max_candidatos_por_evidencia' => ['required', 'integer', 'min:1', 'max:10'],
            'limite_caracteres_embedding' => ['required', 'integer', 'min:200', 'max:20000'],
            'prompt_base_matching' => ['required', 'string'],
        ]);

        foreach ($dados as $chave => $valor) {
            AiConfig::set($chave, (string) $valor);
        }

        return redirect()->route('admin.ia.index')->with('status', 'Configuração da IA atualizada com sucesso.');
    }

    private function promptPadrao(): string
    {
        return <<<'PROMPT'
Você é um assistente de auditoria. Analise se o texto de evidência abaixo
responde à pergunta de auditoria informada.

Pergunta de auditoria: {pergunta}

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
}
