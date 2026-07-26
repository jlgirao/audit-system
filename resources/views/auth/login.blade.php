<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Entrar - Sistema de Auditoria</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f4; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .caixa { background: #fff; padding: 32px; border-radius: 8px; width: 320px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
        label { display: block; margin-top: 12px; font-size: 13px; font-weight: bold; }
        input { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        button { width: 100%; background: #1f2937; color: #fff; border: none; padding: 10px; margin-top: 20px; border-radius: 4px; cursor: pointer; }
        .erro-msg { background: #fee2e2; padding: 8px 12px; border-radius: 4px; font-size: 13px; margin-top: 12px; }
    </style>
</head>
<body>
    <form class="caixa" action="{{ route('login.attempt') }}" method="POST">
        @csrf
        <h2 style="margin-top:0;">Sistema de Auditoria</h2>
        <label>E-mail</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        <label>Senha</label>
        <input type="password" name="password" required>
        <label><input type="checkbox" name="remember" style="width:auto;"> Manter conectado</label>
        <button type="submit">Entrar</button>
        @if ($errors->any())
            <div class="erro-msg">{{ $errors->first() }}</div>
        @endif
    </form>
</body>
</html>
