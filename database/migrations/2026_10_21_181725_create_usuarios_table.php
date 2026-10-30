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
    Schema::create('usuarios', function (Blueprint $table) {
        $table->id('id_usuario');

        $table->foreignId('id_grupo_acesso')
            ->nullable()
            ->constrained('grupos_acesso', 'id_grupo_acesso')
            ->noActionOnDelete();

        $table->string('usuario', 255); // Nome da pessoa
        $table->string('username', 100)->unique(); // Login
        $table->string('grupo_acesso_nome', 255)->nullable();
        $table->string('password', 255);
        $table->boolean('status')->default(true);
        $table->rememberToken();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('usuarios');
}
};
