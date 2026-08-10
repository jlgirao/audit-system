<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_questions', function (Blueprint $table) {
            // Texto livre, opcional, com orientações extras para a IA
            // interpretar a pergunta (ex: "procure o valor no contrato",
            // "considere a resposta da H-007", "consulte o Decreto-Lei
            // X", explicação de siglas, etc.). Entra no prompt de
            // confirmação do matching — não afeta a similaridade/embedding.
            $table->text('contexto_adicional')->nullable()->after('texto_pergunta');
        });
    }

    public function down(): void
    {
        Schema::table('audit_questions', function (Blueprint $table) {
            $table->dropColumn('contexto_adicional');
        });
    }
};
