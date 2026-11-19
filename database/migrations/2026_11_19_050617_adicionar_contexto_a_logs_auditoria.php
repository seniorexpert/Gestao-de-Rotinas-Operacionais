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
        Schema::table('logs_auditoria', function (Blueprint $table) {
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_rotina')->nullable();
            $table->string('usuario_nome', 255)->nullable();
            $table->string('usuario_login', 100)->nullable();
            $table->string('rota', 128)->nullable();
            $table->string('metodo_http', 10)->nullable();
            $table->unsignedSmallInteger('status_http')->nullable();
            $table->string('resultado', 24)->nullable();

            $table->index(['id_empresa', 'id_log']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logs_auditoria', function (Blueprint $table) {
            $table->dropIndex(['id_empresa', 'id_log']);
            $table->dropColumn([
                'id_empresa', 'id_rotina', 'usuario_nome', 'usuario_login',
                'rota', 'metodo_http', 'status_http', 'resultado',
            ]);
        });
    }
};
