<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConfig extends Model
{
    protected $table = 'ai_config';

    protected $primaryKey = 'chave';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['chave', 'valor', 'descricao', 'atualizado_em'];

    public static function get(string $chave, $default = null)
    {
        return static::find($chave)?->valor ?? $default;
    }

    public static function set(string $chave, string $valor, ?string $descricao = null): void
    {
        static::updateOrCreate(
            ['chave' => $chave],
            ['valor' => $valor, 'descricao' => $descricao, 'atualizado_em' => now()]
        );
    }
}
