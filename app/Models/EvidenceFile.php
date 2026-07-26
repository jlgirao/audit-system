<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvidenceFile extends Model
{
    protected $fillable = [
        'process_id',
        'nome_arquivo',
        'caminho_dropbox',
        'dropbox_rev',
        'tipo_arquivo',
        'content_hash',
        'texto_extraido',
        'embedding_vector',
        'origem_texto',
        'classificacao',
        'status_processamento',
        'erro_detalhe',
    ];

    protected function casts(): array
    {
        return [
            'embedding_vector' => 'array',
        ];
    }

    public function processo()
    {
        return $this->belongsTo(AuditProcess::class, 'process_id');
    }

    public function matches()
    {
        return $this->hasMany(QuestionEvidenceMatch::class, 'evidence_file_id');
    }
}
