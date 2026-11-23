<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rotinas_acomp_nao_realizadas', function (Blueprint $table) {
            $table->foreignId('id_execucao')->primary()
                ->constrained('rotinas_execucoes', 'id_execucao')->noActionOnDelete();
            $table->string('caixa', 40);
            $table->dateTime('recebido_em');
            $table->foreignId('id_usuario_verificacao')->nullable()
                ->constrained('usuarios', 'id_usuario')->noActionOnDelete();
            $table->dateTime('verificado_em')->nullable();
            $table->text('acao')->nullable();
            $table->json('encaminhamentos')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rotinas_acomp_nao_realizadas');
    }
};
