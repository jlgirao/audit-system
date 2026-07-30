<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditQuestion extends Model
{
    protected $fillable = [
        'codigo',
        'texto_pergunta',
        'categoria',
        'aba_excel',
        'linha_excel',
        'coluna_ha_evidencia',
        'coluna_observacoes',
        'coluna_evidencia',
        'coluna_parecer',
        'embedding_vector',
        'ativo',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'embedding_vector' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function answers()
    {
        return $this->hasMany(ProcessAnswer::class, 'question_id');
    }

    public function matches()
    {
        return $this->hasMany(QuestionEvidenceMatch::class, 'question_id');
    }
}
