<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvidenceFile extends Model
{
    use SoftDeletes;

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
        'status_ia',
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

    /**
     * Monta um link que abre o arquivo direto na visualização do Dropbox
     * (funciona no navegador para quem já está logado numa conta com
     * acesso à pasta) — não usa a API do Dropbox nem precisa de nenhuma
     * permissão extra no app, só reaproveita o caminho já sincronizado.
     */
    public function linkDropbox(): string
    {
        $diretorio = str_contains($this->caminho_dropbox, '/')
            ? substr($this->caminho_dropbox, 0, strrpos($this->caminho_dropbox, '/'))
            : '';

        $segmentos = array_filter(explode('/', $diretorio), fn ($s) => $s !== '');
        $diretorioCodificado = '/'.implode('/', array_map('rawurlencode', $segmentos));

        return 'https://www.dropbox.com/home'.$diretorioCodificado.'?preview='.rawurlencode($this->nome_arquivo);
    }
}
