<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCallLog extends Model
{
    public $timestamps = false;

    protected $table = 'ai_call_log';

    protected $fillable = [
        'process_id',
        'evidence_file_id',
        'question_id',
        'tipo_chamada',
        'sucesso',
        'duracao_ms',
        'erro_mensagem',
        'criado_em',
    ];

    protected function casts(): array
    {
        return [
            'sucesso' => 'boolean',
        ];
    }

    public function processo()
    {
        return $this->belongsTo(AuditProcess::class, 'process_id');
    }

    public function evidencia()
    {
        return $this->belongsTo(EvidenceFile::class, 'evidence_file_id');
    }

    public function pergunta()
    {
        return $this->belongsTo(AuditQuestion::class, 'question_id');
    }
}
