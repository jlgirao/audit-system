<?php

namespace Database\Seeders;

use App\Models\AuditQuestion;
use Illuminate\Database\Seeder;

class AuditQuestionsSeeder extends Seeder
{
    /**
     * Perguntas de EXEMPLO — substitua pelo conteúdo real do seu Excel de
     * auditoria. Cada pergunta agora tem 4 colunas de saída configuráveis:
     * Resposta (Sim/Não/Não aplicável), Observações, Arquivo da Evidência
     * e Parecer.
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
                'coluna_ha_evidencia' => 'D',
                'coluna_observacoes' => 'E',
                'coluna_evidencia' => 'F',
                'coluna_parecer' => 'G',
                'ordem' => 1,
            ],
            [
                'codigo' => 'Q-002',
                'texto_pergunta' => 'Existe registro de treinamento anual de compliance para novos funcionários?',
                'categoria' => 'Compliance',
                'aba_excel' => 'Compliance',
                'linha_excel' => 8,
                'coluna_ha_evidencia' => 'D',
                'coluna_observacoes' => 'E',
                'coluna_evidencia' => 'F',
                'coluna_parecer' => 'G',
                'ordem' => 2,
            ],
            [
                'codigo' => 'Q-003',
                'texto_pergunta' => 'Os contratos com fornecedores acima de R$ 50 mil possuem cláusula de confidencialidade?',
                'categoria' => 'Fornecedores',
                'aba_excel' => 'Fornecedores',
                'linha_excel' => 3,
                'coluna_ha_evidencia' => 'D',
                'coluna_observacoes' => 'E',
                'coluna_evidencia' => 'F',
                'coluna_parecer' => 'G',
                'ordem' => 3,
            ],
        ];

        foreach ($perguntas as $pergunta) {
            AuditQuestion::updateOrCreate(['codigo' => $pergunta['codigo']], $pergunta);
        }
    }
}
