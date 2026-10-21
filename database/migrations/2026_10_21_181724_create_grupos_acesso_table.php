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
    Schema::create('grupos_acesso', function (Blueprint $table) {
        $table->id('id_grupo_acesso');
        $table->string('grupo_acesso', 255);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('grupos_acesso');
}
};
