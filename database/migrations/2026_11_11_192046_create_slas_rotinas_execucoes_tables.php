<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slas', function (Blueprint $table) {
            $table->id('id_sla');
            $table->string('sla');
            $table->text('descricao')->nullable();
            $table->unsignedInteger('tempo_limite_minutos');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('rotinas_execucoes', function (Blueprint $table) {
            $table->id('id_execucao');

            $table->unsignedBigInteger('id_rotina');
            $table->unsignedBigInteger('id_sla');
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_usuario_responsavel');
            $table->unsignedBigInteger('id_usuario_conclusao')->nullable();

            // Data e hora completas, inclusive para prazos após meia-noite.
            $table->dateTime('previsto_em');
            $table->dateTime('prazo_em');
            $table->dateTime('iniciado_em')->nullable();
            $table->dateTime('concluido_em')->nullable();

            // Cópia do tempo aplicado, preservando o histórico
            // mesmo que o cadastro do SLA seja alterado.
            $table->unsignedInteger('tempo_limite_minutos');

            // Valores previstos:
            // pendente, em_andamento, concluida, cancelada.
            $table->string('status', 20)->default('pendente');

            $table->text('justificativa')->nullable();
            $table->text('obs')->nullable();

            $table->timestamps();

            $table->foreign('id_rotina')
                ->references('id_rotina')
                ->on('rotinas')
                ->onUpdate('no action')
                ->onDelete('no action');

            $table->foreign('id_sla')
                ->references('id_sla')
                ->on('slas')
                ->onUpdate('no action')
                ->onDelete('no action');

            $table->foreign('id_empresa')
                ->references('id_empresa')
                ->on('empresas')
                ->onUpdate('no action')
                ->onDelete('no action');

            $table->foreign('id_usuario_responsavel')
                ->references('id_usuario')
                ->on('usuarios')
                ->onUpdate('no action')
                ->onDelete('no action');

            $table->foreign('id_usuario_conclusao')
                ->references('id_usuario')
                ->on('usuarios')
                ->onUpdate('no action')
                ->onDelete('no action');

            // Impede duplicar a mesma rotina no mesmo horário previsto.
            $table->unique(
                ['id_rotina', 'previsto_em'],
                'rotinas_execucoes_rotina_previsto_unique'
            );

            // Consultas por unidade e período.
            $table->index(
                ['id_empresa', 'previsto_em'],
                'rotinas_execucoes_empresa_previsto_index'
            );

            // Consultas de pendências por responsável.
            $table->index(
                ['id_usuario_responsavel', 'status'],
                'rotinas_execucoes_responsavel_status_index'
            );

            // Consultas de vencimentos.
            $table->index(
                ['status', 'prazo_em'],
                'rotinas_execucoes_status_prazo_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotinas_execucoes');
        Schema::dropIfExists('slas');
    }
};