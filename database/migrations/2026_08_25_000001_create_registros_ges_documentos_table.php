<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_ges_documentos', function (Blueprint $table): void {
            $table->id('id_documento');
            $table->unsignedBigInteger('id_registro');
            $table->string('nombre_original');
            $table->string('nombre_archivo');
            $table->string('ruta_archivo');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('tamanio')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_actualizacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_ges_documentos');
    }
};
