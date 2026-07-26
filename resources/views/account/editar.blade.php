@extends('layouts.app')

@section('titulo', 'Minha conta')

@section('conteudo')
    <h2>{{ $forcado ? 'É necessário trocar sua senha' : 'Minha conta' }}</h2>

    @if ($forcado)
        <p style="color:#92400e;">
            Por segurança, você precisa definir uma nova senha antes de continuar usando o sistema.
        </p>
    @endif

    <form method="POST" action="{{ $forcado ? route('senha.forcar.atualizar') : route('conta.senha') }}">
        @csrf
        @method('PUT')

        @unless ($forcado)
            <label>Senha atual</label>
            <input type="password" name="senha_atual" required>
        @endunless

        <label>Nova senha</label>
        <input type="password" name="nova_senha" required minlength="8">

        <label>Confirmar nova senha</label>
        <input type="password" name="nova_senha_confirmation" required minlength="8">

        <button type="submit">Salvar nova senha</button>
    </form>
@endsection
