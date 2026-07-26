<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('audit_processes');
            $table->string('status_anterior', 30)->nullable();
            $table->string('status_novo', 30);
            $table->text('comentario')->nullable();
            $table->foreignId('usuario_id')->constrained('users');
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_status_history');
    }
};
