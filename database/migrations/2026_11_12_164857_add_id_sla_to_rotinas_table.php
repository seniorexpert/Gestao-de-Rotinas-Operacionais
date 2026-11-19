<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rotinas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_sla')->nullable();

            $table->foreign('id_sla', 'rotinas_id_sla_foreign')
                ->references('id_sla')
                ->on('slas')
                ->onUpdate('no action')
                ->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::table('rotinas', function (Blueprint $table) {
            $table->dropForeign('rotinas_id_sla_foreign');
            $table->dropColumn('id_sla');
        });
    }
};