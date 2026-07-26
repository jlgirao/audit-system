<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>@yield('titulo', 'Sistema de Auditoria')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f4; color: #222; }
        header { background: #1f2937; color: #fff; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-right: 16px; }
        main { padding: 24px; max-width: 1100px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; font-size: 14px; }
        th { background: #f0f0ee; }
        .badge { padding: 2px 8px; border-radius: 4px; font-size: 12px; }
        .badge-criado { background: #e5e7eb; }
        .badge-em_analise { background: #dbeafe; color: #1e40af; }
        .badge-em_revisao { background: #fef3c7; color: #92400e; }
        .badge-devolvido { background: #fee2e2; color: #991b1b; }
        .badge-aprovado, .badge-concluido { background: #dcfce7; color: #166534; }
        .badge-reaberto { background: #ede9fe; color: #5b21b6; }
        .status-msg { background: #dcfce7; padding: 10px 16px; border-radius: 4px; margin-bottom: 16px; }
        .erro-msg { background: #fee2e2; padding: 10px 16px; border-radius: 4px; margin-bottom: 16px; }
        form label { display: block; margin-top: 12px; font-weight: bold; font-size: 13px; }
        form input, form select, form textarea { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        button, .btn { background: #1f2937; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 12px; }
    </style>
</head>
<body>
<header>
    <div>
        <a href="{{ route('processes.index') }}">Processos</a>
        @can('gerenciar-perguntas')
            <a href="{{ route('questions.index') }}">Perguntas</a>
        @endcan
        @can('gerenciar-usuarios')
            <a href="{{ route('admin.users.index') }}">Usuários</a>
        @endcan
    </div>
    <div>
        @auth
            {{ auth()->user()->nome }}
            <form action="{{ route('logout') }}" method="POST" style="display:inline">
                @csrf
                <button type="submit" style="margin-top:0;">Sair</button>
            </form>
        @endauth
    </div>
</header>
<main>
    @if (session('status'))
        <div class="status-msg">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="erro-msg">
            <ul style="margin:0; padding-left:18px;">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('conteudo')
</main>
</body>
</html>
