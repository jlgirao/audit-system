<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DropboxConfig extends Model
{
    protected $table = 'dropbox_config';

    protected $fillable = [
        'access_token',
        'refresh_token',
        'token_expira_em',
        'conta_email',
        'conectado_por',
        'conectado_em',
    ];

    protected function casts(): array
    {
        return [
            // Tokens nunca ficam em texto puro no banco.
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expira_em' => 'datetime',
            'conectado_em' => 'datetime',
        ];
    }

    /**
     * Configuração é um singleton (uma única conexão Dropbox para todo
     * o sistema, gerenciada pelo admin). Sempre usa o registro id=1.
     */
    public static function atual(): self
    {
        return static::firstOrNew(['id' => 1]);
    }

    public function conectado(): bool
    {
        return ! empty($this->refresh_token);
    }

    public function tokenExpirado(): bool
    {
        return $this->token_expira_em === null || now()->greaterThanOrEqualTo($this->token_expira_em);
    }
}
