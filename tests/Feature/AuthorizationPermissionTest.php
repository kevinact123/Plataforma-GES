<?php

namespace Tests\Feature;

use App\Models\Patologia;
use App\Models\PermisoPatologia;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthorizationPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAuthorizationTables();
    }

    public function test_digitadora_without_permission_cannot_view_confidential_patologia(): void
    {
        $role = Rol::create([
            'nombre' => 'digitadora',
            'descripcion' => 'Digitadora estándar',
        ]);

        $user = User::create([
            'nombre' => 'Ana',
            'apellido' => 'Gomez',
            'username' => 'ana.digitadora',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $patologia = Patologia::create([
            'numero_ges' => 18,
            'nombre' => 'VIH/SIDA',
            'descripcion' => 'Patología confidencial',
            'confidencial' => true,
            'activo' => true,
        ]);

        Route::middleware(['auth', 'patologia.autorizacion:view'])->get('/test-patologia/{patologia}', fn () => 'ok');

        $this->actingAs($user)
            ->get('/test-patologia/' . $patologia->id_patologia)
            ->assertStatus(403);
    }

    public function test_admin_can_view_confidential_patologia_without_explicit_permission(): void
    {
        $role = Rol::create([
            'nombre' => 'admin',
            'descripcion' => 'Administrador del sistema',
        ]);

        $user = User::create([
            'nombre' => 'Beto',
            'apellido' => 'Admin',
            'username' => 'beto.admin',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $patologia = Patologia::create([
            'numero_ges' => 86,
            'nombre' => 'Atención Integral de Salud en Agresión Sexual Aguda',
            'descripcion' => 'Patología confidencial',
            'confidencial' => true,
            'activo' => true,
        ]);

        Route::middleware(['auth', 'patologia.autorizacion:view'])->get('/test-patologia/{patologia}', fn () => 'ok');

        $this->actingAs($user)
            ->get('/test-patologia/' . $patologia->id_patologia)
            ->assertOk();
    }

    public function test_user_with_explicit_permission_can_view_confidential_patologia(): void
    {
        $role = Rol::create([
            'nombre' => 'digitadora',
            'descripcion' => 'Digitadora estándar',
        ]);

        $user = User::create([
            'nombre' => 'Carla',
            'apellido' => 'digitadora',
            'username' => 'carla.digitadora',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $patologia = Patologia::create([
            'numero_ges' => 18,
            'nombre' => 'VIH/SIDA',
            'descripcion' => 'Patología confidencial',
            'confidencial' => true,
            'activo' => true,
        ]);

        PermisoPatologia::create([
            'id_usuario' => $user->id_usuario,
            'id_patologia' => $patologia->id_patologia,
            'puede_ver' => true,
            'puede_editar' => false,
            'puede_asignar' => false,
        ]);

        Route::middleware(['auth', 'patologia.autorizacion:view'])->get('/test-patologia/{patologia}', fn () => 'ok');

        $this->actingAs($user)
            ->get('/test-patologia/' . $patologia->id_patologia)
            ->assertOk();
    }

    private function createAuthorizationTables(): void
    {
        Schema::create('roles', function ($table): void {
            $table->id('id_rol');
            $table->string('nombre');
            $table->string('descripcion')->nullable();
        });

        Schema::create('usuarios', function ($table): void {
            $table->id('id_usuario');
            $table->unsignedBigInteger('id_rol')->nullable();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('username')->unique();
            $table->string('password');
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_acceso')->nullable();
            $table->timestamp('fecha_creacion')->nullable();
        });

        Schema::create('patologias', function ($table): void {
            $table->id('id_patologia');
            $table->integer('numero_ges')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('confidencial')->default(false);
            $table->boolean('activo')->default(true);
        });

        Schema::create('permisos_patologia', function ($table): void {
            $table->id('id_permiso');
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_patologia');
            $table->boolean('puede_ver')->default(false);
            $table->boolean('puede_editar')->default(false);
            $table->boolean('puede_asignar')->default(false);
        });
    }
}
