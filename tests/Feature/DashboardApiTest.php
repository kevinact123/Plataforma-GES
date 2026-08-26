<?php

namespace Tests\Feature;

use App\Models\Asignacion;
use App\Models\Hito;
use App\Models\Paciente;
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

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createDashboardSchema();
    }

    public function test_dashboard_endpoints_return_summary_and_distributions(): void
    {
        $admin = $this->createUser('admin', 'Admin', 'admin', 'Administrador del sistema');
        $operador1 = $this->createUser('digitadora', 'Carmen', 'carmen.avendano', 'Digitadora');
        $operador2 = $this->createUser('digitadora', 'Carolina', 'carolina.acuna', 'Digitadora');

        $paciente1 = Paciente::create([
            'rut' => '11111111-1',
            'nombre' => 'Ana',
            'apellido_paterno' => 'Pérez',
            'apellido_materno' => 'López',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'F',
            'activo' => true,
        ]);

        $paciente2 = Paciente::create([
            'rut' => '22222222-2',
            'nombre' => 'Luis',
            'apellido_paterno' => 'García',
            'apellido_materno' => 'Soto',
            'fecha_nacimiento' => '1985-05-15',
            'sexo' => 'M',
            'activo' => true,
        ]);

        $paciente3 = Paciente::create([
            'rut' => '33333333-3',
            'nombre' => 'Marta',
            'apellido_paterno' => 'Rojas',
            'apellido_materno' => 'Pinto',
            'fecha_nacimiento' => '1995-07-12',
            'sexo' => 'F',
            'activo' => true,
        ]);

        $patologia1 = Patologia::create([
            'numero_ges' => 101,
            'nombre' => 'Patología A',
            'descripcion' => 'Patología A',
            'confidencial' => false,
            'activo' => true,
        ]);

        $patologia2 = Patologia::create([
            'numero_ges' => 102,
            'nombre' => 'Patología B',
            'descripcion' => 'Patología B',
            'confidencial' => false,
            'activo' => true,
        ]);

        $prioridad1 = Prioridad::create(['nombre' => 'Urgente', 'nivel' => 1, 'descripcion' => 'Urgente']);
        $prioridad2 = Prioridad::create(['nombre' => 'Alta', 'nivel' => 2, 'descripcion' => 'Alta']);

        $tipo1 = TipoRegistro::create(['nombre' => 'Prestación Otorgada', 'descripcion' => 'Prestación Otorgada', 'activo' => true]);
        $tipo2 = TipoRegistro::create(['nombre' => 'IPD', 'descripcion' => 'IPD', 'activo' => true]);

        foreach ([$operador1, $operador2] as $operador) {
            PermisoPatologia::create([
                'id_usuario' => $operador->id_usuario,
                'id_patologia' => $patologia1->id_patologia,
                'puede_ver' => true,
                'puede_editar' => false,
                'puede_asignar' => true,
            ]);
        }

        $registro1 = RegistroGes::create([
            'id_paciente' => $paciente1->id_paciente,
            'id_patologia' => $patologia1->id_patologia,
            'id_prioridad' => $prioridad1->id_prioridad,
            'id_tipo_registro' => $tipo1->id_tipo_registro,
            'tipo_tratamiento' => 'Control',
            'fecha_ingreso' => '2026-08-20',
            'fecha_limite' => '2026-08-27',
            'estado' => 'Pendiente',
            'observaciones' => 'Registro 1',
        ]);

        $registro2 = RegistroGes::create([
            'id_paciente' => $paciente2->id_paciente,
            'id_patologia' => $patologia2->id_patologia,
            'id_prioridad' => $prioridad2->id_prioridad,
            'id_tipo_registro' => $tipo2->id_tipo_registro,
            'tipo_tratamiento' => 'Seguimiento',
            'fecha_ingreso' => '2026-08-21',
            'fecha_limite' => '2026-08-28',
            'estado' => 'Asignado',
            'observaciones' => 'Registro 2',
        ]);

        $registro3 = RegistroGes::create([
            'id_paciente' => $paciente3->id_paciente,
            'id_patologia' => $patologia1->id_patologia,
            'id_prioridad' => $prioridad1->id_prioridad,
            'id_tipo_registro' => $tipo1->id_tipo_registro,
            'tipo_tratamiento' => 'Control',
            'fecha_ingreso' => '2026-08-22',
            'fecha_limite' => '2026-08-29',
            'estado' => 'Pendiente',
            'observaciones' => 'Registro 3',
        ]);

        Asignacion::create([
            'id_registro' => $registro2->id_registro,
            'id_usuario' => $operador1->id_usuario,
            'asignado_por' => $admin->id_usuario,
            'fecha_asignacion' => now(),
            'estado' => 'activa',
            'observacion' => 'Asignada',
        ]);

        Asignacion::create([
            'id_registro' => $registro1->id_registro,
            'id_usuario' => $operador2->id_usuario,
            'asignado_por' => $admin->id_usuario,
            'fecha_asignacion' => now(),
            'fecha_finalizacion' => now(),
            'estado' => 'finalizada',
            'observacion' => 'Completada',
        ]);

        Hito::create([
            'id_registro' => $registro1->id_registro,
            'id_usuario' => $operador1->id_usuario,
            'nombre' => 'Información recibida',
            'estado' => 'pendiente',
            'observacion' => 'Pendiente',
        ]);

        Hito::create([
            'id_registro' => $registro2->id_registro,
            'id_usuario' => $operador2->id_usuario,
            'nombre' => 'DAU revisado',
            'estado' => 'completado',
            'fecha_inicio' => now(),
            'fecha_completado' => now(),
            'observacion' => 'Completado',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/resumen')
            ->assertOk()
            ->assertJsonPath('total_pacientes', 3)
            ->assertJsonPath('total_registros', 3)
            ->assertJsonPath('registros_pendientes', 2)
            ->assertJsonPath('registros_en_proceso', 1)
            ->assertJsonPath('registros_completados', 1)
            ->assertJsonPath('registros_sin_asignar', 1);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/distribuciones')
            ->assertOk()
            ->assertJsonFragment(['label' => 'Urgente'])
            ->assertJsonFragment(['label' => 'Patología A']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/carga-operadores')
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Carmen Test']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/hitos')
            ->assertOk()
            ->assertJsonPath('pendientes', 1)
            ->assertJsonPath('completados', 1);
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

    private function createDashboardSchema(): void
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

        Schema::create('pacientes', function ($table): void {
            $table->id('id_paciente');
            $table->string('rut')->unique();
            $table->string('nombre');
            $table->string('apellido_paterno');
            $table->string('apellido_materno')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_registro')->nullable();
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
            $table->id('id_permiso_patologia');
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

        Schema::create('complejidad_registro', function ($table): void {
            $table->id('id_complejidad');
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_tipo_registro');
            $table->integer('puntaje');
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_evaluacion')->nullable();
        });
    }
}
