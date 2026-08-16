@extends('layouts.app')

@section('titulo', 'Projetos excluídos')

@section('conteudo')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h2 style="margin:0;">Projetos excluídos</h2>
        <a href="{{ route('processes.index') }}">← Voltar para projetos ativos</a>
    </div>

    <p style="color:#666; font-size:13px;">
        Projetos excluídos continuam no banco (soft delete) — todas as evidências, respostas,
        sugestões de IA e Excels gerados foram preservados. Restaurar um projeto faz ele voltar
        a aparecer normalmente na listagem principal.
    </p>

    @include('partials._per_page_selector')

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Responsáveis</th>
            <th>Excluído em</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($processos as $processo)
            <tr>
                <td>{{ substr($processo->uuid, 0, 8) }}</td>
                <td>{{ $processo->nome }}</td>
                <td>{{ $processo->responsaveis->pluck('nome')->join(', ') }}</td>
                <td>{{ $processo->deleted_at->format('d/m/Y H:i') }}</td>
                <td>
                    <form method="POST" action="{{ route('processes.restaurar', $processo) }}"
                        onsubmit="return confirm('Restaurar este projeto? Ele volta a aparecer na listagem normal.');">
                        @csrf
                        <button type="submit" style="background:#166534; margin-top:0;">Restaurar</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5">Nenhum projeto excluído.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;">{{ $processos->links('partials._pagination') }}</div>
@endsection
