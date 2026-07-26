<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['auth'];
    }

    /**
     * Tela normal de "minha conta" (usuário decide trocar a senha por
     * conta própria, exige a senha atual).
     */
    public function editar()
    {
        return view('account.editar', ['forcado' => false]);
    }

    public function atualizarSenha(Request $request)
    {
        $dados = $request->validate([
            'senha_atual' => ['required', 'string'],
            'nova_senha' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($dados['senha_atual'], $request->user()->senha_hash)) {
            return back()->withErrors(['senha_atual' => 'Senha atual incorreta.']);
        }

        $request->user()->update([
            'senha_hash' => Hash::make($dados['nova_senha']),
            'deve_alterar_senha' => false,
        ]);

        return redirect()->route('processes.index')->with('status', 'Senha alterada com sucesso.');
    }

    /**
     * Ponto 2: tela de troca OBRIGATÓRIA, exibida quando o admin marcou
     * deve_alterar_senha = true. Não pede a senha atual (o usuário pode
     * ter recebido uma senha temporária do admin e não soubermos qual é
     * do lado do usuário além do que o admin definiu).
     */
    public function editarForcado()
    {
        abort_unless(Auth::user()->deve_alterar_senha, 404);

        return view('account.editar', ['forcado' => true]);
    }

    public function atualizarSenhaForcado(Request $request)
    {
        abort_unless($request->user()->deve_alterar_senha, 404);

        $dados = $request->validate([
            'nova_senha' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update([
            'senha_hash' => Hash::make($dados['nova_senha']),
            'deve_alterar_senha' => false,
        ]);

        return redirect()->route('processes.index')->with('status', 'Senha alterada com sucesso.');
    }
}
