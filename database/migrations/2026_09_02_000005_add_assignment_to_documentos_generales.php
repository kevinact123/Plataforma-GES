<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documentos_generales')) {
            return;
        }

        Schema::table('documentos_generales', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_usuario_asignado')->nullable()->after('id_usuario');
            $table->unsignedBigInteger('asignado_por')->nullable()->after('id_usuario_asignado');
            $table->timestamp('fecha_asignacion')->nullable()->after('asignado_por');
            $table->string('estado_asignacion', 20)->default('sin_asignar')->after('fecha_asignacion');
            $table->index(['id_usuario_asignado', 'estado_asignacion'], 'documentos_asignacion_usuario_estado_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('documentos_generales')) {
            return;
        }

        Schema::table('documentos_generales', function (Blueprint $table): void {
            $table->dropIndex('documentos_asignacion_usuario_estado_idx');
            $table->dropColumn([
                'id_usuario_asignado',
                'asignado_por',
                'fecha_asignacion',
                'estado_asignacion',
            ]);
        });
    }
};
