<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rotinas_execucoes', function (Blueprint $table) {
            $table->unsignedInteger('duracao_prevista_segundos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rotinas_execucoes', function (Blueprint $table) {
            $table->dropColumn('duracao_prevista_segundos');
        });
    }
};