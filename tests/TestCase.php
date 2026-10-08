<?php

namespace Tests;

use App\Models\Rol;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function createRolePermissionSchema(): void
    {
        if (! Schema::hasTable('permisos')) {
            Schema::create('permisos', function ($table): void {
                $table->id('id_permiso');
                $table->string('nombre', 100)->unique();
                $table->string('descripcion')->nullable();
            });
        }

        if (! Schema::hasTable('permisos_roles')) {
            Schema::create('permisos_roles', function ($table): void {
                $table->unsignedBigInteger('id_rol');
                $table->unsignedBigInteger('id_permiso');
                $table->primary(['id_rol', 'id_permiso']);
            });
        }

        if (! Schema::hasTable('permisos_patologia')) {
            Schema::create('permisos_patologia', function ($table): void {
                $table->id('id_permiso_patologia');
                $table->unsignedBigInteger('id_usuario');
                $table->unsignedBigInteger('id_patologia');
                $table->boolean('puede_ver')->default(false);
                $table->boolean('puede_editar')->default(false);
                $table->boolean('puede_asignar')->default(false);
            });
        }
    }

    protected function createDocumentTables(): void
    {
        if (! Schema::hasTable('documentacion_categorias')) {
            Schema::create('documentacion_categorias', function ($table): void {
                $table->id('id_categoria');
                $table->string('nombre', 100)->unique();
                $table->text('descripcion')->nullable();
                $table->unsignedBigInteger('id_categoria_padre')->nullable();
                $table->timestamp('fecha_creacion')->nullable();
                $table->timestamp('fecha_actualizacion')->nullable();
            });
        }

        if (! Schema::hasTable('documentos_generales')) {
            Schema::create('documentos_generales', function ($table): void {
                $table->id('id_documento');
                $table->unsignedBigInteger('id_usuario');
                $table->string('nombre_original');
                $table->string('nombre_archivo');
                $table->string('ruta_archivo');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('tamanio')->nullable();
                $table->string('nombre')->nullable();
                $table->text('descripcion')->nullable();
                $table->json('etiquetas')->nullable();
                $table->string('estado', 20)->default('pendiente');
                $table->unsignedBigInteger('id_paciente')->nullable();
                $table->unsignedBigInteger('id_registro')->nullable();
                $table->unsignedBigInteger('id_categoria')->nullable();
                $table->unsignedBigInteger('id_usuario_revisor')->nullable();
                $table->unsignedBigInteger('id_usuario_asignado')->nullable();
                $table->unsignedBigInteger('asignado_por')->nullable();
                $table->timestamp('fecha_asignacion')->nullable();
                $table->string('estado_asignacion', 20)->default('sin_asignar');
                $table->timestamp('fecha_revision')->nullable();
                $table->timestamp('fecha_creacion')->nullable();
                $table->timestamp('fecha_actualizacion')->nullable();
            });
        }

        if (! Schema::hasTable('registros_ges_documentos')) {
            Schema::create('registros_ges_documentos', function ($table): void {
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
    }

    protected function grantDefaultRolePermissions(Rol $role): void
    {
        $permissions = match (strtolower($role->nombre)) {
            'admin', 'administrador', 'superadmin' => [
                'ver_registros',
                'crear_registros',
                'editar_registros',
                'eliminar_registros',
                'asignar_pacientes',
                'reasignar_pacientes',
                'ver_dashboard',
                'administrar_usuarios',
                'administrar_patologias',
                'gestionar_hitos',
            ],
            'supervisor' => [
                'ver_registros',
                'eliminar_registros',
                'asignar_pacientes',
                'reasignar_pacientes',
                'ver_dashboard',
                'gestionar_hitos',
            ],
            'digitadora' => [
                'ver_registros',
                'crear_registros',
                'editar_registros',
                'eliminar_registros',
                'gestionar_hitos',
            ],
            default => [],
        };

        if ($permissions === []) {
            return;
        }

        $this->createRolePermissionSchema();

        foreach ($permissions as $name) {
            $id = DB::table('permisos')->where('nombre', $name)->value('id_permiso');
            if ($id === null) {
                $id = DB::table('permisos')->insertGetId(['nombre' => $name]);
            }

            DB::table('permisos_roles')->insertOrIgnore([
                'id_rol' => $role->id_rol,
                'id_permiso' => $id,
            ]);
        }
    }

    protected function createRoleWithDefaultPermissions(string $name, ?string $description = null): Rol
    {
        $role = Rol::create([
            'nombre' => $name,
            'descripcion' => $description,
        ]);
        $this->grantDefaultRolePermissions($role);

        return $role;
    }
}
