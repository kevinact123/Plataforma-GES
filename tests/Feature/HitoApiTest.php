<?php

namespace Tests\Feature;

use App\Models\Hito;
use App\Models\Patologia;
use App\Models\Prioridad;
use App\Models\RegistroGes;
use App\Models\Rol;
use App\Models\TipoRegistro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HitoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMilestoneSchema();
    }

    public function test_can_create_start_complete_and_query_milestones_with_history(): void
    {
        $admin = $this->createUser('admin', 'Admin', 'admin', 'Administrador del sistema');
        $responsable = $this->createUser('digitadora', 'Carmen', 'carmen.avendano', 'Digitadora');

        $patologia = Patologia::create([
            'numero_ges' => 101,
            'nombre' => 'Patología de prueba',
            'descripcion' => 'Patología activa',
            'confidencial' => false,
            'activo' => true,
        ]);

        $prioridad = Prioridad::create([
            'nombre' => 'Urgente',
            'nivel' => 2,
            'descripcion' => 'Urgente',
        ]);

        $tipoRegistro = TipoRegistro::create([
            'nombre' => 'Prestación Otorgada',
            'descripcion' => 'Prestación Otorgada',
            'activo' => true,
        ]);

        $registro = RegistroGes::create([
            'id_paciente' => 1,
            'id_patologia' => $patologia->id_patologia,
            'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipoRegistro->id_tipo_registro,
            'tipo_tratamiento' => 'Control',
            'fecha_ingreso' => '2026-08-20',
            'fecha_limite' => '2026-08-27',
            'estado' => 'Pendiente',
            'observaciones' => 'Requiere revisión',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/registros-ges/' . $registro->id_registro . '/hitos', [
                'nombre' => 'Información recibida',
                'id_usuario' => $responsable->id_usuario,
                'observacion' => 'Se recibió la documentación',
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Información recibida')
            ->assertJsonPath('data.estado', 'pendiente');

        $hito = Hito::query()->where('id_registro', $registro->id_registro)->firstOrFail();

        $this->actingAs($responsable, 'sanctum')
            ->postJson('/api/hitos/' . $hito->id_hito . '/iniciar', [
                'observacion' => 'Se inicia validación',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_proceso');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/hitos/' . $hito->id_hito . '/completar', [
                'observacion' => 'Se revisó la información y quedó completa',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'completado');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/registros-ges/' . $registro->id_registro . '/hitos')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/registros-ges/' . $registro->id_registro . '/hitos/pendientes')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('historial_registros', [
            'id_registro' => $registro->id_registro,
            'accion' => 'hito_creado',
        ]);

        $this->assertDatabaseHas('historial_registros', [
            'id_registro' => $registro->id_registro,
            'accion' => 'hito_iniciado',
        ]);

        $this->assertDatabaseHas('historial_registros', [
            'id_registro' => $registro->id_registro,
            'accion' => 'hito_completado',
        ]);
    }

    private function createUser(string $rolNombre, string $nombre, string $username, string $descripcion): User
    {
        $role = Rol::create([
            'nombre' => $rolNombre,
            'descripcion' => $descripcion,
        ]);

        return User::create([
            'nombre' => $nombre,
            'apellido' => 'Test',
            'username' => $username,
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);
    }

    private function createMilestoneSchema(): void
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

        Schema::create('hitos', function ($table): void {
            $table->id('id_hito');
            $table->unsignedBigInteger('id_registro');
            $table->unsignedBigInteger('id_usuario');
            $table->string('nombre');
            $table->string('estado')->default('pendiente');
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_completado')->nullable();
            $table->text('observacion')->nullable();
        });

        Schema::create('historial_registros', function ($table): void {
            $table->id('id_historial');
            $table->unsignedBigInteger('id_registro');
            $table->unsignedBigInteger('id_usuario');
            $table->string('accion');
            $table->string('campo_modificado')->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->timestamp('fecha')->nullable();
            $table->string('ip')->nullable();
        });
    }
}
