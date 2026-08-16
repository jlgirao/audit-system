<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateEmbeddingJob;
use App\Jobs\MatchEvidenceToQuestionsJob;
use App\Jobs\SyncProcessEvidenceJob;
use App\Models\AuditProcess;
use App\Models\User;
use App\Services\ExcelExporter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class AuditProcessController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
        ];
    }

    /**
     * Lista de processos.
     * Ponto 3: admin (ou qualquer usuário com "ver-todos-processos") vê
     * todos os processos por padrão, sem precisar de parâmetro na URL.
     * Ele pode opcionalmente filtrar só os seus com ?meus=1.
     */
    public function index(Request $request)
    {
        $usuario = $request->user();
        $podeVerTodos = $usuario->can('ver-todos-processos');
        $mostrandoTodos = $podeVerTodos && ! $request->boolean('meus');

        $query = $this->queryFiltrada($request)
            ->with('responsaveis')
            ->withCount([
                'evidencias as evidencias_pendentes_count' => fn ($q) => $q->whereIn('status_processamento', ['pendente', 'processando']),
                'evidencias as evidencias_ia_processando_count' => fn ($q) => $q->where('status_ia', 'processando'),
                'arquivosSaida as excel_processando_count' => fn ($q) => $q->where('status', 'processando'),
            ]);

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 50, 100], true)
            ? (int) $request->input('per_page')
            : 20;

        $processos = $query->paginate($perPage)->withQueryString();

        return view('processes.index', [
            'processos' => $processos,
            'podeVerTodos' => $podeVerTodos,
            'mostrandoTodos' => $mostrandoTodos,
        ]);
    }

    /**
     * Exporta para Excel exatamente o resultado do filtro/busca/ordenação
     * ativos na tela (mesma query do index(), só sem paginar).
     */
    public function exportar(Request $request)
    {
        $processos = $this->queryFiltrada($request)->with('responsaveis')->get();

        $cabecalhos = ['ID', 'Nome', 'Responsáveis', 'Status', 'Atualizado em'];

        $linhas = $processos->map(fn (AuditProcess $processo) => [
            substr($processo->uuid, 0, 8),
            $processo->nome,
            $processo->responsaveis->pluck('nome')->join(', '),
            ucfirst(str_replace('_', ' ', $processo->status)),
            $processo->updated_at->format('d/m/Y H:i'),
        ]);

        return ExcelExporter::gerar($cabecalhos, $linhas, 'projetos.xlsx');
    }

    /**
     * Monta a query com visibilidade (meus/todos), busca, filtro de status
     * e ordenação — usada tanto pela listagem quanto pela exportação, para
     * garantir que a exportação sempre reflita exatamente o que está
     * filtrado na tela.
     */
    private function queryFiltrada(Request $request)
    {
        $usuario = $request->user();
        $podeVerTodos = $usuario->can('ver-todos-processos');
        $mostrandoTodos = $podeVerTodos && ! $request->boolean('meus');

        $query = AuditProcess::query();

        if (! $mostrandoTodos) {
            $query->whereHas('responsaveis', fn ($q) => $q->where('users.id', $usuario->id));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($busca = $request->input('busca')) {
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('uuid', 'like', "%{$busca}%");
            });
        }

        // Ordenação clicável pelo cabeçalho — whitelist de colunas por
        // segurança (nunca aceitar o nome da coluna direto da URL sem checar).
        $colunasOrdenaveis = ['nome', 'status', 'updated_at'];
        $sort = in_array($request->input('sort'), $colunasOrdenaveis, true) ? $request->input('sort') : 'updated_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction);
    }

    public function create()
    {
        $usuarios = User::where('ativo', true)->orderBy('nome')->get();

        return view('processes.create', compact('usuarios'));
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string'],
            'dropbox_folder_path' => ['required', 'string', 'max:500'],
            'responsaveis' => ['required', 'array', 'min:1'],
            'responsaveis.*' => ['exists:users,id'],
            'papel_principal' => ['required', 'exists:users,id'],
        ]);

        $processo = AuditProcess::create([
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'dropbox_folder_path' => $dados['dropbox_folder_path'],
            'status' => 'criado',
            'criado_por' => $request->user()->id,
        ]);

        foreach ($dados['responsaveis'] as $userId) {
            $processo->responsaveis()->attach($userId, [
                'papel_no_processo' => $userId == $dados['papel_principal']
                    ? 'responsavel_principal'
                    : 'colaborador',
                'atribuido_por' => $request->user()->id,
                'atribuido_em' => now(),
            ]);
        }

        $processo->transicionarStatus('criado', $request->user()->id, 'Projeto criado.');

        return redirect()->route('processes.show', $processo)->with('status', 'Projeto criado com sucesso.');
    }

    public function show(AuditProcess $process)
    {
        $process->load([
            'responsaveis',
            'historicoStatus.usuario',
            'respostas.pergunta',
            'evidencias' => fn ($query) => $query->withCount('matches'),
            'arquivosSaida.geradoPor',
        ]);

        $pendentesExtracao = $process->evidencias->whereIn('status_processamento', ['pendente', 'processando'])->count();
        $iaProcessando = $process->evidencias->where('status_ia', 'processando')->count();
        $iaAguardando = $process->evidencias->whereNotNull('texto_extraido')->where('status_ia', 'pendente')->count();
        $excelProcessando = $process->arquivosSaida->where('status', 'processando')->count();
        $sincronizandoAgora = in_array($process->status_sincronizacao, ['na_fila', 'sincronizando'], true);

        $resumoIa = [
            'com_texto' => $process->evidencias->whereNotNull('texto_extraido')->count(),
            'com_embedding' => $process->evidencias->whereNotNull('embedding_vector')->count(),
            'sugestoes' => $process->evidencias->sum('matches_count'),
            'pendentes_extracao' => $pendentesExtracao,
            'ia_processando' => $iaProcessando,
            'ia_aguardando' => $iaAguardando,
            'excel_processando' => $excelProcessando,
            'sincronizando_agora' => $sincronizandoAgora,
            'status_sincronizacao' => $process->status_sincronizacao,
            // "aguardando" não entra aqui de propósito: significa que
            // ninguém clicou em "Rodar matching por IA" ainda, não que
            // algo está rodando agora — não faz sentido a página ficar
            // se atualizando sozinha à toa nesse caso.
            'em_processamento' => $pendentesExtracao > 0 || $iaProcessando > 0 || $excelProcessando > 0 || $sincronizandoAgora,
        ];

        return view('processes.show', [
            'processo' => $process,
            'podeEditar' => $process->podeSerEditadoPor(request()->user()),
            'statusDisponiveis' => $process->statusDisponiveisPara(request()->user()),
            'resumoIa' => $resumoIa,
        ]);
    }

    /**
     * Ponto 4: edição de nome/descrição/responsáveis. Autorização:
     * responsável do processo ou admin (ver AuditProcess::podeSerEditadoPor).
     */
    public function edit(AuditProcess $process)
    {
        abort_unless($process->podeSerEditadoPor(request()->user()), 403);

        $usuarios = User::where('ativo', true)->orderBy('nome')->get();
        $process->load('responsaveis');

        return view('processes.edit', ['processo' => $process, 'usuarios' => $usuarios]);
    }

    public function update(Request $request, AuditProcess $process)
    {
        abort_unless($process->podeSerEditadoPor($request->user()), 403);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string'],
            'dropbox_folder_path' => ['required', 'string', 'max:500'],
            'responsaveis' => ['required', 'array', 'min:1'],
            'responsaveis.*' => ['exists:users,id'],
            'papel_principal' => ['required', 'exists:users,id'],
        ]);

        $process->update([
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'dropbox_folder_path' => $dados['dropbox_folder_path'],
        ]);

        // Resincroniza responsáveis por completo (mais simples e previsível
        // do que tentar calcular diffs de adição/remoção).
        $process->responsaveis()->detach();

        foreach ($dados['responsaveis'] as $userId) {
            $process->responsaveis()->attach($userId, [
                'papel_no_processo' => $userId == $dados['papel_principal']
                    ? 'responsavel_principal'
                    : 'colaborador',
                'atribuido_por' => $request->user()->id,
                'atribuido_em' => now(),
            ]);
        }

        return redirect()->route('processes.show', $process)->with('status', 'Projeto atualizado com sucesso.');
    }

    /**
     * Dispara (ou redispara) o matching por IA para todas as evidências já
     * extraídas do processo — útil depois de ajustar o prompt/modelo em
     * /admin/ia, ou para evidências sincronizadas antes da Fase 3 existir.
     */
    public function rodarMatching(Request $request, AuditProcess $process)
    {
        abort_unless($process->podeSerEditadoPor($request->user()), 403);

        $evidencias = $process->evidencias()
            ->where('status_processamento', 'concluido')
            ->whereNotNull('texto_extraido')
            ->get();

        foreach ($evidencias as $evidencia) {
            if ($evidencia->embedding_vector) {
                MatchEvidenceToQuestionsJob::dispatch($evidencia->id);
            } else {
                GenerateEmbeddingJob::dispatch($evidencia->id);
            }
        }

        return redirect()->route('processes.show', $process)
            ->with('status', "Matching por IA iniciado para {$evidencias->count()} evidência(s) — atualize a tela de respostas em instantes.");
    }

    /**
     * Dispara a sincronização de evidências deste processo com o Dropbox.
     * Mesma autorização da edição: responsável atribuído ou admin.
     */
    public function sincronizar(Request $request, AuditProcess $process)
    {
        abort_unless($process->podeSerEditadoPor($request->user()), 403);

        // Marca "na_fila" já aqui, antes do job ser efetivamente
        // processado pelo worker — cobre o período de espera na fila,
        // que pode ser longo se houver outros jobs pesados na frente.
        $process->update(['status_sincronizacao' => 'na_fila']);

        SyncProcessEvidenceJob::dispatch($process->id);

        return redirect()->route('processes.show', $process)
            ->with('status', 'Sincronização com o Dropbox iniciada — os arquivos devem aparecer em instantes.');
    }

    /**
     * Ponto 5 e 6: valida no servidor (não só na tela) se o usuário tem
     * permissão para o status pedido, usando AuditProcess::statusDisponiveisPara.
     */
    public function transicionar(Request $request, AuditProcess $process)
    {
        $dados = $request->validate([
            'novo_status' => ['required', 'in:em_analise,em_revisao,devolvido,aprovado,concluido,reaberto'],
            'comentario' => ['nullable', 'string'],
        ]);

        $permitidos = $process->statusDisponiveisPara($request->user());

        if (! in_array($dados['novo_status'], $permitidos, true)) {
            return back()->withErrors([
                'novo_status' => 'Você não tem permissão para mover este projeto para este status.',
            ]);
        }

        if (in_array($dados['novo_status'], ['devolvido', 'reaberto']) && empty($dados['comentario'])) {
            return back()->withErrors(['comentario' => 'Informe o motivo para esta transição.']);
        }

        $process->transicionarStatus($dados['novo_status'], $request->user()->id, $dados['comentario'] ?? null);

        return redirect()->route('processes.show', $process)->with('status', 'Status atualizado.');
    }

    /**
     * Exclusão (soft delete — preserva o registro para fins de auditoria,
     * só deixa de aparecer nas listagens). Restrito à permissão
     * "excluir-processo", que hoje só o perfil admin tem.
     */
    public function destroy(Request $request, AuditProcess $process)
    {
        abort_unless($request->user()->can('excluir-processo'), 403);

        $process->delete();

        return redirect()->route('processes.index')->with('status', 'Projeto excluído com sucesso.');
    }

    /**
     * Lista os processos excluídos (soft delete) — mesma permissão de
     * quem pode excluir, já que é a mesma responsabilidade.
     */
    public function excluidos(Request $request)
    {
        abort_unless($request->user()->can('excluir-processo'), 403);

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 50, 100], true)
            ? (int) $request->input('per_page')
            : 20;

        $processos = AuditProcess::onlyTrashed()
            ->with('responsaveis')
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('processes.excluidos', compact('processos'));
    }

    /**
     * Restaura um processo excluído. A rota usa ->withTrashed() para o
     * model binding conseguir encontrar o registro mesmo estando com
     * soft delete (por padrão, o binding automático não encontraria).
     */
    public function restaurar(Request $request, AuditProcess $process)
    {
        abort_unless($request->user()->can('excluir-processo'), 403);

        $process->restore();

        return redirect()->route('processes.show', $process)->with('status', 'Projeto restaurado com sucesso.');
    }
}
