@extends('layouts.app')

@section('titulo', 'Template do Excel de auditoria')

@section('conteudo')
    <h2>Template do Excel de auditoria</h2>

    @if ($existe)
        <p style="color:#166534;">✅ Template atual enviado em {{ $atualizadoEm }}</p>
    @else
        <p style="color:#991b1b;">⚠️ Nenhum template enviado ainda. A geração de Excel não vai funcionar até isso ser feito.</p>
    @endif

    <form method="POST" action="{{ route('admin.template.store') }}" enctype="multipart/form-data">
        @csrf
        <label>Arquivo do template (.xlsx)</label>
        <input type="file" name="template" accept=".xlsx" required>
        <button type="submit">Enviar template</button>
    </form>

    <div style="margin-top:24px; font-size:13px; color:#444;">
        <p><strong>Importante:</strong> os nomes das abas do arquivo enviado precisam bater exatamente
        com o campo "Aba no Excel" cadastrado em cada pergunta (tela <a href="{{ route('questions.index') }}">Perguntas</a>).
        Se uma aba estiver faltando ou com nome diferente, a geração do Excel falha e avisa qual aba está com problema.</p>
    </div>
@endsection
