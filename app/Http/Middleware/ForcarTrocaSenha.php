<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcarTrocaSenha
{
    /**
     * Rotas que precisam continuar acessíveis mesmo com a senha pendente
     * de troca (senão o usuário fica preso sem conseguir nem trocar a
     * senha nem sair do sistema).
     */
    private array $rotasPermitidas = [
        'senha.forcar.editar',
        'senha.forcar.atualizar',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && $usuario->deve_alterar_senha && ! $request->routeIs(...$this->rotasPermitidas)) {
            return redirect()->route('senha.forcar.editar');
        }

        return $next($request);
    }
}
