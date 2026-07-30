@extends('layouts.app')

@section('titulo', 'Configuração da IA')

@section('conteudo')
    <h2>Configuração da IA (Ollama)</h2>
    <p style="color:#666; font-size:13px;">
        Ajustes aqui valem para o matching automático de todos os processos.
        Depois de salvar, use o botão "Rodar matching por IA" na tela de um processo
        para reprocessar com a nova configuração.
    </p>

    <form method="POST" action="{{ route('admin.ia.update') }}">
        @csrf
        @method('PUT')

        <label>Endpoint do Ollama</label>
        <input type="text" name="ollama_endpoint" value="{{ old('ollama_endpoint', $valores['ollama_endpoint']) }}" required>

        <label>Modelo de embedding</label>
        <input type="text" name="modelo_embedding" value="{{ old('modelo_embedding', $valores['modelo_embedding']) }}" required>

        <label>Modelo de matching (LLM)</label>
        <input type="text" name="modelo_matching" value="{{ old('modelo_matching', $valores['modelo_matching']) }}" required>

        <label>Limiar mínimo de similaridade (0 a 1)</label>
        <input type="number" step="0.01" min="0" max="1" name="limiar_similaridade_minimo"
            value="{{ old('limiar_similaridade_minimo', $valores['limiar_similaridade_minimo']) }}" required>
        <p style="font-size:12px; color:#666; margin-top:2px;">
            Só pares pergunta-evidência com similaridade igual ou acima disso chegam a ser
            confirmados pelo LLM. Muito baixo = mais candidatos, porém mais chamadas de IA
            (mais lento). Muito alto = pode deixar de sugerir evidências relevantes.
        </p>

        <label>Máximo de perguntas candidatas por evidência</label>
        <input type="number" min="1" max="10" name="max_candidatos_por_evidencia"
            value="{{ old('max_candidatos_por_evidencia', $valores['max_candidatos_por_evidencia']) }}" required>

        <label>Limite de caracteres enviados para gerar o embedding</label>
        <input type="number" min="200" max="20000" name="limite_caracteres_embedding"
            value="{{ old('limite_caracteres_embedding', $valores['limite_caracteres_embedding']) }}" required>
        <p style="font-size:12px; color:#666; margin-top:2px;">
            Modelos de embedding costumam ter um limite de contexto BEM menor que os modelos
            de linguagem (o <code>nomic-embed-text</code>, por exemplo, costuma aceitar só
            ~2048 tokens no Ollama por padrão). Se aparecer o erro <code>"input length exceeds
            the context length"</code> no log, <strong>diminua</strong> este valor. Isso só
            afeta a comparação por similaridade (pré-seleção) — o texto completo da evidência
            continua sendo usado no prompt do LLM de confirmação, dentro do limite dele.
        </p>

        <label>Prompt base do matching</label>
        <textarea name="prompt_base_matching" rows="16" style="font-family:monospace; font-size:13px;" required>{{ old('prompt_base_matching', $valores['prompt_base_matching']) }}</textarea>
        <p style="font-size:12px; color:#666; margin-top:2px;">
            Use <code>{pergunta}</code> e <code>{evidencia}</code> como marcadores — serão
            substituídos pelo texto real na hora do matching. O modelo deve responder em
            JSON com as chaves <code>responde_a_pergunta</code>, <code>confianca</code>,
            <code>resposta_sugerida</code> e <code>parecer_sugerido</code>.
        </p>

        <button type="submit">Salvar configuração</button>
    </form>
@endsection
