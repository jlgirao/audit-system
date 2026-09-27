<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

use App\Http\Middleware\ForcarTrocaSenha;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Necessário para rodar atrás de um proxy reverso (Nginx Proxy
        // Manager) — sem isso, o Laravel não sabe que a conexão original
        // do visitante é HTTPS, e gera links/formulários como "http://"
        // mesmo com o certificado certo por fora. "at: '*'" é seguro aqui
        // porque os containers não são acessíveis diretamente de fora, só
        // através do proxy.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Ponto 2: qualquer usuário autenticado com deve_alterar_senha=true
        // é redirecionado para a troca obrigatória antes de acessar
        // qualquer outra tela (ver App\Http\Middleware\ForcarTrocaSenha).
        $middleware->appendToGroup('web', [
            ForcarTrocaSenha::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
