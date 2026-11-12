<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs_auditoria', function (Blueprint $table) {
            $table->id('id_log');

            // Responsável pela ação. Nulo para processos automáticos.
            $table->unsignedBigInteger('id_usuario')->nullable();

            // criado, atualizado ou excluido
            $table->string('acao', 30);

            $table->string('tabela', 128);
            $table->string('registro_id', 255);

            // Valores armazenados em formato JSON.
            $table->longText('dados_anteriores')->nullable();
            $table->longText('dados_novos')->nullable();

            $table->text('descricao')->nullable();
            $table->string('origem', 30)->default('web');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->dateTime('created_at')->useCurrent();

            $table->index(['tabela', 'registro_id']);
            $table->index(['id_usuario', 'created_at']);
            $table->index(['acao', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_auditoria');
    }
};