<?php

namespace App\Http\Controllers;

use App\Models\AuditQuestion;
use App\Services\ExcelExporter;
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

    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 50, 100], true)
            ? (int) $request->input('per_page')
            : 20;

        $perguntas = $this->queryFiltrada($request)->paginate($perPage)->withQueryString();

        $abasDisponiveis = AuditQuestion::query()
            ->select('aba_excel')
            ->distinct()
            ->orderBy('aba_excel')
            ->pluck('aba_excel');

        return view('questions.index', compact('perguntas', 'abasDisponiveis'));
    }

    /**
     * Exporta para Excel exatamente o resultado do filtro/busca/ordenação
     * ativos na tela.
     */
    public function exportar(Request $request)
    {
        $perguntas = $this->queryFiltrada($request)->get();

        $cabecalhos = [
            'Código', 'Pergunta', 'Contexto Adicional', 'Categoria', 'Aba Excel', 'Linha Excel',
            'Col. Resposta', 'Col. Observações', 'Col. Evidência', 'Col. Parecer',
            'Ordem', 'Ativo',
        ];

        $linhas = $perguntas->map(fn (AuditQuestion $pergunta) => [
            $pergunta->codigo,
            $pergunta->texto_pergunta,
            $pergunta->contexto_adicional,
            $pergunta->categoria,
            $pergunta->aba_excel,
            $pergunta->linha_excel,
            $pergunta->coluna_ha_evidencia,
            $pergunta->coluna_observacoes,
            $pergunta->coluna_evidencia,
            $pergunta->coluna_parecer,
            $pergunta->ordem,
            $pergunta->ativo ? 'Sim' : 'Não',
        ]);

        return ExcelExporter::gerar($cabecalhos, $linhas, 'perguntas.xlsx');
    }

    /**
     * Monta a query com busca (código/texto), filtro por aba e ordenação —
     * usada tanto pela listagem quanto pela exportação.
     */
    private function queryFiltrada(Request $request)
    {
        $query = AuditQuestion::query();

        if ($busca = $request->input('busca')) {
            $query->where(function ($q) use ($busca) {
                $q->where('codigo', 'like', "%{$busca}%")
                    ->orWhere('texto_pergunta', 'like', "%{$busca}%");
            });
        }

        if ($aba = $request->input('aba')) {
            $query->where('aba_excel', $aba);
        }

        $colunasOrdenaveis = ['codigo', 'texto_pergunta', 'aba_excel', 'linha_excel', 'ordem'];
        $sort = in_array($request->input('sort'), $colunasOrdenaveis, true) ? $request->input('sort') : 'ordem';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $direction);
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
            'contexto_adicional' => ['nullable', 'string'],
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
