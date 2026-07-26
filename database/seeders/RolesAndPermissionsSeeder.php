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
            'preencher-respostas',
            'revisar-processo',
            'aprovar-processo',
            'reabrir-processo',
        ];

        foreach ($permissoes as $permissao) {
            Permission::firstOrCreate(['name' => $permissao]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        $analista = Role::firstOrCreate(['name' => 'analista']);
        $analista->syncPermissions([
            'criar-processo',
            'preencher-respostas',
        ]);

        $auditor = Role::firstOrCreate(['name' => 'auditor']);
        $auditor->syncPermissions([
            'revisar-processo',
            'aprovar-processo',
            'reabrir-processo',
        ]);

        // Nota: como um usuário pode ter os dois perfis (analista + auditor),
        // basta atribuir ambos os papéis a ele no cadastro de usuário —
        // as permissões se acumulam automaticamente (ver seção 2.1 do
        // documento técnico).
    }
}
