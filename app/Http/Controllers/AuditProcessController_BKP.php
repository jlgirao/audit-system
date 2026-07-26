<?php

namespace App\Http\Controllers;

use App\Models\AuditProcess;
use App\Models\User;
use Illuminate\Http\Request;

class AuditProcessController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Lista de processos. Admin vê tudo; analista/auditor
     * veem por padrão os processos em que estão atribuídos
     * (pode ser sobrescrito com ?todos=1 se tiver permissão).
     */
    public function index(Request $request)
    {
        $usuario = $request->user();

        $query = AuditProcess::query()->with('responsaveis');

        $verTodos = $request->boolean('todos') && $usuario->can('ver-todos-processos');

        if (! $verTodos) {
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

        return view('processes.index', compact('processos', 'verTodos'));
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
            'dropbox_folder_path' => ['required', 'string', 'max:500'],
            'responsaveis' => ['required', 'array', 'min:1'],
            'responsaveis.*' => ['exists:users,id'],
            'papel_principal' => ['required', 'exists:users,id'],
        ]);

        $processo = AuditProcess::create([
            'nome' => $dados['nome'],
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
        $process->load(['responsaveis', 'historicoStatus.usuario', 'respostas.pergunta', 'evidencias']);

        return view('processes.show', ['processo' => $process]);
    }

    /**
     * Transições de estado simples da Fase 0. As regras de quem pode
     * transicionar de qual status para qual devem evoluir para uma
     * Policy dedicada nas próximas fases.
     */
    public function transicionar(Request $request, AuditProcess $process)
    {
        $dados = $request->validate([
            'novo_status' => ['required', 'in:em_analise,em_revisao,devolvido,aprovado,concluido,reaberto'],
            'comentario' => ['nullable', 'string'],
        ]);

        if (in_array($dados['novo_status'], ['devolvido', 'reaberto']) && empty($dados['comentario'])) {
            return back()->withErrors(['comentario' => 'Informe o motivo para esta transição.']);
        }

        $process->transicionarStatus($dados['novo_status'], $request->user()->id, $dados['comentario'] ?? null);

        return redirect()->route('processes.show', $process)->with('status', 'Status atualizado.');
    }
}
