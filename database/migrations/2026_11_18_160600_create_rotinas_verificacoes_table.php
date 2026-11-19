<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rotinas_verificacoes', function (Blueprint $table) {
            $table->foreignId('id_execucao')->primary()
                ->constrained('rotinas_execucoes', 'id_execucao')->noActionOnDelete();
            $table->foreignId('id_usuario_verificacao')
                ->constrained('usuarios', 'id_usuario')->noActionOnDelete();
            $table->text('acao');
            $table->dateTime('verificado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotinas_verificacoes');
    }
};
