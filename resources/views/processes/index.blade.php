@extends('layouts.app')

@section('titulo', 'Projetos de auditoria')

@section('conteudo')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h2 style="margin:0;">Projetos de auditoria</h2>
        <div style="display:flex; gap:12px; align-items:center;">
            @can('excluir-processo')
                <a href="{{ route('processes.excluidos') }}" style="font-size:13px;">🗑️ Ver excluídos</a>
            @endcan
            @can('criar-processo')
                <a class="btn" href="{{ route('processes.create') }}">+ Novo projeto</a>
            @endcan
        </div>
    </div>

    @if ($podeVerTodos)
        <p style="font-size:13px;">
            @if ($mostrandoTodos)
                Mostrando <strong>todos os projetos</strong>.
                <a href="{{ request()->fullUrlWithQuery(['meus' => 1]) }}">Ver só os meus</a>
            @else
                Mostrando <strong>somente os meus projetos</strong>.
                <a href="{{ request()->fullUrlWithQuery(['meus' => 0]) }}">Ver todos</a>
            @endif
        </p>
    @endif

    <form method="GET" style="display:flex; gap:8px; margin-bottom:16px;">
        @if ($mostrandoTodos)
            <input type="hidden" name="meus" value="0">
        @endif
        <input type="text" name="busca" placeholder="Buscar por nome ou ID" value="{{ request('busca') }}" style="flex:1;">
        <select name="status">
            <option value="">Todos os status</option>
            @foreach (['criado','em_analise','em_revisao','devolvido','aprovado','concluido','reaberto'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        </select>
        <button type="submit">Filtrar</button>
        <a href="{{ route('processes.exportar') }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}"
            title="Exportar para Excel" style="display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; background:#166534; color:#fff; border-radius:4px; text-decoration:none; font-size:16px; margin-top:0;">⬇</a>
    </form>

    @include('partials._per_page_selector')

    <table>
        <thead>
        <tr>
            <th>ID</th>
            @include('partials._sort_header', ['coluna' => 'nome', 'label' => 'Nome'])
            <th>Responsáveis</th>
            @include('partials._sort_header', ['coluna' => 'status', 'label' => 'Status'])
            @include('partials._sort_header', ['coluna' => 'updated_at', 'label' => 'Atualizado em'])
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
                    @if ($processo->evidencias_pendentes_count > 0 || $processo->evidencias_ia_processando_count > 0 || $processo->excel_processando_count > 0 || in_array($processo->status_sincronizacao, ['na_fila', 'sincronizando'], true))
                        <span class="badge badge-em_analise" title="Ainda há tarefas em segundo plano rodando (sincronização, extração, IA ou Excel)">⏳ Processando</span>
                    @endif
                </td>
                <td>{{ $processo->responsaveis->pluck('nome')->join(', ') }}</td>
                <td><span class="badge badge-{{ $processo->status }}">{{ ucfirst(str_replace('_', ' ', $processo->status)) }}</span></td>
                <td>{{ $processo->updated_at->diffForHumans() }}</td>
                <td>
                    <div class="acoes">
                        <a href="{{ route('processes.show', $processo) }}" class="acao-btn acao-editar" title="Ver">👁️</a>
                        @can('excluir-processo')
                            <form method="POST" action="{{ route('processes.destroy', $processo) }}"
                                onsubmit="return confirm('Excluir este projeto? Ele deixa de aparecer nas listagens, mas o registro é preservado para fins de auditoria.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="acao-btn acao-remover" title="Excluir">🗑️</button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6">Nenhum projeto encontrado.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;">{{ $processos->links('partials._pagination') }}</div>
@endsection
