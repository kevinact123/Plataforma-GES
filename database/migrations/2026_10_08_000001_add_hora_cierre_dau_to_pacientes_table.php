<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pacientes', 'hora_cierre_dau')) {
            return;
        }

        Schema::table('pacientes', function (Blueprint $table): void {
            $table->dateTime('hora_cierre_dau')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('pacientes', 'hora_cierre_dau')) {
            Schema::table('pacientes', function (Blueprint $table): void {
                $table->dropColumn('hora_cierre_dau');
            });
        }
    }
};
