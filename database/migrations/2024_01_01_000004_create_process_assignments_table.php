<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('audit_processes');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('papel_no_processo', [
                'responsavel_principal', 'colaborador', 'auditor_revisor',
            ]);
            $table->foreignId('atribuido_por')->constrained('users');
            $table->timestamp('atribuido_em')->useCurrent();

            $table->unique(['process_id', 'user_id'], 'uq_process_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_assignments');
    }
};
