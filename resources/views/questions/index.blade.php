@extends('layouts.app')

@section('titulo', 'Perguntas de auditoria')

@section('conteudo')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h2 style="margin:0;">Perguntas fixas de auditoria</h2>
        <a class="btn" href="{{ route('questions.create') }}">+ Nova pergunta</a>
    </div>

    <form method="GET" style="display:flex; gap:8px; margin-bottom:16px;">
        <input type="text" name="busca" placeholder="Buscar por código ou texto" value="{{ request('busca') }}" style="flex:1;">
        <select name="aba">
            <option value="">Todas as abas</option>
            @foreach ($abasDisponiveis as $aba)
                <option value="{{ $aba }}" @selected(request('aba') === $aba)>{{ $aba }}</option>
            @endforeach
        </select>
        <button type="submit">Filtrar</button>
        <a href="{{ route('questions.exportar') }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}"
            title="Exportar para Excel" style="display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; background:#166534; color:#fff; border-radius:4px; text-decoration:none; font-size:16px; margin-top:0;">⬇</a>
    </form>

    @include('partials._per_page_selector')

    <table>
        <thead>
        <tr>
            @include('partials._sort_header', ['coluna' => 'codigo', 'label' => 'Código'])
            @include('partials._sort_header', ['coluna' => 'texto_pergunta', 'label' => 'Pergunta'])
            @include('partials._sort_header', ['coluna' => 'aba_excel', 'label' => 'Aba'])
            @include('partials._sort_header', ['coluna' => 'linha_excel', 'label' => 'Linha'])
            <th>Col. Resposta</th>
            <th>Col. Observações</th>
            <th>Col. Arq. Evidência</th>
            <th>Col. Parecer</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($perguntas as $pergunta)
            <tr>
                <td>{{ $pergunta->codigo }}</td>
                <td>{{ $pergunta->texto_pergunta }}</td>
                <td>{{ $pergunta->aba_excel }}</td>
                <td>{{ $pergunta->linha_excel }}</td>
                <td>{{ $pergunta->coluna_ha_evidencia ?? '⚠️ não definida' }}</td>
                <td>{{ $pergunta->coluna_observacoes }}</td>
                <td>{{ $pergunta->coluna_evidencia }}</td>
                <td>{{ $pergunta->coluna_parecer ?? '⚠️ não definida' }}</td>
                <td>
                    <div class="acoes">
                        <a href="{{ route('questions.edit', $pergunta) }}" class="acao-btn acao-editar" title="Editar">✏️</a>
                        <form method="POST" action="{{ route('questions.duplicar', $pergunta) }}">
                            @csrf
                            <button type="submit" class="acao-btn acao-duplicar" title="Duplicar">📋</button>
                        </form>
                        <form method="POST" action="{{ route('questions.destroy', $pergunta) }}" onsubmit="return confirm('Remover esta pergunta?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="acao-btn acao-remover" title="Remover">🗑️</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="9">Nenhuma pergunta cadastrada.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;">{{ $perguntas->links('partials._pagination') }}</div>
@endsection
