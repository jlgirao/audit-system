@extends('layouts.app')

@section('titulo', $processo->nome)

@section('conteudo')
    @if ($resumoIa['em_processamento'])
        <div style="background:#eef2ff; border:1px solid #c7d2fe; border-radius:6px; padding:10px 16px; margin-bottom:16px; font-size:14px; color:#3730a3;">
            ⏳ <strong>Este processo tem tarefas em segundo plano ainda rodando</strong> — a página vai se
            atualizar sozinha a cada 15 segundos até tudo terminar.
            <span style="font-size:12px; display:block; margin-top:4px; color:#4338ca;">
                @if ($resumoIa['status_sincronizacao'] === 'na_fila')
                    Sincronização com o Dropbox está na fila, aguardando a vez (pode demorar se houver outros processos na frente).
                @elseif ($resumoIa['status_sincronizacao'] === 'sincronizando')
                    Sincronizando com o Dropbox agora.
                @endif
                @if ($resumoIa['pendentes_extracao'] > 0)
                    {{ $resumoIa['pendentes_extracao'] }} evidência(s) aguardando extração/OCR.
                @endif
                @if ($resumoIa['ia_processando'] > 0)
                    {{ $resumoIa['ia_processando'] }} evidência(s) com embedding/matching de IA rodando agora.
                @endif
                @if ($resumoIa['excel_processando'] > 0)
                    Geração de Excel em andamento.
                @endif
            </span>
        </div>
    @endif

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

    @can('excluir-processo')
        <form method="POST" action="{{ route('processes.destroy', $processo) }}" style="display:inline-block;"
            onsubmit="return confirm('Excluir este processo? Ele deixa de aparecer nas listagens, mas o registro é preservado para fins de auditoria.');">
            @csrf
            @method('DELETE')
            <button type="submit" style="background:#991b1b;">Excluir processo</button>
        </form>
    @endcan

    <h3>Responsáveis</h3>
    <ul>
        @foreach ($processo->responsaveis as $responsavel)
            <li>{{ $responsavel->nome }} — {{ str_replace('_', ' ', $responsavel->pivot->papel_no_processo) }}</li>
        @endforeach
    </ul>

    <h3>Pasta no Dropbox</h3>
    <p><code>{{ $processo->dropbox_folder_path }}</code>
        @if ($processo->tem_arquivos_novos)
            <span style="color:#92400e;">🔄 há arquivos novos desde a última sincronização</span>
        @endif
    </p>

    <p style="font-size:13px; color:#666;">
        🤖 IA: {{ $resumoIa['com_texto'] }} evidência(s) com texto extraído,
        {{ $resumoIa['com_embedding'] }} com embedding gerado,
        {{ $resumoIa['sugestoes'] }} sugestão(ões) de match no total.
        @if ($resumoIa['ia_aguardando'] > 0)
            <br>{{ $resumoIa['ia_aguardando'] }} evidência(s) com texto pronto, mas ainda sem IA rodada —
            clique em "Rodar matching por IA" abaixo para processá-las.
        @endif
    </p>

    @if ($podeEditar)
        <form method="POST" action="{{ route('processes.sincronizar', $processo) }}" style="display:inline-block; margin-right:8px;">
            @csrf
            <button type="submit">Sincronizar agora</button>
        </form>
        <form method="POST" action="{{ route('processes.matching', $processo) }}" style="display:inline-block;">
            @csrf
            <button type="submit">🤖 Rodar matching por IA</button>
        </form>
    @endif

    <h3>Evidências ({{ $processo->evidencias->count() }})</h3>
    <table>
        <thead>
        <tr><th>Arquivo</th><th>Tipo</th><th>Status</th><th>Origem do texto</th><th>Status IA</th><th>Observação</th><th></th></tr>
        </thead>
        <tbody>
        @forelse ($processo->evidencias as $evidencia)
            <tr>
                <td>{{ $evidencia->nome_arquivo }}</td>
                <td>{{ strtoupper($evidencia->tipo_arquivo) }}</td>
                <td>
                    @if ($evidencia->status_processamento === 'erro')
                        <span class="badge badge-devolvido">Erro</span>
                    @elseif ($evidencia->status_processamento === 'concluido')
                        <span class="badge badge-concluido">Concluído</span>
                    @elseif ($evidencia->status_processamento === 'processando')
                        <span class="badge badge-em_analise">Processando…</span>
                    @else
                        <span class="badge badge-criado">{{ ucfirst($evidencia->status_processamento) }}</span>
                    @endif
                </td>
                <td>{{ $evidencia->origem_texto ? ucfirst($evidencia->origem_texto) : '—' }}</td>
                <td style="font-size:12px;">
                    @if (! $evidencia->texto_extraido)
                        <span style="color:#999;">Aguardando texto</span>
                    @elseif ($evidencia->status_ia === 'processando')
                        <span style="color:#1d4ed8;">⏳ Processando…</span>
                    @elseif ($evidencia->status_ia === 'erro')
                        <span style="color:#991b1b;">Erro na IA</span>
                    @elseif (! $evidencia->embedding_vector)
                        <span style="color:#92400e;">Sem embedding ainda</span>
                    @elseif ($evidencia->matches_count > 0)
                        <span style="color:#166534;">{{ $evidencia->matches_count }} sugestão(ões)</span>
                    @else
                        <span style="color:#999;">Nenhuma sugestão (abaixo do limiar)</span>
                    @endif
                </td>
                <td style="font-size:12px; color:#666;">{{ $evidencia->erro_detalhe ?? '—' }}</td>
                <td>
                    @if ($podeEditar && in_array($evidencia->status_processamento, ['erro', 'pendente']))
                        <form method="POST" action="{{ route('evidences.reprocessar', [$processo, $evidencia]) }}">
                            @csrf
                            <button type="submit" class="acao-btn acao-duplicar" title="Reprocessar">🔄</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7">Nenhuma evidência sincronizada ainda. Clique em "Sincronizar agora".</td></tr>
        @endforelse
        </tbody>
    </table>
    <p style="color:#666; font-size:13px;">
        Imagens (PNG/JPEG) e PDFs escaneados agora passam por OCR automaticamente (Fase 2).
        O matching automático com as perguntas entra na Fase 3.
    </p>

    @if ($podeEditar)
        <a class="btn" href="{{ route('processes.respostas.edit', $processo) }}">Responder perguntas</a>
    @endif

    <h3>Excel de saída</h3>
    @if ($podeEditar)
        <form method="POST" action="{{ route('processes.excel.gerar', $processo) }}">
            @csrf
            <button type="submit">Gerar nova versão do Excel</button>
        </form>
    @endif
    <table style="margin-top:12px;">
        <thead>
        <tr><th>Versão</th><th>Status</th><th>Gerado em</th><th>Gerado por</th><th></th></tr>
        </thead>
        <tbody>
        @forelse ($processo->arquivosSaida as $arquivo)
            <tr>
                <td>v{{ $arquivo->versao }}</td>
                <td>
                    @if ($arquivo->status === 'processando')
                        <span class="badge badge-em_analise">Processando…</span>
                    @elseif ($arquivo->status === 'erro')
                        <span class="badge badge-devolvido" title="{{ $arquivo->erro_detalhe }}">Erro</span>
                    @else
                        <span class="badge badge-concluido">Concluído</span>
                    @endif
                </td>
                <td>{{ \Illuminate\Support\Carbon::parse($arquivo->gerado_em)->format('d/m/Y H:i') }}</td>
                <td>{{ $arquivo->geradoPor->nome }}</td>
                <td>
                    @if ($arquivo->status === 'concluido')
                        <a href="{{ route('processes.excel.download', [$processo, $arquivo]) }}">Baixar</a>
                    @elseif ($arquivo->status === 'erro')
                        <span style="font-size:12px; color:#991b1b;">{{ $arquivo->erro_detalhe }}</span>
                    @else
                        —
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">Nenhuma versão gerada ainda.</td></tr>
        @endforelse
        </tbody>
    </table>

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

    @if ($resumoIa['em_processamento'])
        <script>
            // Atualiza a página sozinha enquanto houver processamento pendente,
            // para o usuário não precisar ficar clicando em atualizar. Para de
            // atualizar automaticamente assim que tudo terminar (o banner some
            // e este script deixa de ser renderizado no próximo carregamento).
            setTimeout(() => window.location.reload(), 15000);
        </script>
    @endif
@endsection
