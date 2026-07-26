<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@empresa.com'],
            [
                'nome' => 'Administrador',
                'senha_hash' => Hash::make('trocar-esta-senha'),
                'ativo' => true,
                'deve_alterar_senha' => true,
            ]
        );

        $admin->assignRole('admin');

        $this->command->warn('Usuário admin criado: admin@empresa.com / trocar-esta-senha — a troca de senha será exigida no primeiro login.');
    }
}
