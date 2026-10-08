<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentacion_categorias', function (Blueprint $table): void {
            $table->id('id_categoria');
            $table->string('nombre', 100)->unique();
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('id_categoria_padre')->nullable();
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_actualizacion')->nullable();
            $table->index('id_categoria_padre');
        });

        Schema::table('documentos_generales', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_categoria')->nullable()->after('id_usuario');
            $table->text('descripcion')->nullable()->after('nombre');
            $table->json('etiquetas')->nullable()->after('descripcion');
            $table->string('estado', 20)->default('pendiente')->after('etiquetas');
            $table->unsignedBigInteger('id_paciente')->nullable()->after('estado');
            $table->unsignedBigInteger('id_registro')->nullable()->after('id_paciente');
            $table->unsignedBigInteger('id_usuario_revisor')->nullable()->after('id_registro');
            $table->timestamp('fecha_revision')->nullable()->after('id_usuario_revisor');
            $table->index(['id_categoria', 'estado']);
            $table->index('id_paciente');
            $table->index('id_registro');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_generales', function (Blueprint $table): void {
            $table->dropIndex(['id_categoria', 'estado']);
            $table->dropIndex(['id_paciente']);
            $table->dropIndex(['id_registro']);
            $table->dropColumn([
                'id_categoria',
                'descripcion',
                'etiquetas',
                'estado',
                'id_paciente',
                'id_registro',
                'id_usuario_revisor',
                'fecha_revision',
            ]);
        });

        Schema::dropIfExists('documentacion_categorias');
    }
};
