<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Usa SQL bruto em vez de ->change() para não depender do pacote
     * doctrine/dbal — mudança simples, não vale a dependência extra.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE output_files MODIFY caminho_arquivo VARCHAR(600) NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE output_files MODIFY caminho_arquivo VARCHAR(600) NOT NULL DEFAULT ''");
    }
};