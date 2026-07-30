@extends('layouts.app')

@section('titulo', 'Usuários')

@section('conteudo')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h2 style="margin:0;">Usuários</h2>
        <a class="btn" href="{{ route('admin.users.create') }}">+ Novo usuário</a>
    </div>

    <table>
        <thead>
        <tr><th>Nome</th><th>E-mail</th><th>Perfis</th><th>Ativo</th><th></th></tr>
        </thead>
        <tbody>
        @forelse ($usuarios as $usuario)
            <tr>
                <td>{{ $usuario->nome }}</td>
                <td>{{ $usuario->email }}</td>
                <td>{{ $usuario->roles->pluck('name')->map(fn($r) => ucfirst($r))->join(', ') }}</td>
                <td>{{ $usuario->ativo ? 'Sim' : 'Não' }}</td>
                <td><a href="{{ route('admin.users.edit', $usuario) }}" class="acao-btn acao-editar" title="Editar">✏️</a></td>
            </tr>
        @empty
            <tr><td colspan="5">Nenhum usuário cadastrado.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;">{{ $usuarios->links() }}</div>
@endsection
