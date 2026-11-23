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
        Schema::table('rotinas_execucoes', function (Blueprint $table) {
            $table->text('comentario_conclusao')->nullable();
            $table->string('foto_conclusao')->nullable();
            $table->text('justificativa_nao_realizada')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rotinas_execucoes', function (Blueprint $table) {
            $table->dropColumn(['comentario_conclusao', 'foto_conclusao', 'justificativa_nao_realizada']);
        });
    }
};
