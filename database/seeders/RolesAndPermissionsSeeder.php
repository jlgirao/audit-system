<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissoes = [
            'gerenciar-usuarios',
            'gerenciar-perguntas',
            'configurar-ia',
            'configurar-dropbox',
            'ver-logs',
            'criar-processo',
            'ver-todos-processos',
            'excluir-processo',
            'preencher-respostas',
            'revisar-processo',
            'aprovar-processo',
            'concluir-processo', // NOVO: separado de aprovar, ver ponto 6
            'reabrir-processo',
            'ver-metricas-ia',
        ];

        foreach ($permissoes as $permissao) {
            Permission::firstOrCreate(['name' => $permissao]);
        }

        // Admin sempre acumula todas as permissões existentes, incluindo
        // as que forem criadas no futuro (rodar o seeder de novo já cobre).
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // Analista NÃO recebe aprovar-processo nem concluir-processo —
        // por isso essas opções não aparecem nem ficam disponíveis para
        // ele na tela de transição de status (ponto 6).
        $analista = Role::firstOrCreate(['name' => 'analista']);
        $analista->syncPermissions([
            'criar-processo',
            'preencher-respostas',
        ]);

        $auditor = Role::firstOrCreate(['name' => 'auditor']);
        $auditor->syncPermissions([
            'revisar-processo',
            'aprovar-processo',
            'concluir-processo',
            'reabrir-processo',
            'ver-metricas-ia', // auditor acompanha a qualidade das sugestões da IA
        ]);

        // Um usuário pode ter os dois perfis (analista + auditor) — as
        // permissões se acumulam automaticamente, sem lógica especial.
    }
}
