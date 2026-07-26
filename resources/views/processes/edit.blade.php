@extends('layouts.app')

@section('titulo', 'Editar processo')

@section('conteudo')
    <h2>Editar processo</h2>

    <form method="POST" action="{{ route('processes.update', $processo) }}">
        @csrf
        @method('PUT')

        <label>Nome do processo</label>
        <input type="text" name="nome" value="{{ old('nome', $processo->nome) }}" required>

        <label>Descrição</label>
        <textarea name="descricao" rows="3">{{ old('descricao', $processo->descricao) }}</textarea>

        <label>Caminho da pasta no Dropbox</label>
        <input type="text" name="dropbox_folder_path" value="{{ old('dropbox_folder_path', $processo->dropbox_folder_path) }}" required>

        <label>Responsáveis (selecione um ou mais)</label>
        <select name="responsaveis[]" multiple size="6" required>
            @foreach ($usuarios as $usuario)
                <option value="{{ $usuario->id }}"
                    @selected($processo->responsaveis->pluck('id')->contains($usuario->id))>
                    {{ $usuario->nome }} ({{ $usuario->email }})
                </option>
            @endforeach
        </select>

        <label>Responsável principal</label>
        <select name="papel_principal" required>
            @foreach ($usuarios as $usuario)
                @php
                    $ehPrincipalAtual = $processo->responsaveis
                        ->firstWhere('id', $usuario->id)?->pivot?->papel_no_processo === 'responsavel_principal';
                @endphp
                <option value="{{ $usuario->id }}" @selected($ehPrincipalAtual)>{{ $usuario->nome }}</option>
            @endforeach
        </select>

        <button type="submit">Salvar alterações</button>
    </form>
@endsection
