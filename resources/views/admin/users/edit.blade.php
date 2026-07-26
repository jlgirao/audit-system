@extends('layouts.app')

@section('titulo', 'Editar usuário')

@section('conteudo')
    <h2>Editar usuário</h2>
    <form method="POST" action="{{ route('admin.users.update', $usuario) }}">
        @csrf
        @method('PUT')

        <label>Nome</label>
        <input type="text" name="nome" value="{{ old('nome', $usuario->nome) }}" required>

        <label>E-mail</label>
        <input type="email" name="email" value="{{ old('email', $usuario->email) }}" required>

        <label style="font-weight:normal;">
            <input type="checkbox" name="ativo" value="1" @checked($usuario->ativo) style="width:auto;">
            Usuário ativo
        </label>

        <label>Perfis (pode selecionar mais de um — ex: analista e auditor no mesmo usuário)</label>
        @foreach ($roles as $role)
            <label style="font-weight:normal;">
                <input type="checkbox" name="perfis[]" value="{{ $role->name }}"
                    @checked($usuario->roles->pluck('name')->contains($role->name)) style="width:auto;">
                {{ ucfirst($role->name) }}
            </label>
        @endforeach

        <button type="submit">Salvar alterações</button>
    </form>
@endsection
