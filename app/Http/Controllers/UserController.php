<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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

    public function index()
    {
        $usuarios = User::with('roles')->orderBy('nome')->paginate(20);

        return view('admin.users.index', compact('usuarios'));
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
