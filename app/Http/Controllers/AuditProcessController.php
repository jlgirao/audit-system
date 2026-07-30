<?php

namespace App\Http\Controllers;

use App\Jobs\SyncProcessEvidenceJob;
use App\Models\AuditProcess;
use App\Models\User;
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

        $query = AuditProcess::query()->with('responsaveis');

        $mostrandoTodos = $podeVerTodos && ! $request->boolean('meus');

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

        $processos = $query->orderByDesc('updated_at')->paginate(15)->withQueryString();

        return view('processes.index', [
            'processos' => $processos,
            'podeVerTodos' => $podeVerTodos,
            'mostrandoTodos' => $mostrandoTodos,
        ]);
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

        $processo->transicionarStatus('criado', $request->user()->id, 'Processo criado.');

        return redirect()->route('processes.show', $processo)->with('status', 'Processo criado com sucesso.');
    }

    public function show(AuditProcess $process)
    {
        $process->load(['responsaveis', 'historicoStatus.usuario', 'respostas.pergunta', 'evidencias', 'arquivosSaida.geradoPor']);

        return view('processes.show', [
            'processo' => $process,
            'podeEditar' => $process->podeSerEditadoPor(request()->user()),
            'statusDisponiveis' => $process->statusDisponiveisPara(request()->user()),
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

        return redirect()->route('processes.show', $process)->with('status', 'Processo atualizado com sucesso.');
    }

    /**
     * Dispara a sincronização de evidências deste processo com o Dropbox.
     * Mesma autorização da edição: responsável atribuído ou admin.
     */
    public function sincronizar(Request $request, AuditProcess $process)
    {
        abort_unless($process->podeSerEditadoPor($request->user()), 403);

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
                'novo_status' => 'Você não tem permissão para mover este processo para este status.',
            ]);
        }

        if (in_array($dados['novo_status'], ['devolvido', 'reaberto']) && empty($dados['comentario'])) {
            return back()->withErrors(['comentario' => 'Informe o motivo para esta transição.']);
        }

        $process->transicionarStatus($dados['novo_status'], $request->user()->id, $dados['comentario'] ?? null);

        return redirect()->route('processes.show', $process)->with('status', 'Status atualizado.');
    }
}
