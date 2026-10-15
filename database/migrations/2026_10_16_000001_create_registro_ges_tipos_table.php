<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_ges_tipos', function (Blueprint $table): void {
            $table->id('id_registro_tipo');
            $table->unsignedBigInteger('id_registro');
            $table->unsignedBigInteger('id_tipo_registro');
            $table->decimal('puntaje_complejidad', 8, 2)->nullable();

            $table->unique(['id_registro', 'id_tipo_registro']);
            $table->index('id_tipo_registro');
        });

        if (! Schema::hasTable('registros_ges')
            || ! Schema::hasColumn('registros_ges', 'id_registro')
            || ! Schema::hasColumn('registros_ges', 'id_tipo_registro')) {
            return;
        }

        $puntajes = Schema::hasTable('complejidad_registro')
            && Schema::hasColumn('complejidad_registro', 'id_tipo_registro')
            && Schema::hasColumn('complejidad_registro', 'puntaje')
            ? DB::table('complejidad_registro')
                ->select('id_tipo_registro')
                ->selectRaw('AVG(puntaje) as puntaje_complejidad')
                ->groupBy('id_tipo_registro')
                ->pluck('puntaje_complejidad', 'id_tipo_registro')
            : collect();

        DB::table('registros_ges')
            ->select('id_registro', 'id_tipo_registro')
            ->whereNotNull('id_tipo_registro')
            ->orderBy('id_registro')
            ->chunk(500, function ($registros) use ($puntajes): void {
                $tipos = $registros->map(fn ($registro): array => [
                    'id_registro' => $registro->id_registro,
                    'id_tipo_registro' => $registro->id_tipo_registro,
                    'puntaje_complejidad' => $puntajes->get($registro->id_tipo_registro),
                ])->all();

                DB::table('registro_ges_tipos')->insertOrIgnore($tipos);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_ges_tipos');
    }
};
