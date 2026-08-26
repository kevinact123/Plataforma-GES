<?php

namespace Tests\Feature;

use App\Models\Asignacion;
use App\Models\Paciente;
use App\Models\Patologia;
use App\Models\PermisoPatologia;
use App\Models\Prioridad;
use App\Models\RegistroGes;
use App\Models\Rol;
use App\Models\TipoRegistro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistroGesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRegistroGesSchema();
    }

    public function test_can_list_and_filter_registros_ges(): void
    {
        $user = $this->createUserWithAccess();

        $paciente1 = Paciente::create([
            'rut' => '11.111.111-1',
            'nombre' => 'Ana',
            'apellido_paterno' => 'Pérez',
            'apellido_materno' => 'López',
            'fecha_nacimiento' => '1990-01-10',
            'sexo' => 'F',
            'activo' => true,
        ]);

        $paciente2 = Paciente::create([
            'rut' => '22.222.222-2',
            'nombre' => 'Luis',
            'apellido_paterno' => 'García',
            'apellido_materno' => 'Silva',
            'fecha_nacimiento' => '1985-05-12',
            'sexo' => 'M',
            'activo' => true,
        ]);

        $patologia = Patologia::first();
        $prioridad1 = Prioridad::first();
        $prioridad2 = Prioridad::create([
            'nombre' => 'Urgente',
            'nivel' => 2,
            'descripcion' => 'Urgente',
        ]);
        $tipo1 = TipoRegistro::first();
        $tipo2 = TipoRegistro::create([
            'nombre' => 'Especialidad',
            'descripcion' => 'Especialidad',
            'activo' => true,
        ]);

        $registroPendiente = RegistroGes::create([
            'id_paciente' => $paciente1->id_paciente,
            'id_patologia' => $patologia->id_patologia,
            'id_prioridad' => $prioridad1->id_prioridad,
            'id_tipo_registro' => $tipo1->id_tipo_registro,
            'tipo_tratamiento' => 'Control',
            'fecha_ingreso' => '2026-08-10',
            'fecha_limite' => '2026-08-17',
            'estado' => 'Pendiente',
            'observaciones' => 'Sin asignar',
        ]);

        $registroAsignado = RegistroGes::create([
            'id_paciente' => $paciente2->id_paciente,
            'id_patologia' => $patologia->id_patologia,
            'id_prioridad' => $prioridad2->id_prioridad,
            'id_tipo_registro' => $tipo2->id_tipo_registro,
            'tipo_tratamiento' => 'Seguimiento',
            'fecha_ingreso' => '2026-08-11',
            'fecha_limite' => '2026-08-18',
            'estado' => 'Asignado',
            'observaciones' => 'Asignado a profesional',
        ]);

        Asignacion::create([
            'id_registro' => $registroAsignado->id_registro,
            'id_usuario' => $user->id_usuario,
            'asignado_por' => $user->id_usuario,
            'fecha_asignacion' => now(),
            'estado' => 'activo',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/registros-ges')
            ->assertOk()
            ->assertJsonFragment(['estado' => 'Pendiente'])
            ->assertJsonFragment(['estado' => 'Asignado']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/registros-ges/pendientes')
            ->assertOk()
            ->assertJsonFragment(['id_registro' => $registroPendiente->id_registro]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/registros-ges/asignados')
            ->assertOk()
            ->assertJsonFragment(['id_registro' => $registroAsignado->id_registro]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/registros-ges/sin-asignar')
            ->assertOk()
            ->assertJsonFragment(['id_registro' => $registroPendiente->id_registro]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/registros-ges?id_prioridad=' . $prioridad1->id_prioridad . '&id_patologia=' . $patologia->id_patologia . '&id_tipo_registro=' . $tipo1->id_tipo_registro . '&estado=Pendiente')
            ->assertOk()
            ->assertJsonFragment(['id_registro' => $registroPendiente->id_registro]);
    }

    public function test_user_without_permission_cannot_view_confidential_registros(): void
    {
        $user = $this->createUserWithAccess();

        $paciente = Paciente::create([
            'rut' => '33.333.333-3',
            'nombre' => 'Sofía',
            'apellido_paterno' => 'Navarro',
            'apellido_materno' => 'Mora',
            'fecha_nacimiento' => '1992-07-05',
            'sexo' => 'F',
            'activo' => true,
        ]);

        $patologiaConfidencial = Patologia::create([
            'numero_ges' => 18,
            'nombre' => 'VIH/SIDA',
            'descripcion' => 'Patología confidencial',
            'confidencial' => true,
            'activo' => true,
        ]);

        $prioridad = Prioridad::create([
            'nombre' => 'Urgente',
            'nivel' => 1,
            'descripcion' => 'Urgente',
        ]);

        $tipo = TipoRegistro::create([
            'nombre' => 'Consulta',
            'descripcion' => 'Consulta',
            'activo' => true,
        ]);

        $registro = RegistroGes::create([
            'id_paciente' => $paciente->id_paciente,
            'id_patologia' => $patologiaConfidencial->id_patologia,
            'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipo->id_tipo_registro,
            'tipo_tratamiento' => 'Control',
            'fecha_ingreso' => '2026-08-15',
            'fecha_limite' => '2026-08-22',
            'estado' => 'Pendiente',
            'observaciones' => 'No autorizado',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/registros-ges/' . $registro->id_registro)
            ->assertStatus(404);
    }

    public function test_catalogos_include_active_patients_without_ges_records(): void
    {
        $user = $this->createUserWithAccess();

        Paciente::create([
            'rut' => '17.777.777-7',
            'nombre' => 'Elena',
            'apellido_paterno' => 'Morales',
            'apellido_materno' => 'Castro',
            'fecha_nacimiento' => '1979-03-15',
            'sexo' => 'F',
            'activo' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/registros-ges/catalogos')
            ->assertOk()
            ->assertJsonFragment(['rut' => '17.777.777-7']);
    }

    public function test_can_delete_registro_with_related_rows(): void
    {
        $user = $this->createUserWithAccess();
        $this->createRegistroGesRelatedTables();

        $paciente = Paciente::create([
            'rut' => '16.666.666-6',
            'nombre' => 'Rosa',
            'apellido_paterno' => 'Fuentes',
            'apellido_materno' => 'Lara',
            'fecha_nacimiento' => '1980-11-20',
            'sexo' => 'F',
            'activo' => true,
        ]);

        $registro = RegistroGes::create([
            'id_paciente' => $paciente->id_paciente,
            'id_patologia' => Patologia::first()->id_patologia,
            'id_prioridad' => Prioridad::first()->id_prioridad,
            'id_tipo_registro' => TipoRegistro::first()->id_tipo_registro,
            'tipo_tratamiento' => 'Control',
            'fecha_ingreso' => '2026-08-20',
            'fecha_limite' => '2026-08-27',
            'estado' => 'Pendiente',
            'observaciones' => 'Con relaciones',
        ]);

        Asignacion::create([
            'id_registro' => $registro->id_registro,
            'id_usuario' => $user->id_usuario,
            'asignado_por' => $user->id_usuario,
            'fecha_asignacion' => now(),
            'estado' => 'activo',
        ]);

        DB::table('hitos')->insert([
            'id_registro' => $registro->id_registro,
            'id_usuario' => $user->id_usuario,
            'nombre' => 'Revisar caso',
            'estado' => 'pendiente',
        ]);

        DB::table('historial_registros')->insert([
            'id_registro' => $registro->id_registro,
            'id_usuario' => $user->id_usuario,
            'accion' => 'registro_creado',
            'fecha' => now(),
        ]);

        DB::table('registros_ges_documentos')->insert([
            'id_registro' => $registro->id_registro,
            'nombre_original' => 'documento.pdf',
            'nombre_archivo' => 'documento.pdf',
            'ruta_archivo' => 'registros-ges/' . $registro->id_registro . '/documento.pdf',
        ]);

        PermisoPatologia::query()
            ->where('id_usuario', $user->id_usuario)
            ->where('id_patologia', $registro->id_patologia)
            ->update(['puede_editar' => true]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/registros-ges/' . $registro->id_registro)
            ->assertOk()
            ->assertJsonPath('message', 'Registro GES eliminado correctamente.');

        $this->assertDatabaseMissing('registros_ges', ['id_registro' => $registro->id_registro]);
        $this->assertDatabaseMissing('asignaciones', ['id_registro' => $registro->id_registro]);
        $this->assertDatabaseMissing('hitos', ['id_registro' => $registro->id_registro]);
        $this->assertDatabaseMissing('historial_registros', ['id_registro' => $registro->id_registro]);
        $this->assertDatabaseMissing('registros_ges_documentos', ['id_registro' => $registro->id_registro]);
    }

    private function createUserWithAccess(): User
    {
        $role = Rol::create([
            'nombre' => 'digitadora',
            'descripcion' => 'Digitadora',
        ]);

        $user = User::create([
            'nombre' => 'Test',
            'apellido' => 'Usuario',
            'username' => 'test.user',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $patologia = Patologia::create([
            'numero_ges' => 1,
            'nombre' => 'Patología accesible',
            'descripcion' => 'No confidencial',
            'confidencial' => false,
            'activo' => true,
        ]);

        PermisoPatologia::create([
            'id_usuario' => $user->id_usuario,
            'id_patologia' => $patologia->id_patologia,
            'puede_ver' => true,
            'puede_editar' => false,
            'puede_asignar' => false,
        ]);

        return $user;
    }

    private function createRegistroGesSchema(): void
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
            $table->string('estado')->nullable();
        });

        Prioridad::create([
            'nombre' => 'Normal',
            'nivel' => 1,
            'descripcion' => 'Normal',
        ]);

        Prioridad::create([
            'nombre' => 'Urgente',
            'nivel' => 2,
            'descripcion' => 'Urgente',
        ]);

        TipoRegistro::create([
            'nombre' => 'Consulta',
            'descripcion' => 'Consulta',
            'activo' => true,
        ]);

        TipoRegistro::create([
            'nombre' => 'Especialidad',
            'descripcion' => 'Especialidad',
            'activo' => true,
        ]);
    }

    private function createRegistroGesRelatedTables(): void
    {
        if (!Schema::hasTable('hitos')) {
            Schema::create('hitos', function ($table): void {
                $table->id('id_hito');
                $table->unsignedBigInteger('id_registro');
                $table->unsignedBigInteger('id_usuario')->nullable();
                $table->string('nombre');
                $table->string('estado')->nullable();
                $table->timestamp('fecha_inicio')->nullable();
                $table->timestamp('fecha_completado')->nullable();
                $table->text('observacion')->nullable();
            });
        }

        if (!Schema::hasTable('historial_registros')) {
            Schema::create('historial_registros', function ($table): void {
                $table->id('id_historial');
                $table->unsignedBigInteger('id_registro');
                $table->unsignedBigInteger('id_usuario')->nullable();
                $table->string('accion');
                $table->string('campo_modificado')->nullable();
                $table->text('valor_anterior')->nullable();
                $table->text('valor_nuevo')->nullable();
                $table->timestamp('fecha')->nullable();
                $table->string('ip')->nullable();
            });
        }

        if (!Schema::hasTable('registros_ges_documentos')) {
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
}
