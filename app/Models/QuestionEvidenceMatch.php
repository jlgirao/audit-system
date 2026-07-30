<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionEvidenceMatch extends Model
{
    public $timestamps = false;

    protected $table = 'question_evidence_match';

    protected $fillable = [
        'process_id',
        'question_id',
        'evidence_file_id',
        'origem',
        'score_confianca',
        'resposta_sugerida',
        'parecer_sugerido',
        'status',
        'revisado_por',
        'revisado_em',
        'criado_em',
    ];

    public function processo()
    {
        return $this->belongsTo(AuditProcess::class, 'process_id');
    }

    public function pergunta()
    {
        return $this->belongsTo(AuditQuestion::class, 'question_id');
    }

    public function evidencia()
    {
        return $this->belongsTo(EvidenceFile::class, 'evidence_file_id');
    }

    public function revisor()
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
