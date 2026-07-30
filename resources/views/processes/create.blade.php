@extends('layouts.app')

@section('titulo', 'Novo processo')

@section('conteudo')
    <h2>Novo processo de auditoria</h2>

    <form method="POST" action="{{ route('processes.store') }}">
        @csrf

        <label>Nome do processo</label>
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

        <button type="submit">Criar processo</button>
    </form>
@endsection
