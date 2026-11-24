<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rotinas', function (Blueprint $table) {
            $table->uuid('grupo_rotina')->nullable()->index();
            $table->dateTime('excluida_em')->nullable();
        });

        $rotinasDiarias = DB::table('rotinas')->where('tipo_rotina', 'diaria');
        $grupos = (clone $rotinasDiarias)->distinct()->get(['id_empresa', 'id_usuario']);

        foreach ($grupos as $grupo) {
            (clone $rotinasDiarias)->where('id_empresa', $grupo->id_empresa)->where('id_usuario', $grupo->id_usuario)
                ->update(['grupo_rotina' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('rotinas', function (Blueprint $table) {
            $table->dropIndex(['grupo_rotina']);
            $table->dropColumn(['grupo_rotina', 'excluida_em']);
        });
    }
};
