<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AuditProcess extends Model
{
    use SoftDeletes;

    /**
     * Mapa de status finais -> permissão exigida para transicionar para eles.
     * Status que não aparecem aqui (criado, em_analise, em_revisao) podem
     * ser definidos por qualquer responsável atribuído ao processo.
     * Admin tem todas as permissões (ver RolesAndPermissionsSeeder), então
     * sempre pode transicionar para qualquer status (ponto 5).
     */
    public const PERMISSAO_POR_STATUS = [
        'devolvido' => 'revisar-processo',
        'aprovado' => 'aprovar-processo',
        'concluido' => 'concluir-processo',
        'reaberto' => 'reabrir-processo',
    ];

    protected $fillable = [
        'uuid',
        'nome',
        'descricao',
        'status',
        'dropbox_folder_path',
        'dropbox_cursor',
        'tem_arquivos_novos',
        'status_sincronizacao',
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

    public function chamadasIa()
    {
        return $this->hasMany(AiCallLog::class, 'process_id');
    }

    /**
     * Ponto 4: "cada user pode editar o seu" — considera-se "seu" qualquer
     * processo onde o usuário está atribuído como responsável (principal
     * ou colaborador). Admin pode editar qualquer processo.
     */
    public function podeSerEditadoPor(User $user): bool
    {
        if ($user->ehAdmin()) {
            return true;
        }

        return $this->responsaveis()->where('users.id', $user->id)->exists();
    }

    /**
     * Ponto 5 e 6: retorna a lista de status para os quais o usuário tem
     * permissão de transicionar este processo. Usado tanto para validar no
     * controller quanto para montar as opções disponíveis na tela.
     */
    public function statusDisponiveisPara(User $user): array
    {
        $todos = ['em_analise', 'em_revisao', 'devolvido', 'aprovado', 'concluido', 'reaberto'];

        return array_values(array_filter($todos, function (string $status) use ($user) {
            $permissao = self::PERMISSAO_POR_STATUS[$status] ?? null;

            // Sem permissão associada (em_analise, em_revisao): liberado
            // para qualquer responsável atribuído (ou admin).
            if ($permissao === null) {
                return $user->ehAdmin() || $this->responsaveis()->where('users.id', $user->id)->exists();
            }

            return $user->can($permissao);
        }));
    }

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
