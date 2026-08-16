<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ExcelExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Routing\Controllers\HasMiddleware;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'permission:gerenciar-usuarios',
        ];
    }

    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 50, 100], true)
            ? (int) $request->input('per_page')
            : 20;

        $usuarios = $this->queryFiltrada($request)->with('roles')->paginate($perPage)->withQueryString();
        $rolesDisponiveis = Role::orderBy('name')->get();

        return view('admin.users.index', compact('usuarios', 'rolesDisponiveis'));
    }

    /**
     * Exporta para Excel exatamente o resultado do filtro/busca/ordenação
     * ativos na tela.
     */
    public function exportar(Request $request)
    {
        $usuarios = $this->queryFiltrada($request)->with('roles')->get();

        $cabecalhos = ['Nome', 'E-mail', 'Perfis', 'Ativo'];

        $linhas = $usuarios->map(fn (User $usuario) => [
            $usuario->nome,
            $usuario->email,
            $usuario->roles->pluck('name')->map(fn ($r) => ucfirst($r))->join(', '),
            $usuario->ativo ? 'Sim' : 'Não',
        ]);

        return ExcelExporter::gerar($cabecalhos, $linhas, 'usuarios.xlsx');
    }

    /**
     * Monta a query com busca (nome/e-mail), filtro por perfil, filtro por
     * ativo/inativo e ordenação — usada tanto pela listagem quanto pela
     * exportação.
     */
    private function queryFiltrada(Request $request)
    {
        $query = User::query();

        if ($busca = $request->input('busca')) {
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('email', 'like', "%{$busca}%");
            });
        }

        if ($perfil = $request->input('perfil')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $perfil));
        }

        if ($request->input('status') === 'ativo') {
            $query->where('ativo', true);
        } elseif ($request->input('status') === 'inativo') {
            $query->where('ativo', false);
        }

        $colunasOrdenaveis = ['nome', 'email', 'ativo'];
        $sort = in_array($request->input('sort'), $colunasOrdenaveis, true) ? $request->input('sort') : 'nome';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $direction);
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();

        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'senha' => ['required', 'string', 'min:8'],
            'perfis' => ['required', 'array', 'min:1'],
            'perfis.*' => ['exists:roles,name'],
        ]);

        $usuario = User::create([
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'senha_hash' => Hash::make($dados['senha']),
            'ativo' => true,
            // Usuário novo sempre troca a senha inicial no primeiro acesso.
            'deve_alterar_senha' => true,
        ]);

        $usuario->syncRoles($dados['perfis']);

        return redirect()->route('admin.users.index')->with('status', 'Usuário criado com sucesso.');
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();

        return view('admin.users.edit', ['usuario' => $user, 'roles' => $roles]);
    }

    public function update(Request $request, User $user)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,'.$user->id],
            'ativo' => ['nullable', 'boolean'],
            'perfis' => ['required', 'array', 'min:1'],
            'perfis.*' => ['exists:roles,name'],
            // Ponto 2: campos opcionais — admin pode deixar em branco se só
            // quiser marcar "forçar troca no próximo login" sem definir a senha.
            'nova_senha' => ['nullable', 'string', 'min:8'],
            'forcar_troca_senha' => ['nullable', 'boolean'],
        ]);

        $atualizacao = [
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'ativo' => $request->boolean('ativo'),
        ];

        if (! empty($dados['nova_senha'])) {
            $atualizacao['senha_hash'] = Hash::make($dados['nova_senha']);
        }

        // Se o admin definiu uma nova senha OU marcou a caixa explicitamente,
        // o usuário é obrigado a trocar no próximo login.
        if (! empty($dados['nova_senha']) || $request->boolean('forcar_troca_senha')) {
            $atualizacao['deve_alterar_senha'] = true;
        }

        $user->update($atualizacao);
        $user->syncRoles($dados['perfis']);

        return redirect()->route('admin.users.index')->with('status', 'Usuário atualizado com sucesso.');
    }
}
