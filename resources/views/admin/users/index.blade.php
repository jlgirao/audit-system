@extends('layouts.app')

@section('titulo', 'Usuários')

@section('conteudo')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h2 style="margin:0;">Usuários</h2>
        <a class="btn" href="{{ route('admin.users.create') }}">+ Novo usuário</a>
    </div>

    <form method="GET" style="display:flex; gap:8px; margin-bottom:16px;">
        <input type="text" name="busca" placeholder="Buscar por nome ou e-mail" value="{{ request('busca') }}" style="flex:1;">
        <select name="perfil">
            <option value="">Todos os perfis</option>
            @foreach ($rolesDisponiveis as $role)
                <option value="{{ $role->name }}" @selected(request('perfil') === $role->name)>{{ ucfirst($role->name) }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">Todos</option>
            <option value="ativo" @selected(request('status') === 'ativo')>Ativos</option>
            <option value="inativo" @selected(request('status') === 'inativo')>Inativos</option>
        </select>
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.users.exportar') }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}"
            title="Exportar para Excel" style="display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; background:#166534; color:#fff; border-radius:4px; text-decoration:none; font-size:16px; margin-top:0;">⬇</a>
    </form>

    @include('partials._per_page_selector')

    <table>
        <thead>
        <tr>
            @include('partials._sort_header', ['coluna' => 'nome', 'label' => 'Nome'])
            @include('partials._sort_header', ['coluna' => 'email', 'label' => 'E-mail'])
            <th>Perfis</th>
            @include('partials._sort_header', ['coluna' => 'ativo', 'label' => 'Ativo'])
            <th></th>
        </tr>
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

    <div style="margin-top:16px;">{{ $usuarios->links('partials._pagination') }}</div>
@endsection
