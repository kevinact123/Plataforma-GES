<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoleActionPermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('permisos_roles');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('roles');

        Schema::create('roles', function ($table): void {
            $table->id('id_rol');
            $table->string('nombre');
            $table->string('descripcion')->nullable();
        });
        Schema::create('permisos', function ($table): void {
            $table->id('id_permiso');
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
        });
        Schema::create('permisos_roles', function ($table): void {
            $table->unsignedBigInteger('id_rol');
            $table->unsignedBigInteger('id_permiso');
            $table->primary(['id_rol', 'id_permiso']);
        });
        Schema::create('usuarios', function ($table): void {
            $table->id('id_usuario');
            $table->unsignedBigInteger('id_rol')->nullable();
            $table->string('tipo_digitadora')->nullable();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('username');
            $table->string('password');
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_creacion')->nullable();
        });
    }

    public function test_permissions_are_attached_to_roles_without_creating_roles(): void
    {
        $supervisor = Rol::create(['nombre' => 'Supervisor']);
        $digitadora = Rol::create(['nombre' => 'Digitadora']);
        $this->attach($supervisor, [
            'ver_dashboard',
            'asignar_pacientes',
            'reasignar_pacientes',
            'administrar_usuarios',
            'administrar_patologias',
            'editar_registros',
        ]);
        $this->attach($digitadora, ['ver_registros', 'crear_registros', 'editar_registros']);

        $this->assertTrue($supervisor->tienePermiso('asignar_pacientes'));
        $this->assertTrue($supervisor->tienePermiso('administrar_usuarios'));
        $this->assertTrue($supervisor->tienePermiso('administrar_patologias'));
        $this->assertTrue($supervisor->tienePermiso('editar_registros'));
        $this->assertTrue($digitadora->tienePermiso('crear_registros'));
        $this->assertFalse($digitadora->tienePermiso('asignar_pacientes'));
        $this->assertSame(2, Rol::query()->count());
    }

    public function test_permission_middleware_enforces_the_role_permission(): void
    {
        $supervisor = Rol::create(['nombre' => 'Supervisor']);
        $this->attach($supervisor, ['ver_dashboard', 'administrar_usuarios']);
        $user = User::create([
            'nombre' => 'Sofía',
            'apellido' => 'Supervisora',
            'username' => 'sofia.supervisora',
            'password' => bcrypt('secret123'),
            'id_rol' => $supervisor->id_rol,
            'activo' => true,
        ]);

        Route::middleware(['auth', 'permission:ver_dashboard'])->get('/test-dashboard-permission', fn () => 'ok');
        Route::middleware(['auth', 'permission:administrar_usuarios'])->get('/test-user-admin-permission', fn () => 'ok');

        $this->actingAs($user)->get('/test-dashboard-permission')->assertOk();
        $this->actingAs($user)->get('/test-user-admin-permission')->assertOk();
    }

    private function attach(Rol $role, array $permissions): void
    {
        foreach ($permissions as $name) {
            $permissionId = DB::table('permisos')->where('nombre', $name)->value('id_permiso')
                ?? DB::table('permisos')->insertGetId([
                    'nombre' => $name,
                    'descripcion' => $name,
                ]);
            DB::table('permisos_roles')->insert([
                'id_rol' => $role->id_rol,
                'id_permiso' => $permissionId,
            ]);
        }
    }
}
