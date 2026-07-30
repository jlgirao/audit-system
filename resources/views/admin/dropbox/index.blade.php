@extends('layouts.app')

@section('titulo', 'Conexão com o Dropbox')

@section('conteudo')
    <h2>Conexão com o Dropbox</h2>

    @if ($config->conectado())
        <p style="color:#166534;">
            ✅ Conectado
            @if ($config->conectado_em)
                em {{ $config->conectado_em->format('d/m/Y H:i') }}
            @endif
        </p>
        <form method="POST" action="{{ route('admin.dropbox.desconectar') }}" onsubmit="return confirm('Desconectar o Dropbox? A sincronização de todos os processos vai parar até reconectar.');">
            @csrf
            @method('DELETE')
            <button type="submit" style="background:#991b1b;">Desconectar</button>
        </form>
    @else
        <p style="color:#666;">Nenhuma conta do Dropbox conectada ainda.</p>
        <a class="btn" href="{{ route('admin.dropbox.conectar') }}">Conectar ao Dropbox</a>
    @endif

    <h3 style="margin-top:32px;">Como configurar (uma vez só)</h3>
    <ol style="font-size:13px; color:#444;">
        <li>Crie um app em <a href="https://www.dropbox.com/developers/apps" target="_blank">dropbox.com/developers/apps</a> (tipo "Scoped access", acesso "Full Dropbox" ou a uma pasta específica).</li>
        <li>Nas permissões (aba "Permissions") do app, habilite: <code>files.metadata.read</code>, <code>files.content.read</code>.</li>
        <li>Em "Redirect URIs", adicione: <code>{{ config('services.dropbox.redirect') }}</code></li>
        <li>Copie o "App key" e "App secret" para o arquivo <code>.env</code> do servidor (<code>DROPBOX_APP_KEY</code>, <code>DROPBOX_APP_SECRET</code>) e rode <code>php artisan config:clear</code>.</li>
        <li>Volte nesta tela e clique em "Conectar ao Dropbox".</li>
    </ol>
@endsection
