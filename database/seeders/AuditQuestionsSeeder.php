<?php

namespace Database\Seeders;

use App\Models\AuditQuestion;
use Illuminate\Database\Seeder;

class AuditQuestionsSeeder extends Seeder
{
    /**
     * Este seeder traz 3 perguntas de EXEMPLO só para você validar
     * a estrutura. Substitua pelo conteúdo real do seu Excel de
     * auditoria — o ideal é gerar este arquivo a partir de uma
     * exportação do template (uma linha por pergunta), não digitar
     * manualmente dezenas/centenas de perguntas aqui.
     */
    public function run(): void
    {
        $perguntas = [
            [
                'codigo' => 'Q-001',
                'texto_pergunta' => 'A empresa possui política formal de aprovação de despesas acima de R$ 10 mil?',
                'categoria' => 'Financeiro',
                'aba_excel' => 'Financeiro',
                'linha_excel' => 5,
                'coluna_resposta' => 'D',
                'coluna_evidencia' => 'E',
                'ordem' => 1,
            ],
            [
                'codigo' => 'Q-002',
                'texto_pergunta' => 'Existe registro de treinamento anual de compliance para novos funcionários?',
                'categoria' => 'Compliance',
                'aba_excel' => 'Compliance',
                'linha_excel' => 8,
                'coluna_resposta' => 'D',
                'coluna_evidencia' => 'E',
                'ordem' => 2,
            ],
            [
                'codigo' => 'Q-003',
                'texto_pergunta' => 'Os contratos com fornecedores acima de R$ 50 mil possuem cláusula de confidencialidade?',
                'categoria' => 'Fornecedores',
                'aba_excel' => 'Fornecedores',
                'linha_excel' => 3,
                'coluna_resposta' => 'D',
                'coluna_evidencia' => 'E',
                'ordem' => 3,
            ],
        ];

        foreach ($perguntas as $pergunta) {
            AuditQuestion::firstOrCreate(['codigo' => $pergunta['codigo']], $pergunta);
        }
    }
}
