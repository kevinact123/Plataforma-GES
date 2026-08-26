<?php

namespace Tests\Feature;

use App\Models\Asignacion;
use App\Models\Patologia;
use App\Models\PermisoPatologia;
use App\Models\Prioridad;
use App\Models\RegistroGes;
use App\Models\Rol;
use App\Models\TipoRegistro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AsignacionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAssignmentSchema();
    }

    public function test_can_assign_reassign_and_finalize_workload_history(): void
    {
        $admin = $this->createUser('admin', 'Admin', 'admin', 'Administrador del sistema');
        $operador1 = $this->createUser('digitadora', 'Carmen', 'carmen.avendano', 'Digitadora');
        $operador2 = $this->createUser('digitadora', 'Carolina', 'carolina.acuna', 'Digitadora');

        $patologia = Patologia::create([
            'numero_ges' => 12,
            'nombre' => 'Patología para asignación',
            'descripcion' => 'Patología activa',
            'confidencial' => false,
            'activo' => true,
        ]);

        PermisoPatologia::create([
            'id_usuario' => $operador1->id_usuario,
            'id_patologia' => $patologia->id_patologia,
            'puede_ver' => true,
            'puede_editar' => false,
            'puede_asignar' => true,
        ]);

        PermisoPatologia::create([
            'id_usuario' => $operador2->id_usuario,
            'id_patologia' => $patologia->id_patologia,
            'puede_ver' => true,
            'puede_editar' => false,
            'puede_asignar' => true,
        ]);

        $prioridad = Prioridad::create([
            'nombre' => 'Urgente',
            'nivel' => 2,
            'descripcion' => 'Urgente',
        ]);

        $tipoRegistro = TipoRegistro::create([
            'nombre' => 'Consulta',
            'descripcion' => 'Consulta',
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
            ->postJson('/api/asignaciones', [
                'id_registro' => $registro->id_registro,
                'id_usuario' => $operador1->id_usuario,
                'observacion' => 'Asignación inicial',
            ])
            ->assertOk()
            ->assertJsonPath('data.id_usuario', $operador1->id_usuario)
            ->assertJsonPath('data.estado', 'activa');

        $asignacion = Asignacion::where('id_registro', $registro->id_registro)->first();

        $respuestaReasignacion = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/asignaciones/' . $asignacion->id_asignacion . '/reasignar', [
                'id_usuario' => $operador2->id_usuario,
                'observacion' => 'Reasignación por carga',
            ])
            ->assertOk()
            ->assertJsonPath('data.id_usuario', $operador2->id_usuario);

        $nuevaAsignacion = Asignacion::findOrFail($respuestaReasignacion->json('data.id_asignacion'));

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/asignaciones/' . $nuevaAsignacion->id_asignacion . '/finalizar', [
                'observacion' => 'Trabajo concluido',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'finalizada');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/registros-ges/' . $registro->id_registro . '/historial-asignaciones')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/usuarios/' . $operador2->id_usuario . '/carga')
            ->assertOk()
            ->assertJsonPath('data.total_activas', 0);
    }

    public function test_can_suggest_best_operator_using_workload_and_complexity_data(): void
    {
        $admin = $this->createUser('admin', 'Admin', 'admin', 'Administrador del sistema');
        $operador1 = $this->createUser('digitadora', 'Carmen', 'carmen.avendano', 'Digitadora');
        $operador2 = $this->createUser('digitadora', 'Carolina', 'carolina.acuna', 'Digitadora');
        $operador3 = $this->createUser('digitadora', 'Luciana', 'luciana', 'Digitadora');

        $patologia = Patologia::create([
            'numero_ges' => 13,
            'nombre' => 'Patología con sugerencia',
            'descripcion' => 'Patología con carga variable',
            'confidencial' => false,
            'activo' => true,
        ]);

        foreach ([$operador1, $operador2, $operador3] as $operador) {
            PermisoPatologia::create([
                'id_usuario' => $operador->id_usuario,
                'id_patologia' => $patologia->id_patologia,
                'puede_ver' => true,
                'puede_editar' => false,
                'puede_asignar' => true,
            ]);
        }

        $prioridad = Prioridad::create([
            'nombre' => 'Urgente',
            'nivel' => 3,
            'descripcion' => 'Urgente',
        ]);

        $tipoRegistro = TipoRegistro::create([
            'nombre' => 'Especialidad',
            'descripcion' => 'Especialidad',
            'activo' => true,
        ]);

        $registro = RegistroGes::create([
            'id_paciente' => 2,
            'id_patologia' => $patologia->id_patologia,
            'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipoRegistro->id_tipo_registro,
            'tipo_tratamiento' => 'Seguimiento',
            'fecha_ingreso' => '2026-08-21',
            'fecha_limite' => '2026-08-28',
            'estado' => 'Pendiente',
            'observaciones' => 'Necesita asignación sugerida',
        ]);

        Asignacion::create([
            'id_registro' => $registro->id_registro,
            'id_usuario' => $operador1->id_usuario,
            'asignado_por' => $admin->id_usuario,
            'fecha_asignacion' => now(),
            'estado' => 'activa',
            'observacion' => 'Carga alta',
        ]);

        Asignacion::create([
            'id_registro' => $registro->id_registro,
            'id_usuario' => $operador2->id_usuario,
            'asignado_por' => $admin->id_usuario,
            'fecha_asignacion' => now(),
            'estado' => 'activa',
            'observacion' => 'Carga media',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/asignaciones/sugerir', [
                'id_registro' => $registro->id_registro,
                'cantidad_trabajo' => 4,
                'prioridad' => 3,
                'dificultad' => 2,
                'complejidad' => 3,
                'disponibilidad' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('operador_recomendado.id_usuario', $operador3->id_usuario);
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

    private function createAssignmentSchema(): void
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

        Schema::create('complejidad_patologia', function ($table): void {
            $table->id('id_complejidad_patologia');
            $table->unsignedBigInteger('id_patologia');
            $table->decimal('factor', 8, 2)->default(1.00);
            $table->integer('nivel')->default(1);
            $table->text('motivo')->nullable();
            $table->boolean('activo')->default(true);
        });

        Schema::create('complejidad_registro', function ($table): void {
            $table->id('id_complejidad');
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_tipo_registro');
            $table->integer('puntaje')->default(1);
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_evaluacion')->nullable();
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

        Schema::create('asignaciones', function ($table): void {
            $table->id('id_asignacion');
            $table->unsignedBigInteger('id_registro');
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('asignado_por')->nullable();
            $table->timestamp('fecha_asignacion')->nullable();
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_finalizacion')->nullable();
            $table->string('estado')->nullable();
            $table->text('observacion')->nullable();
        });
    }
}
