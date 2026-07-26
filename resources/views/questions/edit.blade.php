@extends('layouts.app')

@section('titulo', 'Editar pergunta')

@section('conteudo')
    <h2>Editar pergunta {{ $pergunta->codigo }}</h2>
    <form method="POST" action="{{ route('questions.update', $pergunta) }}">
        @csrf
        @method('PUT')
        @include('questions._form')
    </form>
@endsection
