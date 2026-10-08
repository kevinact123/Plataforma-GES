<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->string('tipo_digitadora', 20)->nullable()->default(null)->change();
        });

        $digitadoraRoles = DB::table('roles')
            ->whereRaw('LOWER(nombre) = ?', ['digitadora'])
            ->pluck('id_rol');

        DB::table('usuarios')->whereIn('id_rol', $digitadoraRoles)
            ->where('tipo_digitadora', 'confidencial')
            ->update(['tipo_digitadora' => 'CONFIDENCIAL']);

        DB::table('usuarios')->whereIn('id_rol', $digitadoraRoles)
            ->where(fn ($query) => $query->whereNull('tipo_digitadora')
                ->orWhere('tipo_digitadora', '!=', 'CONFIDENCIAL'))
            ->update(['tipo_digitadora' => 'NO_CONFIDENCIAL']);

        DB::table('usuarios')->whereNotIn('id_rol', $digitadoraRoles)
            ->update(['tipo_digitadora' => null]);
    }

    public function down(): void
    {
        DB::table('usuarios')->where('tipo_digitadora', 'CONFIDENCIAL')->update(['tipo_digitadora' => 'confidencial']);
        DB::table('usuarios')->where(fn ($query) => $query->whereNull('tipo_digitadora')
            ->orWhere('tipo_digitadora', 'NO_CONFIDENCIAL'))->update(['tipo_digitadora' => 'estandar']);

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->string('tipo_digitadora', 20)->default('estandar')->nullable(false)->change();
        });
    }
};
