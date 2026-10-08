<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_ges_patologias', function (Blueprint $table): void {
            $table->id('id_registro_patologia');
            $table->unsignedBigInteger('id_registro');
            $table->unsignedBigInteger('id_patologia');
            $table->string('tipo', 50)->default('complicacion');
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_actualizacion')->nullable();

            $table->unique(['id_registro', 'id_patologia']);
            $table->index('id_patologia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_ges_patologias');
    }
};
