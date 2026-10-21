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
    Schema::create('empresa_usuario', function (Blueprint $table) {
        $table->foreignId('id_empresa')
            ->constrained('empresas', 'id_empresa')
            ->noActionOnDelete();

        $table->foreignId('id_usuario')
            ->constrained('usuarios', 'id_usuario')
            ->noActionOnDelete();

        $table->string('empresa_nome', 255);
        $table->timestamps();

        $table->primary(['id_empresa', 'id_usuario']);
    });
}

public function down(): void
{
    Schema::dropIfExists('empresa_usuario');
}
};
