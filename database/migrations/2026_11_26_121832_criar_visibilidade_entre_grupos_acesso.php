<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_acesso_visibilidade', function (Blueprint $table): void {
            $table->foreignId('id_grupo_origem')->constrained('grupos_acesso', 'id_grupo_acesso')->noActionOnDelete();
            $table->foreignId('id_grupo_visivel')->constrained('grupos_acesso', 'id_grupo_acesso')->noActionOnDelete();
            $table->primary(['id_grupo_origem', 'id_grupo_visivel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos_acesso_visibilidade');
    }
};
