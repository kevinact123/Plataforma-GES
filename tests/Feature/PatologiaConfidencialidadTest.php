<?php

namespace Tests\Feature;

use App\Models\Patologia;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PatologiaConfidencialidadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['permisos_roles', 'permisos', 'patologias', 'usuarios', 'roles', 'personal_access_tokens'] as $table) {
            Schema::dropIfExists($table);
        }

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
        Schema::create('personal_access_tokens', function ($table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('patologias', function ($table): void {
            $table->id('id_patologia');
            $table->unsignedInteger('numero_ges')->nullable();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->boolean('confidencial')->default(false);
            $table->boolean('activo')->default(true);
        });
    }

    public function test_only_roles_with_administrar_patologias_can_change_confidentiality(): void
    {
        $patologia = Patologia::create(['numero_ges' => 5, 'nombre' => 'Prueba']);

        $admin = $this->userWithPermission('Administrador', ['administrar_patologias']);
        $supervisor = $this->userWithPermission('Supervisor', ['ver_registros']);

        $url = '/api/patologias/'.$patologia->id_patologia.'/confidencialidad';

        $this->actingAs($supervisor, 'sanctum')->putJson($url, ['confidencial' => true])->assertForbidden();
        $this->assertFalse($patologia->refresh()->confidencial);

        $this->actingAs($admin, 'sanctum')->putJson($url, ['confidencial' => true])
            ->assertOk()
            ->assertJsonPath('data.confidencial', true);
        $this->assertTrue($patologia->refresh()->confidencial);

        $this->actingAs($admin, 'sanctum')->putJson($url, ['confidencial' => false])->assertOk();
        $this->assertFalse($patologia->refresh()->confidencial);

        $this->actingAs($admin, 'sanctum')->putJson($url, [])->assertUnprocessable();
    }

    private function userWithPermission(string $role, array $permissions): User
    {
        $rol = Rol::create(['nombre' => $role]);

        foreach ($permissions as $name) {
            $id = DB::table('permisos')->insertGetId(['nombre' => $name, 'descripcion' => $name]);
            DB::table('permisos_roles')->insert(['id_rol' => $rol->id_rol, 'id_permiso' => $id]);
        }

        return User::create([
            'nombre' => $role,
            'apellido' => 'Test',
            'username' => strtolower($role),
            'password' => bcrypt('secret123'),
            'id_rol' => $rol->id_rol,
            'activo' => true,
        ]);
    }
}
