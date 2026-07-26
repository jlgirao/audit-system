@extends('layouts.app')

@section('titulo', 'Nova pergunta')

@section('conteudo')
    <h2>Nova pergunta de auditoria</h2>
    <form method="POST" action="{{ route('questions.store') }}">
        @csrf
        @include('questions._form')
    </form>
@endsection
