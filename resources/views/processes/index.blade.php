@extends('layouts.app')

@section('titulo', 'Processos de auditoria')

@section('conteudo')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h2 style="margin:0;">Processos de auditoria</h2>
        @can('criar-processo')
            <a class="btn" href="{{ route('processes.create') }}">+ Novo processo</a>
        @endcan
    </div>

    <form method="GET" style="display:flex; gap:8px; margin-bottom:16px;">
        <input type="text" name="busca" placeholder="Buscar por nome ou ID" value="{{ request('busca') }}" style="flex:1;">
        <select name="status">
            <option value="">Todos os status</option>
            @foreach (['criado','em_analise','em_revisao','devolvido','aprovado','concluido','reaberto'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        </select>
        <button type="submit">Filtrar</button>
    </form>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Responsáveis</th>
            <th>Status</th>
            <th>Atualizado em</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($processos as $processo)
            <tr>
                <td>{{ substr($processo->uuid, 0, 8) }}</td>
                <td>
                    {{ $processo->nome }}
                    @if ($processo->tem_arquivos_novos)
                        <span title="Há arquivos novos no Dropbox">🔄</span>
                    @endif
                </td>
                <td>{{ $processo->responsaveis->pluck('nome')->join(', ') }}</td>
                <td><span class="badge badge-{{ $processo->status }}">{{ ucfirst(str_replace('_', ' ', $processo->status)) }}</span></td>
                <td>{{ $processo->updated_at->diffForHumans() }}</td>
                <td><a href="{{ route('processes.show', $processo) }}">Ver</a></td>
            </tr>
        @empty
            <tr><td colspan="6">Nenhum processo encontrado.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;">{{ $processos->links() }}</div>
@endsection
