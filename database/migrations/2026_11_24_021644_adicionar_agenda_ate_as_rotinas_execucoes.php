<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rotinas_execucoes', function (Blueprint $table) {
            $table->dateTime('agenda_ate')->nullable();
            $table->index(['id_empresa', 'agenda_ate'], 'rotinas_execucoes_empresa_agenda_index');
        });
    }

    public function down(): void
    {
        Schema::table('rotinas_execucoes', function (Blueprint $table) {
            $table->dropIndex('rotinas_execucoes_empresa_agenda_index');
            $table->dropColumn('agenda_ate');
        });
    }
};
