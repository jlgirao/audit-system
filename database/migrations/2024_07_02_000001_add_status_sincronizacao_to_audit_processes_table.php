<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_processes', function (Blueprint $table) {
            // Status da sincronização com o Dropbox, marcado pelo próprio
            // job — cobre o período em que o job ainda está NA FILA
            // (aguardando outros jobs terminarem), antes mesmo de existir
            // qualquer evidence_files para o indicador de processamento
            // olhar.
            $table->enum('status_sincronizacao', ['nunca', 'na_fila', 'sincronizando', 'concluido', 'erro'])
                ->default('nunca')
                ->after('tem_arquivos_novos');
        });
    }

    public function down(): void
    {
        Schema::table('audit_processes', function (Blueprint $table) {
            $table->dropColumn('status_sincronizacao');
        });
    }
};
