<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AuditProcess extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'nome',
        'status',
        'dropbox_folder_path',
        'dropbox_cursor',
        'tem_arquivos_novos',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'tem_arquivos_novos' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AuditProcess $process) {
            if (empty($process->uuid)) {
                $process->uuid = (string) Str::uuid();
            }
        });
    }

    public function criador()
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function responsaveis()
    {
        return $this->belongsToMany(User::class, 'process_assignments', 'process_id', 'user_id')
            ->withPivot('papel_no_processo', 'atribuido_por', 'atribuido_em');
    }

    public function historicoStatus()
    {
        return $this->hasMany(ProcessStatusHistory::class, 'process_id')->orderByDesc('criado_em');
    }

    public function evidencias()
    {
        return $this->hasMany(EvidenceFile::class, 'process_id');
    }

    public function respostas()
    {
        return $this->hasMany(ProcessAnswer::class, 'process_id');
    }

    public function arquivosSaida()
    {
        return $this->hasMany(OutputFile::class, 'process_id')->orderByDesc('versao');
    }

    /**
     * Registra transição de status com histórico. Regras de fluxo
     * (quem pode transicionar de qual estado para qual) ficam na
     * camada de Controller/Policy, não no Model.
     */
    public function transicionarStatus(string $novoStatus, int $usuarioId, ?string $comentario = null): void
    {
        $this->historicoStatus()->create([
            'status_anterior' => $this->status,
            'status_novo' => $novoStatus,
            'comentario' => $comentario,
            'usuario_id' => $usuarioId,
        ]);

        $this->update(['status' => $novoStatus]);
    }
}
