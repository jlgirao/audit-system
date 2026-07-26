<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'nome',
        'email',
        'senha_hash',
        'ativo',
        'deve_alterar_senha',
    ];

    protected $hidden = [
        'senha_hash',
        'remember_token',
    ];

    // Laravel espera o campo "password" para autenticação;
    // mapeamos para a coluna senha_hash usada no schema do projeto.
    public function getAuthPassword()
    {
        return $this->senha_hash;
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'deve_alterar_senha' => 'boolean',
        ];
    }

    public function processosAtribuidos()
    {
        return $this->belongsToMany(AuditProcess::class, 'process_assignments', 'user_id', 'process_id')
            ->withPivot('papel_no_processo', 'atribuido_por', 'atribuido_em');
    }

    public function processosCriados()
    {
        return $this->hasMany(AuditProcess::class, 'criado_por');
    }

    /**
     * Ponto 3: admin sempre vê/gerencia tudo. Centralizamos aqui a
     * checagem em vez de espalhar hasRole('admin') pelos controllers.
     */
    public function ehAdmin(): bool
    {
        return $this->hasRole('admin');
    }
}
