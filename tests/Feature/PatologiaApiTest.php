<?php

namespace Tests\Feature;

use App\Models\Patologia;
use App\Models\PermisoPatologia;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PatologiaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPatologiaSchema();
    }

    public function test_list_patologias_returns_active_and_visible_entries(): void
    {
        $role = Rol::create([
            'nombre' => 'digitadora',
            'descripcion' => 'Digitadora',
        ]);

        $user = User::create([
            'nombre' => 'Maria',
            'apellido' => 'Soto',
            'username' => 'maria.digitadora',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        Patologia::create([
            'numero_ges' => 1,
            'nombre' => 'Patología pública',
            'descripcion' => 'No confidencial',
            'confidencial' => false,
            'activo' => true,
        ]);

        $confidential = Patologia::create([
            'numero_ges' => 18,
            'nombre' => 'VIH/SIDA',
            'descripcion' => 'Confidencial',
            'confidencial' => true,
            'activo' => true,
        ]);

        PermisoPatologia::create([
            'id_usuario' => $user->id_usuario,
            'id_patologia' => $confidential->id_patologia,
            'puede_ver' => true,
            'puede_editar' => false,
            'puede_asignar' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/patologias');

        $response->assertOk();
        $response->assertJsonFragment(['nombre' => 'Patología pública']);
        $response->assertJsonFragment(['nombre' => 'VIH/SIDA']);
    }

    public function test_user_without_permission_cannot_view_confidential_patologia_detail(): void
    {
        $role = Rol::create([
            'nombre' => 'digitadora',
            'descripcion' => 'Digitadora',
        ]);

        $user = User::create([
            'nombre' => 'Pedro',
            'apellido' => 'Rios',
            'username' => 'pedro.digitadora',
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

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/patologias/' . $patologia->id_patologia)
            ->assertStatus(403);
    }

    private function createPatologiaSchema(): void
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

        Schema::create('prioridades', function ($table): void {
            $table->id('id_prioridad');
            $table->string('nombre');
            $table->integer('nivel')->nullable();
            $table->text('descripcion')->nullable();
        });

        Schema::create('tipos_registro', function ($table): void {
            $table->id('id_tipo_registro');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
        });

        Schema::create('registros_ges', function ($table): void {
            $table->id('id_registro');
            $table->unsignedBigInteger('id_paciente');
            $table->unsignedBigInteger('id_patologia');
            $table->unsignedBigInteger('id_prioridad');
            $table->unsignedBigInteger('id_tipo_registro');
            $table->string('tipo_tratamiento')->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->date('fecha_limite')->nullable();
            $table->string('estado')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_actualizacion')->nullable();
        });
    }
}
