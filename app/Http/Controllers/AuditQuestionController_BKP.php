<?php

namespace App\Http\Controllers;

use App\Models\AuditQuestion;
use Illuminate\Http\Request;

class AuditQuestionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:gerenciar-perguntas']);
    }

    public function index()
    {
        $perguntas = AuditQuestion::orderBy('ordem')->paginate(20);

        return view('questions.index', compact('perguntas'));
    }

    public function create()
    {
        return view('questions.create');
    }

    public function store(Request $request)
    {
        $dados = $this->validarDados($request);
        AuditQuestion::create($dados);

        return redirect()->route('questions.index')->with('status', 'Pergunta criada com sucesso.');
    }

    public function edit(AuditQuestion $question)
    {
        return view('questions.edit', ['pergunta' => $question]);
    }

    public function update(Request $request, AuditQuestion $question)
    {
        $dados = $this->validarDados($request, $question->id);
        $question->update($dados);

        return redirect()->route('questions.index')->with('status', 'Pergunta atualizada com sucesso.');
    }

    public function destroy(AuditQuestion $question)
    {
        $question->delete();

        return redirect()->route('questions.index')->with('status', 'Pergunta removida.');
    }

    private function validarDados(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:30', 'unique:audit_questions,codigo,'.$ignorarId],
            'texto_pergunta' => ['required', 'string'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'aba_excel' => ['required', 'string', 'max:100'],
            'linha_excel' => ['required', 'integer', 'min:1'],
            'coluna_resposta' => ['required', 'string', 'max:5'],
            'coluna_evidencia' => ['required', 'string', 'max:5'],
            'ordem' => ['nullable', 'integer'],
            'ativo' => ['nullable', 'boolean'],
        ]);
    }
}
