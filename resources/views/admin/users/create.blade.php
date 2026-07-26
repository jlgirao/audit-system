@extends('layouts.app')

@section('titulo', 'Novo usuário')

@section('conteudo')
    <h2>Novo usuário</h2>
    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf

        <label>Nome</label>
        <input type="text" name="nome" value="{{ old('nome') }}" required>

        <label>E-mail</label>
        <input type="email" name="email" value="{{ old('email') }}" required>

        <label>Senha inicial</label>
        <input type="password" name="senha" required minlength="8">

        <label>Perfis (pode selecionar mais de um — ex: analista e auditor no mesmo usuário)</label>
        @foreach ($roles as $role)
            <label style="font-weight:normal;">
                <input type="checkbox" name="perfis[]" value="{{ $role->name }}" style="width:auto;">
                {{ ucfirst($role->name) }}
            </label>
        @endforeach

        <button type="submit">Criar usuário</button>
    </form>
@endsection
