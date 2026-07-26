<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
