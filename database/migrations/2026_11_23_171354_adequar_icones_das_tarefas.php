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
        if (in_array(Schema::getColumnType('tarefas', 'icone'), ['bit', 'bool', 'boolean', 'tinyint', 'tinyint(1)'], true)) {
            Schema::table('tarefas', function (Blueprint $table) {
                $table->string('icone', 64)->nullable()->change();
            });
        }

        if (! Schema::hasColumn('tarefas', 'cor_icone')) {
            Schema::table('tarefas', function (Blueprint $table) {
                $table->string('cor_icone', 20)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Preserva os ícones e as cores cadastrados ao reverter a aplicação.
    }
};
