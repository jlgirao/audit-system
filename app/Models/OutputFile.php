<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutputFile extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'process_id',
        'versao',
        'status',
        'erro_detalhe',
        'caminho_arquivo',
        'caminho_dropbox',
        'gerado_por',
        'gerado_em',
    ];

    public function processo()
    {
        return $this->belongsTo(AuditProcess::class, 'process_id');
    }

    public function geradoPor()
    {
        return $this->belongsTo(User::class, 'gerado_por');
    }
}
