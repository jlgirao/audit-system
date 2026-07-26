@extends('layouts.app')

@section('titulo', $processo->nome)

@section('conteudo')
    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
            <h2 style="margin-bottom:4px;">{{ $processo->nome }}</h2>
            <p style="color:#666; margin-top:0;">ID: {{ $processo->uuid }}</p>
        </div>
        <span class="badge badge-{{ $processo->status }}" style="font-size:14px;">
            {{ ucfirst(str_replace('_', ' ', $processo->status)) }}
        </span>
    </div>

    @if ($processo->descricao)
        <p>{{ $processo->descricao }}</p>
    @endif

    @if ($podeEditar)
        <a class="btn" href="{{ route('processes.edit', $processo) }}">Editar processo</a>
    @endif

    <h3>Responsáveis</h3>
    <ul>
        @foreach ($processo->responsaveis as $responsavel)
            <li>{{ $responsavel->nome }} — {{ str_replace('_', ' ', $responsavel->pivot->papel_no_processo) }}</li>
        @endforeach
    </ul>

    <h3>Pasta no Dropbox</h3>
    <p><code>{{ $processo->dropbox_folder_path }}</code></p>
    <p style="color:#666; font-size:13px;">
        A sincronização de evidências e o pipeline de extração/IA serão adicionados nas próximas fases.
    </p>

    @if (count($statusDisponiveis) > 0)
        <h3>Transicionar status</h3>
        <form method="POST" action="{{ route('processes.transicionar', $processo) }}">
            @csrf
            <label>Novo status</label>
            <select name="novo_status" required>
                @foreach ($statusDisponiveis as $status)
                    <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
            <label>Comentário</label>
            <textarea name="comentario" rows="2"></textarea>
            <button type="submit">Atualizar status</button>
        </form>
    @else
        <p style="color:#666; font-size:13px;">Você não tem permissão para alterar o status deste processo.</p>
    @endif

    <h3>Histórico de status</h3>
    <table>
        <thead>
        <tr><th>Data</th><th>De</th><th>Para</th><th>Usuário</th><th>Comentário</th></tr>
        </thead>
        <tbody>
        @forelse ($processo->historicoStatus as $registro)
            <tr>
                <td>{{ $registro->criado_em->format('d/m/Y H:i') }}</td>
                <td>{{ $registro->status_anterior ?? '—' }}</td>
                <td>{{ $registro->status_novo }}</td>
                <td>{{ $registro->usuario->nome }}</td>
                <td>{{ $registro->comentario ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sem histórico ainda.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
