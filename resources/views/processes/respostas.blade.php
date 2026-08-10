@extends('layouts.app')

@section('titulo', 'Responder perguntas')

@section('conteudo')
    <h2>Responder perguntas — {{ $processo->nome }}</h2>
    <p style="color:#666; font-size:13px;">
        Preenchimento manual (sem IA nesta fase). Responda com base nas evidências vinculadas.
        Pode salvar aos poucos — só as perguntas que você preencher nesta submissão são gravadas;
        as demais continuam como estavam. Se "Observações" ficar em branco com resposta "Não", é
        preenchido automaticamente como "N/A".
    </p>

    <form method="GET" style="display:flex; gap:8px; margin-bottom:16px; align-items:center;">
        <label style="font-weight:normal; margin:0;">Filtrar por aba:</label>
        <select name="aba" onchange="this.form.submit()">
            <option value="">Todas as abas</option>
            @foreach ($abasDisponiveis as $aba)
                <option value="{{ $aba }}" @selected(request('aba') === $aba)>{{ $aba }}</option>
            @endforeach
        </select>

        <label style="font-weight:normal; margin:0;">Filtrar por:</label>
        <select name="filtro" onchange="this.form.submit()">
            <option value="" @selected(!request('filtro'))>Todas as perguntas</option>
            <option value="com_sugestao" @selected(request('filtro') === 'com_sugestao')>Com sugestão da IA</option>
            <option value="sem_resposta" @selected(request('filtro') === 'sem_resposta')>Sem resposta ainda</option>
        </select>
        @if (request('per_page'))
            <input type="hidden" name="per_page" value="{{ request('per_page') }}">
        @endif
    </form>

    @include('partials._per_page_selector')

    <form method="POST" action="{{ route('processes.respostas.aplicar_nao', $processo) }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}" style="margin-bottom:20px;">
        @csrf
        <button type="submit" style="background:#57534e;">
            Aplicar "Não" às perguntas sem evidência
        </button>
        <span style="font-size:12px; color:#666; margin-left:8px;">
            Preenche automaticamente (Resposta = Não, Observações = N/A) só as perguntas que ainda não têm
            nenhuma resposta salva e nenhuma evidência confirmada — não sobrescreve nada já preenchido.
            <strong>Aplica em todas as perguntas do projeto, não só na página atual.</strong>
        </span>
    </form>

    <form method="POST" action="{{ route('processes.respostas.update', $processo) }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">
        @csrf
        @method('PUT')

        @foreach ($perguntas as $pergunta)
            @php
                $resposta = $respostas->get($pergunta->id);
                $matchesAtuais = $matchesConfirmados->get($pergunta->id, collect())->pluck('evidence_file_id');
                $sugestoes = $matchesSugeridos->get($pergunta->id, collect());
            @endphp
            <div style="border:1px solid #ddd; border-radius:6px; padding:16px; margin-bottom:16px; background:#fff;">
                <p style="font-size:13px; color:#666; margin:0 0 4px;">{{ $pergunta->codigo }} — {{ $pergunta->categoria }}</p>
                <p style="font-weight:bold; margin:0 0 12px;">{{ $pergunta->texto_pergunta }}</p>

                @if ($sugestoes->isNotEmpty())
                    <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:10px 12px; margin-bottom:12px;">
                        <p style="font-size:12px; font-weight:bold; margin:0 0 8px; color:#334155;">🤖 Sugestões da IA</p>
                        @foreach ($sugestoes as $sugestao)
                            @php
                                $corResposta = match ($sugestao->resposta_sugerida) {
                                    'sim' => ['bg' => '#dcfce7', 'text' => '#166534', 'label' => 'Sim'],
                                    'nao' => ['bg' => '#fee2e2', 'text' => '#991b1b', 'label' => 'Não'],
                                    'nao_aplicavel' => ['bg' => '#e5e7eb', 'text' => '#374151', 'label' => 'Não aplicável'],
                                    default => ['bg' => '#e5e7eb', 'text' => '#374151', 'label' => '—'],
                                };
                            @endphp
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 8px; margin-top:4px; border-radius:4px; background:{{ $corResposta['bg'] }};">
                                <span style="font-size:13px; color:#1f2937;">
                                    {{ $sugestao->evidencia->nome_arquivo }}
                                    — <strong>{{ round($sugestao->score_confianca) }}% confiança</strong>
                                    — resposta sugerida: <strong style="color:{{ $corResposta['text'] }};">{{ $corResposta['label'] }}</strong>
                                </span>
                                <button type="button"
                                    onclick="usarSugestaoIA({{ $pergunta->id }}, {{ $sugestao->evidence_file_id }}, {{ json_encode($sugestao->evidencia->nome_arquivo) }}, '{{ $sugestao->resposta_sugerida }}', {{ json_encode($sugestao->parecer_sugerido) }})"
                                    style="margin-top:0; padding:4px 10px; font-size:12px; background:#1f2937;">
                                    Usar esta sugestão
                                </button>
                            </div>
                            @if ($sugestao->parecer_sugerido)
                                <p style="font-size:12px; color:{{ $corResposta['text'] }}; margin:2px 0 8px; font-style:italic;">"{{ $sugestao->parecer_sugerido }}"</p>
                            @endif
                        @endforeach
                    </div>
                @endif

                <label>Resposta (com base nas evidências)</label>
                <select id="select-resposta-{{ $pergunta->id }}" name="respostas[{{ $pergunta->id }}][ha_evidencia]"
                    onchange="alternarObservacoesObrigatorias(this, {{ $pergunta->id }})">
                    <option value="" @selected(!$resposta)>Ainda não respondida</option>
                    <option value="sim" @selected($resposta?->ha_evidencia === 'sim')>Sim</option>
                    <option value="nao" @selected($resposta?->ha_evidencia === 'nao')>Não</option>
                    <option value="nao_aplicavel" @selected($resposta?->ha_evidencia === 'nao_aplicavel')>Não aplicável</option>
                </select>

                <label>Arquivo da Evidência (selecione um ou mais)</label>
                <select id="select-evidencias-{{ $pergunta->id }}" name="respostas[{{ $pergunta->id }}][evidencias][]" multiple size="4">
                    @foreach ($evidencias as $evidencia)
                        <option value="{{ $evidencia->id }}" @selected($matchesAtuais->contains($evidencia->id))>
                            {{ $evidencia->nome_arquivo }} ({{ $evidencia->status_processamento }})
                        </option>
                    @endforeach
                </select>

                <label id="label-observacoes-{{ $pergunta->id }}">
                    Observações
                    @if ($resposta?->ha_evidencia === 'nao')
                        <span style="color:#92400e;">(se em branco, vira "N/A")</span>
                    @endif
                </label>
                <textarea name="respostas[{{ $pergunta->id }}][observacoes]" rows="2">{{ $resposta?->observacoes }}</textarea>

                <label>Parecer</label>
                <textarea id="textarea-parecer-{{ $pergunta->id }}" name="respostas[{{ $pergunta->id }}][parecer]" rows="2">{{ $resposta?->parecer }}</textarea>
                <p style="font-size:12px; color:#666; margin-top:2px;">
                    Pode ser preenchido manualmente ou aceito a partir de uma sugestão da IA acima — sempre revise antes de salvar.
                </p>
            </div>
        @endforeach

        <button type="submit">Salvar respostas</button>
    </form>

    <div style="margin-top:16px;">{{ $perguntas->links('partials._pagination') }}</div>

    <script>
        function alternarObservacoesObrigatorias(select, perguntaId) {
            const label = document.getElementById('label-observacoes-' + perguntaId);
            const marcaAviso = ' <span style="color:#92400e;">(se em branco, vira "N/A")</span>';
            label.innerHTML = 'Observações' + (select.value === 'nao' ? marcaAviso : '');
        }

        function usarSugestaoIA(perguntaId, evidenciaId, evidenciaNome, respostaSugerida, parecerSugerido) {
            const selectResposta = document.getElementById('select-resposta-' + perguntaId);
            selectResposta.value = respostaSugerida;
            alternarObservacoesObrigatorias(selectResposta, perguntaId);

            const selectEvidencias = document.getElementById('select-evidencias-' + perguntaId);
            for (const opcao of selectEvidencias.options) {
                if (parseInt(opcao.value, 10) === evidenciaId) {
                    opcao.selected = true;
                }
            }

            if (parecerSugerido) {
                const textarea = document.getElementById('textarea-parecer-' + perguntaId);
                const trechoNovo = evidenciaNome ? (evidenciaNome + ': ' + parecerSugerido) : parecerSugerido;

                // Acumula em vez de substituir — assim, aceitar mais de uma
                // sugestão para a mesma pergunta junta os pareceres, em vez
                // de o último clique apagar o anterior. Evita duplicar se
                // clicar duas vezes na mesma sugestão.
                if (! textarea.value.includes(parecerSugerido)) {
                    textarea.value = textarea.value.trim()
                        ? textarea.value.trim() + '\n\n' + trechoNovo
                        : trechoNovo;
                }
            }
        }
    </script>
@endsection
