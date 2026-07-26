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
            ]
        );

        $admin->assignRole('admin');

        $this->command->warn('Usuário admin criado: admin@empresa.com / trocar-esta-senha — troque a senha no primeiro acesso.');
    }
}
