<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_generales', function (Blueprint $table): void {
            $table->string('nombre')->nullable()->after('id_usuario');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_generales', function (Blueprint $table): void {
            $table->dropColumn('nombre');
        });
    }
};
