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

        <hr style="margin:20px 0;">
        <p style="font-size:13px; color:#666;">
            Preencha abaixo somente se quiser definir uma nova senha para este usuário.
            @if ($usuario->deve_alterar_senha)
                <br><strong>Este usuário já está com troca de senha pendente no próximo login.</strong>
            @endif
        </p>

        <label>Nova senha (opcional)</label>
        <input type="password" name="nova_senha" minlength="8" placeholder="Deixe em branco para não alterar">

        <label style="font-weight:normal;">
            <input type="checkbox" name="forcar_troca_senha" value="1" style="width:auto;">
            Forçar troca de senha no próximo login
        </label>

        <div style="display:flex; gap:8px;">
            <button type="submit">Salvar alterações</button>
            <a href="{{ route('admin.users.index') }}" class="btn" style="background:#57534e;">Cancelar</a>
        </div>
    </form>
@endsection
