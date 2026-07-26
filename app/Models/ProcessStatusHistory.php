<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'process_status_history';

    protected $fillable = [
        'process_id',
        'status_anterior',
        'status_novo',
        'comentario',
        'usuario_id',
        'criado_em',
    ];

    protected $casts = [
        'criado_em' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}