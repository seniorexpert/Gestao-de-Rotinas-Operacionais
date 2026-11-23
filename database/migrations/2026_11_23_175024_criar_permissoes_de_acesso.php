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
        foreach (['grupos_acesso' => 'id_grupo_acesso', 'usuarios' => 'id_usuario'] as $origem => $chave) {
            Schema::create('permissoes_'.$origem, function (Blueprint $table) use ($origem, $chave) {
                $table->foreignId($chave)->constrained($origem, $chave)->cascadeOnDelete();
                $table->string('modulo', 40);
                $table->unsignedTinyInteger('nivel');
                $table->primary([$chave, 'modulo']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissoes_usuarios');
        Schema::dropIfExists('permissoes_grupos_acesso');
    }
};
