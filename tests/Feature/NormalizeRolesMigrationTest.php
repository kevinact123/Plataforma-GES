<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class NormalizeRolesMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('permisos_roles');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('roles');
        Schema::enableForeignKeyConstraints();

        Schema::create('roles', function ($table): void {
            $table->unsignedBigInteger('id_rol')->primary();
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
        });

        Schema::create('usuarios', function ($table): void {
            $table->id('id_usuario');
            $table->unsignedBigInteger('id_rol')->nullable();
            $table->string('tipo_digitadora')->nullable();
            $table->foreign('id_rol')->references('id_rol')->on('roles');
        });

        Schema::create('role_assignments', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('id_rol');
            $table->foreign('id_rol')->references('id_rol')->on('roles');
        });
    }

    public function test_it_consolidates_legacy_roles_and_reassigns_all_foreign_key_references(): void
    {
        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre' => 'Administrador', 'descripcion' => 'Admin antiguo'],
            ['id_rol' => 2, 'nombre' => 'Supervisor', 'descripcion' => 'Supervisor antiguo'],
            ['id_rol' => 3, 'nombre' => 'Operador', 'descripcion' => 'Operador antiguo'],
            ['id_rol' => 4, 'nombre' => 'admin', 'descripcion' => 'Admin duplicado'],
            ['id_rol' => 5, 'nombre' => 'digitadora', 'descripcion' => 'Digitadora duplicada'],
        ]);
        DB::table('usuarios')->insert([
            ['id_usuario' => 1, 'id_rol' => 1],
            ['id_usuario' => 2, 'id_rol' => 2],
            ['id_usuario' => 3, 'id_rol' => 3],
            ['id_usuario' => 4, 'id_rol' => 4],
            ['id_usuario' => 5, 'id_rol' => 5],
        ]);
        DB::table('role_assignments')->insert([
            ['id' => 1, 'id_rol' => 4],
            ['id' => 2, 'id_rol' => 5],
        ]);

        $this->migration()->up();

        $this->assertSame(
            [
                ['id_rol' => 1, 'nombre' => 'Administrador', 'descripcion' => 'Administración completa del sistema'],
                ['id_rol' => 2, 'nombre' => 'Supervisor', 'descripcion' => 'Supervisión y distribución de carga laboral'],
                ['id_rol' => 3, 'nombre' => 'Digitadora', 'descripcion' => 'Digitación y gestión de registros GES'],
            ],
            DB::table('roles')->orderBy('id_rol')->get(['id_rol', 'nombre', 'descripcion'])->map(fn ($role) => (array) $role)->all(),
        );
        $this->assertSame([1, 2, 3, 1, 3], DB::table('usuarios')->orderBy('id_usuario')->pluck('id_rol')->all());
        $this->assertSame([1, 3], DB::table('role_assignments')->orderBy('id')->pluck('id_rol')->all());
        $this->assertSame(5, DB::table('usuarios')->count());
    }

    public function test_it_stops_without_changing_data_when_an_unrecognized_role_exists(): void
    {
        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre' => 'Administrador', 'descripcion' => null],
            ['id_rol' => 6, 'nombre' => 'Auditor', 'descripcion' => null],
        ]);
        DB::table('usuarios')->insert([
            ['id_usuario' => 1, 'id_rol' => 1],
        ]);

        try {
            $this->migration()->up();
            $this->fail('Se esperaba detener la migración ante un rol no reconocido.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Auditor', $exception->getMessage());
        }

        $this->assertSame(['Administrador', 'Auditor'], DB::table('roles')->orderBy('id_rol')->pluck('nombre')->all());
        $this->assertSame([1], DB::table('usuarios')->pluck('id_rol')->all());
    }

    public function test_it_creates_missing_canonical_role_and_moves_digitadoras_from_role_five(): void
    {
        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre' => 'Administrador', 'descripcion' => null],
            ['id_rol' => 2, 'nombre' => 'Supervisor', 'descripcion' => null],
            ['id_rol' => 5, 'nombre' => 'Digitadora', 'descripcion' => null],
        ]);
        DB::table('usuarios')->insert([
            ['id_usuario' => 1, 'id_rol' => 5],
            ['id_usuario' => 2, 'id_rol' => 5],
        ]);

        $this->migration()->up();

        $this->assertSame([1, 2, 3], DB::table('roles')->orderBy('id_rol')->pluck('id_rol')->all());
        $this->assertSame([3, 3], DB::table('usuarios')->orderBy('id_usuario')->pluck('id_rol')->all());
        $this->assertSame('Digitadora', DB::table('roles')->where('id_rol', 3)->value('nombre'));
    }

    public function test_role_permission_migration_creates_the_action_catalog_and_role_matrix(): void
    {
        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre' => 'Administrador', 'descripcion' => null],
            ['id_rol' => 2, 'nombre' => 'Supervisor', 'descripcion' => null],
            ['id_rol' => 3, 'nombre' => 'Digitadora', 'descripcion' => null],
        ]);

        $migration = require database_path('migrations/2026_10_05_000003_create_role_permissions.php');
        $migration->up();

        $this->assertSame(9, DB::table('permisos')->count());
        $this->assertSame(9, DB::table('permisos_roles')->where('id_rol', 1)->count());
        $this->assertSame(5, DB::table('permisos_roles')->where('id_rol', 2)->count());
        $this->assertSame(4, DB::table('permisos_roles')->where('id_rol', 3)->count());
        $this->assertDatabaseHas('permisos_roles', [
            'id_rol' => 2,
            'id_permiso' => DB::table('permisos')->where('nombre', 'asignar_pacientes')->value('id_permiso'),
        ]);
        $this->assertDatabaseMissing('permisos_roles', [
            'id_rol' => 3,
            'id_permiso' => DB::table('permisos')->where('nombre', 'asignar_pacientes')->value('id_permiso'),
        ]);
    }

    public function test_supervisor_receives_all_configuration_permissions(): void
    {
        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre' => 'Administrador', 'descripcion' => null],
            ['id_rol' => 2, 'nombre' => 'Supervisor', 'descripcion' => null],
            ['id_rol' => 3, 'nombre' => 'Digitadora', 'descripcion' => null],
        ]);

        $permissionsMigration = require database_path('migrations/2026_10_05_000003_create_role_permissions.php');
        $permissionsMigration->up();

        $configurationMigration = require database_path('migrations/2026_10_07_000001_grant_supervisor_configuration_permissions.php');
        $configurationMigration->up();

        foreach (['administrar_usuarios', 'administrar_patologias', 'editar_registros'] as $permission) {
            $this->assertDatabaseHas('permisos_roles', [
                'id_rol' => 2,
                'id_permiso' => DB::table('permisos')->where('nombre', $permission)->value('id_permiso'),
            ]);
        }

        $this->assertSame(8, DB::table('permisos_roles')->where('id_rol', 2)->count());
        $this->assertSame(4, DB::table('permisos_roles')->where('id_rol', 3)->count());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_05_000002_normalize_roles.php');
    }
}
