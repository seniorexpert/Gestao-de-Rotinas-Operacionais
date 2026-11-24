<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['tarefas' => 'id_tarefa', 'ferramentas' => 'id_ferramenta', 'locais' => 'id_local'] as $catalogo => $chave) {
            Schema::create($catalogo.'_empresas', function (Blueprint $table) use ($catalogo, $chave) {
                $table->foreignId($chave)->constrained($catalogo, $chave)->cascadeOnDelete();
                $table->foreignId('id_empresa')->constrained('empresas', 'id_empresa');
                $table->primary([$chave, 'id_empresa']);
                $table->index(['id_empresa', $chave]);
            });
        }

        DB::table('tarefas_empresas')->insertUsing(['id_tarefa', 'id_empresa'], DB::table('rotinas')
            ->select('id_tarefa', 'id_empresa')->distinct());
        DB::table('locais_empresas')->insertUsing(['id_local', 'id_empresa'], DB::table('rotinas')->whereNotNull('id_local')
            ->select('id_local', 'id_empresa')->distinct());
        DB::table('ferramentas_empresas')->insertUsing(['id_ferramenta', 'id_empresa'], DB::table('tarefas as t')
            ->join('tarefas_empresas as unidade', 't.id_tarefa', '=', 'unidade.id_tarefa')->whereNotNull('t.id_ferramenta')
            ->select('t.id_ferramenta', 'unidade.id_empresa')->distinct());
        DB::table('ferramentas')->whereNull('status')->update(['status' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locais_empresas');
        Schema::dropIfExists('ferramentas_empresas');
        Schema::dropIfExists('tarefas_empresas');
    }
};
