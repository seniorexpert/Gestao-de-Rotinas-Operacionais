<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ferramentas', function (Blueprint $table) {
            $table->id('id_ferramenta');
            $table->string('ferramenta')->nullable();
            $table->boolean('status')->nullable();
            $table->timestamps();
        });

        Schema::create('locais', function (Blueprint $table) {
            $table->id('id_local');
            $table->string('local');
            $table->boolean('status');
            $table->timestamps();
        });

        Schema::create('tarefas', function (Blueprint $table) {
            $table->id('id_tarefa');
            $table->boolean('icone')->nullable();
            $table->string('tarefa')->nullable();
            $table->time('tempo_realizar_tarefa')->nullable();
            $table->text('descricao')->nullable();
            $table->unsignedBigInteger('id_ferramenta')->nullable();
            $table->string('documentacao')->nullable();
            $table->boolean('status');
            $table->timestamps();

            $table->foreign('id_ferramenta')
                ->references('id_ferramenta')
                ->on('ferramentas')
                ->onUpdate('no action')
                ->onDelete('no action');
        });

        Schema::create('rotinas', function (Blueprint $table) {
            $table->id('id_rotina');
            $table->boolean('status');
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_usuario');
            $table->string('dias_semanas');
            $table->time('horario');
            $table->unsignedBigInteger('id_tarefa');
            $table->string('morador')->nullable();
            $table->unsignedBigInteger('id_local')->nullable();
            $table->string('local')->nullable();
            $table->boolean('auxilio');
            $table->text('obs')->nullable();
            $table->timestamps();

            $table->foreign('id_tarefa')
                ->references('id_tarefa')
                ->on('tarefas')
                ->onUpdate('no action')
                ->onDelete('no action');

            $table->foreign('id_local')
                ->references('id_local')
                ->on('locais')
                ->onUpdate('no action')
                ->onDelete('no action');

            $table->foreign('id_empresa')
                ->references('id_empresa')
                ->on('empresas')
                ->onUpdate('no action')
                ->onDelete('no action');

            $table->foreign('id_usuario')
                ->references('id_usuario')
                ->on('usuarios')
                ->onUpdate('no action')
                ->onDelete('no action');
        });

        Schema::create('rotina_auxiliares', function (Blueprint $table) {
            $table->unsignedBigInteger('id_rotina');
            $table->unsignedBigInteger('id_usuario');

            // Impede repetir o mesmo auxiliar na mesma rotina.
            $table->primary(['id_rotina', 'id_usuario']);

            $table->foreign('id_rotina')
                ->references('id_rotina')
                ->on('rotinas')
                ->onUpdate('no action')
                ->onDelete('cascade');

            $table->foreign('id_usuario')
                ->references('id_usuario')
                ->on('usuarios')
                ->onUpdate('no action')
                ->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotina_auxiliares');
        Schema::dropIfExists('rotinas');
        Schema::dropIfExists('tarefas');
        Schema::dropIfExists('locais');
        Schema::dropIfExists('ferramentas');
    }
};