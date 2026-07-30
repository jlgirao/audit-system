@extends('layouts.app')

@section('titulo', 'Responder perguntas')

@section('conteudo')
    <h2>Responder perguntas — {{ $processo->nome }}</h2>
    <p style="color:#666; font-size:13px;">
        Preenchimento manual (sem IA nesta fase). Responda com base nas evidências vinculadas.
        "Observações" é obrigatório quando a resposta for "Não".
    </p>

    <form method="POST" action="{{ route('processes.respostas.update', $processo) }}">
        @csrf
        @method('PUT')

        @foreach ($perguntas as $pergunta)
            @php
                $resposta = $respostas->get($pergunta->id);
                $matchesAtuais = $matchesConfirmados->get($pergunta->id, collect())->pluck('evidence_file_id');
            @endphp
            <div style="border:1px solid #ddd; border-radius:6px; padding:16px; margin-bottom:16px; background:#fff;">
                <p style="font-size:13px; color:#666; margin:0 0 4px;">{{ $pergunta->codigo }} — {{ $pergunta->categoria }}</p>
                <p style="font-weight:bold; margin:0 0 12px;">{{ $pergunta->texto_pergunta }}</p>

                <label>Resposta (com base nas evidências)</label>
                <select name="respostas[{{ $pergunta->id }}][ha_evidencia]" required
                    onchange="alternarObservacoesObrigatorias(this, {{ $pergunta->id }})">
                    <option value="" disabled @selected(!$resposta)>Selecione…</option>
                    <option value="sim" @selected($resposta?->ha_evidencia === 'sim')>Sim</option>
                    <option value="nao" @selected($resposta?->ha_evidencia === 'nao')>Não</option>
                    <option value="nao_aplicavel" @selected($resposta?->ha_evidencia === 'nao_aplicavel')>Não aplicável</option>
                </select>

                <label>Arquivo da Evidência (selecione um ou mais)</label>
                <select name="respostas[{{ $pergunta->id }}][evidencias][]" multiple size="4">
                    @foreach ($evidencias as $evidencia)
                        <option value="{{ $evidencia->id }}" @selected($matchesAtuais->contains($evidencia->id))>
                            {{ $evidencia->nome_arquivo }} ({{ $evidencia->status_processamento }})
                        </option>
                    @endforeach
                </select>

                <label id="label-observacoes-{{ $pergunta->id }}">
                    Observações
                    @if ($resposta?->ha_evidencia === 'nao')
                        <span style="color:#991b1b;">(obrigatório)</span>
                    @endif
                </label>
                <textarea name="respostas[{{ $pergunta->id }}][observacoes]" rows="2">{{ $resposta?->observacoes }}</textarea>

                <label>Parecer</label>
                <textarea name="respostas[{{ $pergunta->id }}][parecer]" rows="2">{{ $resposta?->parecer }}</textarea>
                <p style="font-size:12px; color:#666; margin-top:2px;">
                    Preenchido manualmente por enquanto — nas próximas fases, a IA vai sugerir este parecer com base nas evidências.
                </p>
            </div>
        @endforeach

        <button type="submit">Salvar respostas</button>
    </form>

    <script>
        function alternarObservacoesObrigatorias(select, perguntaId) {
            const label = document.getElementById('label-observacoes-' + perguntaId);
            const marcaObrigatorio = ' <span style="color:#991b1b;">(obrigatório)</span>';
            label.innerHTML = 'Observações' + (select.value === 'nao' ? marcaObrigatorio : '');
        }
    </script>
@endsection
