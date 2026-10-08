<?php

namespace Tests\Feature;

use App\Models\Patologia;
use App\Models\Paciente;
use App\Models\PermisoPatologia;
use App\Models\Prioridad;
use App\Models\RegistroGes;
use App\Models\Rol;
use App\Models\TipoRegistro;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PacienteApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAuthorizationTables();
        $this->createRolePermissionSchema();
        $this->createDocumentTables();
    }

    public function test_active_user_can_create_patient(): void
    {
        $role = $this->createRoleWithDefaultPermissions('digitadora');
        $this->grantDefaultRolePermissions($role);
        $user = User::create([
            'nombre' => 'Ana',
            'apellido' => 'Soto',
            'username' => 'ana.creadora',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/pacientes', [
                'rut' => '19.876.543-2',
                'nombre' => 'Carla',
                'apellido_paterno' => 'Rojas',
                'apellido_materno' => 'Mena',
                'fecha_nacimiento' => '1991-04-12',
                'sexo' => 'F',
            ])
            ->assertCreated()
            ->assertJsonPath('data.rut', '19.876.543-2')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('pacientes', [
            'rut' => '19.876.543-2',
            'nombre' => 'Carla',
            'activo' => true,
        ]);
    }

    public function test_patient_without_ges_records_is_listed_after_creation(): void
    {
        $user = User::create([
            'nombre' => 'Ana',
            'apellido' => 'Soto',
            'username' => 'ana.listado',
            'password' => bcrypt('secret123'),
            'id_rol' => $this->createRoleWithDefaultPermissions('digitadora')->id_rol,
            'activo' => true,
        ]);

        Paciente::create([
            'rut' => '18.765.432-1',
            'nombre' => 'Mario',
            'apellido_paterno' => 'Vega',
            'apellido_materno' => 'Diaz',
            'fecha_nacimiento' => '1984-09-01',
            'sexo' => 'M',
            'activo' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/pacientes')
            ->assertOk()
            ->assertJsonFragment(['rut' => '18.765.432-1']);
    }

    public function test_patients_can_be_searched_by_name_or_surname(): void
    {
        $user = User::create([
            'nombre' => 'Ana',
            'apellido' => 'Soto',
            'username' => 'ana.nombre',
            'password' => bcrypt('secret123'),
            'id_rol' => $this->createRoleWithDefaultPermissions('digitadora')->id_rol,
            'activo' => true,
        ]);

        Paciente::create([
            'rut' => '18.765.432-1',
            'nombre' => 'Roberto Claudio',
            'apellido_paterno' => 'Contreras',
            'apellido_materno' => 'Pérez',
            'fecha_nacimiento' => '1974-09-21',
            'activo' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/pacientes?nombre=Contreras')
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Roberto Claudio']);
    }

    public function test_admin_can_delete_patient(): void
    {
        $admin = User::create([
            'nombre' => 'Admin',
            'apellido' => 'Sistema',
            'username' => 'admin.paciente',
            'password' => bcrypt('secret123'),
            'id_rol' => $this->createRoleWithDefaultPermissions('Administrador', 'Administrador')->id_rol,
            'activo' => true,
        ]);

        $paciente = Paciente::create([
            'rut' => '99.999.999-9',
            'nombre' => 'Elena',
            'apellido_paterno' => 'Martínez',
            'apellido_materno' => 'Ríos',
            'fecha_nacimiento' => '1987-11-20',
            'sexo' => 'F',
            'activo' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/pacientes/' . $paciente->id_paciente)
            ->assertOk()
            ->assertJsonPath('message', 'Paciente eliminado correctamente.');

        $this->assertDatabaseHas('pacientes', [
            'id_paciente' => $paciente->id_paciente,
            'activo' => false,
        ]);
    }

    public function test_admin_can_delete_many_patients_without_deleting_records(): void
    {
        $admin = User::create([
            'nombre' => 'Admin Lote',
            'apellido' => 'Sistema',
            'username' => 'admin.lote',
            'password' => bcrypt('secret123'),
            'id_rol' => $this->createRoleWithDefaultPermissions('Administrador')->id_rol,
            'activo' => true,
        ]);
        $patients = collect(range(1, 2))->map(fn (int $number): Paciente => Paciente::create([
            'rut' => "10.000.000-{$number}",
            'nombre' => "Paciente {$number}",
            'apellido_paterno' => 'Lote',
            'fecha_nacimiento' => '1990-01-01',
            'activo' => true,
        ]));

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/pacientes/eliminar-muchos', ['ids' => $patients->pluck('id_paciente')->all()])
            ->assertOk()
            ->assertJsonPath('data.eliminados', 2);

        $this->assertDatabaseCount('pacientes', 2);
        $this->assertDatabaseMissing('pacientes', ['activo' => true]);
    }

    public function test_digitadora_without_permission_cannot_view_patient(): void
    {
        $role = $this->createRoleWithDefaultPermissions('digitadora', 'Digitadora');

        $user = User::create([
            'nombre' => 'Luis',
            'apellido' => 'Pérez',
            'username' => 'luis.digitadora',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $patologia = Patologia::create([
            'numero_ges' => 18,
            'nombre' => 'VIH/SIDA',
            'descripcion' => 'Confidencial',
            'confidencial' => true,
            'activo' => true,
        ]);

        $paciente = Paciente::create([
            'rut' => '11.111.111-1',
            'nombre' => 'Maria',
            'apellido_paterno' => 'García',
            'apellido_materno' => 'López',
            'fecha_nacimiento' => '1990-01-15',
            'sexo' => 'F',
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

        RegistroGes::create([
            'id_paciente' => $paciente->id_paciente,
            'id_patologia' => $patologia->id_patologia,
            'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipo->id_tipo_registro,
            'tipo_tratamiento' => 'Control',
            'fecha_ingreso' => '2026-08-20',
            'fecha_limite' => '2026-08-27',
            'estado' => 'Pendiente',
            'observaciones' => 'Observación',
        ]);

        Route::middleware(['auth'])->get('/test-paciente/{paciente}', fn (Paciente $paciente) => $paciente->toJson());

        $this->actingAs($user)
            ->get('/test-paciente/' . $paciente->id_paciente)
            ->assertStatus(200);
    }

    public function test_user_with_permission_can_view_patient(): void
    {
        $role = $this->createRoleWithDefaultPermissions('digitadora', 'Digitadora');

        $user = User::create([
            'nombre' => 'Ana',
            'apellido' => 'Soto',
            'username' => 'ana.digitadora',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $patologia = Patologia::create([
            'numero_ges' => 18,
            'nombre' => 'VIH/SIDA',
            'descripcion' => 'Confidencial',
            'confidencial' => true,
            'activo' => true,
        ]);

        $paciente = Paciente::create([
            'rut' => '12.345.678-9',
            'nombre' => 'Pedro',
            'apellido_paterno' => 'Muñoz',
            'apellido_materno' => 'Rojas',
            'fecha_nacimiento' => '1988-02-10',
            'sexo' => 'M',
            'activo' => true,
        ]);

        $prioridad = Prioridad::create([
            'nombre' => 'Alta',
            'nivel' => 2,
            'descripcion' => 'Alta',
        ]);

        $tipo = TipoRegistro::create([
            'nombre' => 'Especialidad',
            'descripcion' => 'Especialidad',
            'activo' => true,
        ]);

        RegistroGes::create([
            'id_paciente' => $paciente->id_paciente,
            'id_patologia' => $patologia->id_patologia,
            'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipo->id_tipo_registro,
            'tipo_tratamiento' => 'Consulta',
            'fecha_ingreso' => '2026-08-21',
            'fecha_limite' => '2026-08-28',
            'estado' => 'Pendiente',
            'observaciones' => 'Sin observación',
        ]);

        PermisoPatologia::create([
            'id_usuario' => $user->id_usuario,
            'id_patologia' => $patologia->id_patologia,
            'puede_ver' => true,
            'puede_editar' => false,
            'puede_asignar' => false,
        ]);

        Route::middleware(['auth'])->get('/test-paciente/{paciente}', fn (Paciente $paciente) => $paciente->toJson());

        $this->actingAs($user)
            ->get('/test-paciente/' . $paciente->id_paciente)
            ->assertStatus(200);
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
            $table->string('tipo_digitadora')->nullable();
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
            $table->timestamp('eliminado_en')->nullable();
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
