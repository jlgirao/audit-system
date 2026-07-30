<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessAnswer extends Model
{
    protected $fillable = [
        'process_id',
        'question_id',
        'ha_evidencia',
        'observacoes',
        'parecer',
        'preenchido_por',
        'preenchido_em',
    ];

    public function processo()
    {
        return $this->belongsTo(AuditProcess::class, 'process_id');
    }

    public function pergunta()
    {
        return $this->belongsTo(AuditQuestion::class, 'question_id');
    }

    public function preenchidoPor()
    {
        return $this->belongsTo(User::class, 'preenchido_por');
    }
}
