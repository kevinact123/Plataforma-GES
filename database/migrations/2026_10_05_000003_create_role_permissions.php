<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'ver_registros' => 'Consultar registros GES',
        'crear_registros' => 'Crear registros GES',
        'editar_registros' => 'Editar registros GES',
        'asignar_pacientes' => 'Asignar pacientes o registros',
        'reasignar_pacientes' => 'Reasignar pacientes o registros',
        'ver_dashboard' => 'Consultar dashboard y métricas',
        'administrar_usuarios' => 'Administrar usuarios y accesos',
        'administrar_patologias' => 'Administrar patologías',
        'gestionar_hitos' => 'Gestionar hitos de registros',
    ];

    private const ROLE_PERMISSIONS = [
        1 => [
            'ver_registros',
            'crear_registros',
            'editar_registros',
            'asignar_pacientes',
            'reasignar_pacientes',
            'ver_dashboard',
            'administrar_usuarios',
            'administrar_patologias',
            'gestionar_hitos',
        ],
        2 => [
            'ver_registros',
            'asignar_pacientes',
            'reasignar_pacientes',
            'ver_dashboard',
            'gestionar_hitos',
        ],
        3 => [
            'ver_registros',
            'crear_registros',
            'editar_registros',
            'gestionar_hitos',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('usuarios')) {
            throw new RuntimeException('No se pueden asociar permisos: deben existir roles y usuarios.');
        }

        foreach ([1, 2, 3] as $roleId) {
            if (! DB::table('roles')->where('id_rol', $roleId)->exists()) {
                throw new RuntimeException("No existe el rol canónico con id {$roleId}.");
            }
        }

        // MySQL no revierte DDL: limpia restos de un intento fallido anterior.
        Schema::dropIfExists('permisos_roles');
        Schema::dropIfExists('permisos');

        Schema::create('permisos', function (Blueprint $table): void {
            $table->id('id_permiso');
            $table->string('nombre', 100)->unique();
            $table->string('descripcion')->nullable();
        });

        Schema::create('permisos_roles', function (Blueprint $table): void {
            // Debe coincidir con roles.id_rol (INT con signo).
            $table->integer('id_rol');
            $table->unsignedBigInteger('id_permiso');
            $table->primary(['id_rol', 'id_permiso']);
            $table->foreign('id_rol')->references('id_rol')->on('roles')->cascadeOnDelete();
            $table->foreign('id_permiso')->references('id_permiso')->on('permisos')->cascadeOnDelete();
        });

        DB::transaction(function (): void {
            foreach (self::PERMISSIONS as $name => $description) {
                DB::table('permisos')->insert([
                    'nombre' => $name,
                    'descripcion' => $description,
                ]);
            }

            foreach (self::ROLE_PERMISSIONS as $roleId => $permissionNames) {
                $permissionIds = DB::table('permisos')
                    ->whereIn('nombre', $permissionNames)
                    ->pluck('id_permiso');

                foreach ($permissionIds as $permissionId) {
                    DB::table('permisos_roles')->insert([
                        'id_rol' => $roleId,
                        'id_permiso' => $permissionId,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos_roles');
        Schema::dropIfExists('permisos');
    }
};
