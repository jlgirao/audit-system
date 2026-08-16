@extends('layouts.app')

@section('titulo', 'Novo projeto')

@section('conteudo')
    <h2>Novo projeto de auditoria</h2>

    <form method="POST" action="{{ route('processes.store') }}">
        @csrf

        <label>Nome do projeto</label>
        <input type="text" name="nome" value="{{ old('nome') }}" required>

        <label>Descrição</label>
        <textarea name="descricao" rows="3">{{ old('descricao') }}</textarea>

        @include('processes._dropbox_picker', ['valorAtual' => ''])

        <label>Responsáveis (selecione um ou mais)</label>
        <select name="responsaveis[]" multiple size="6" required>
            @foreach ($usuarios as $usuario)
                <option value="{{ $usuario->id }}">{{ $usuario->nome }} ({{ $usuario->email }})</option>
            @endforeach
        </select>

        <label>Responsável principal</label>
        <select name="papel_principal" required>
            @foreach ($usuarios as $usuario)
                <option value="{{ $usuario->id }}">{{ $usuario->nome }}</option>
            @endforeach
        </select>

        <div style="display:flex; gap:8px;">
            <button type="submit">Criar projeto</button>
            <a href="{{ route('processes.index') }}" class="btn" style="background:#57534e;">Cancelar</a>
        </div>
    </form>
@endsection
