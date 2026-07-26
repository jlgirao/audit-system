<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:gerenciar-usuarios']);
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
        ]);

        // Um usuário pode acumular múltiplos perfis (ex: analista + auditor).
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
        ]);

        $user->update([
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'ativo' => $request->boolean('ativo'),
        ]);

        $user->syncRoles($dados['perfis']);

        return redirect()->route('admin.users.index')->with('status', 'Usuário atualizado com sucesso.');
    }
}
