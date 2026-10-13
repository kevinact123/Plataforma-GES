<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['hora_llegada', 'hora_atencion'] as $column) {
            if (! Schema::hasColumn('pacientes', $column)) {
                Schema::table('pacientes', function (Blueprint $table) use ($column): void {
                    $table->dateTime($column)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['hora_llegada', 'hora_atencion'] as $column) {
            if (Schema::hasColumn('pacientes', $column)) {
                Schema::table('pacientes', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
