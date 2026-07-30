@extends('layouts.app')

@section('titulo', 'Perguntas de auditoria')

@section('conteudo')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h2 style="margin:0;">Perguntas fixas de auditoria</h2>
        <a class="btn" href="{{ route('questions.create') }}">+ Nova pergunta</a>
    </div>

    <table>
        <thead>
        <tr>
            <th>Código</th>
            <th>Pergunta</th>
            <th>Aba</th>
            <th>Linha</th>
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

    <div style="margin-top:16px;">{{ $perguntas->links() }}</div>
@endsection
