<?php

namespace App\Http\Controllers;

use App\Models\AuditQuestion;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class AuditQuestionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'permission:gerenciar-perguntas',
        ];
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

    /**
     * Duplica a pergunta e já leva para a tela de edição da cópia — o
     * código precisa ser único, então geramos um provisório e deixamos
     * o usuário ajustar código/texto/linha antes de salvar de vez.
     */
    public function duplicar(AuditQuestion $question)
    {
        $copia = $question->replicate();
        $copia->codigo = $this->gerarCodigoDisponivel($question->codigo);
        $copia->ordem = $question->ordem + 1;
        $copia->save();

        return redirect()->route('questions.edit', $copia)
            ->with('status', 'Pergunta duplicada — ajuste o código, o texto e a linha antes de salvar.');
    }

    private function gerarCodigoDisponivel(string $codigoOriginal): string
    {
        $sufixo = 2;
        $novoCodigo = "{$codigoOriginal}-copia";

        while (AuditQuestion::where('codigo', $novoCodigo)->exists()) {
            $novoCodigo = "{$codigoOriginal}-copia{$sufixo}";
            $sufixo++;
        }

        return $novoCodigo;
    }

    private function validarDados(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:30', 'unique:audit_questions,codigo,'.$ignorarId],
            'texto_pergunta' => ['required', 'string'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'aba_excel' => ['required', 'string', 'max:100'],
            'linha_excel' => ['required', 'integer', 'min:1'],
            'coluna_ha_evidencia' => ['required', 'string', 'max:5'],
            'coluna_observacoes' => ['required', 'string', 'max:5'],
            'coluna_evidencia' => ['required', 'string', 'max:5'],
            'coluna_parecer' => ['required', 'string', 'max:5'],
            'ordem' => ['nullable', 'integer'],
            'ativo' => ['nullable', 'boolean'],
        ]);
    }
}
