<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\Patologia;
use App\Models\PermisoPatologia;
use App\Models\Prioridad;
use App\Models\RegistroGes;
use App\Models\Rol;
use App\Models\TipoRegistro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistroGesDocumentacionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRegistroGesSchema();
        Storage::fake('local');
    }

    public function test_can_manage_registro_documents_and_create_delete_record(): void
    {
        $user = $this->createUserWithAccess();

        $paciente = Paciente::create([
            'rut' => '11.111.111-1',
            'nombre' => 'Ana',
            'apellido_paterno' => 'Pérez',
            'apellido_materno' => 'López',
            'fecha_nacimiento' => '1990-01-10',
            'sexo' => 'F',
            'activo' => true,
        ]);

        $patologia = Patologia::query()->latest('id_patologia')->firstOrFail();
        $prioridad = Prioridad::first();
        $tipo = TipoRegistro::first();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/registros-ges', [
                'id_paciente' => $paciente->id_paciente,
                'id_patologia' => $patologia->id_patologia,
                'id_prioridad' => $prioridad->id_prioridad,
                'id_tipo_registro' => $tipo->id_tipo_registro,
                'tipo_tratamiento' => 'Control',
                'fecha_ingreso' => '2026-08-13',
                'fecha_limite' => '2026-08-20',
                'estado' => 'Pendiente',
                'observaciones' => 'Registro de prueba',
            ])
            ->assertCreated()
            ->assertJsonPath('data.id_paciente', $paciente->id_paciente)
            ->assertJsonPath('data.estado', 'Pendiente');

        $registro = RegistroGes::query()->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/registros-ges/' . $registro->id_registro . '/documentos', [
                'observaciones' => 'Factura de ingreso',
                'documento' => UploadedFile::fake()->create('documento.pdf', 125, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.nombre_original', 'documento.pdf')
            ->assertJsonPath('data.observaciones', 'Factura de ingreso');

        $documento = $registro->documentos()->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/registros-ges/' . $registro->id_registro . '/documentos')
            ->assertOk()
            ->assertJsonFragment(['nombre_original' => 'documento.pdf']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/registros-ges/' . $registro->id_registro . '/documentos/' . $documento->id_documento)
            ->assertOk()
            ->assertJsonPath('data.id_documento', $documento->id_documento);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/registros-ges/' . $registro->id_registro . '/documentos/' . $documento->id_documento)
            ->assertOk()
            ->assertJsonPath('message', 'Documento eliminado correctamente.');

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/registros-ges/' . $registro->id_registro)
            ->assertOk()
            ->assertJsonPath('message', 'Registro GES eliminado correctamente.');
    }

    public function test_can_list_previous_registros_for_same_patient(): void
    {
        $user = $this->createUserWithAccess();

        $paciente = Paciente::create([
            'rut' => '22.222.222-2',
            'nombre' => 'Luis',
            'apellido_paterno' => 'García',
            'apellido_materno' => 'Silva',
            'fecha_nacimiento' => '1985-05-12',
            'sexo' => 'M',
            'activo' => true,
        ]);

        $patologia = Patologia::query()->latest('id_patologia')->firstOrFail();
        $prioridad = Prioridad::first();
        $tipo = TipoRegistro::first();

        $registroActual = RegistroGes::create([
            'id_paciente' => $paciente->id_paciente,
            'id_patologia' => $patologia->id_patologia,
            'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipo->id_tipo_registro,
            'tipo_tratamiento' => 'Seguimiento',
            'fecha_ingreso' => '2026-08-15',
            'fecha_limite' => '2026-08-22',
            'estado' => 'Pendiente',
            'observaciones' => 'Registro actual',
        ]);

        $registroAnterior = RegistroGes::create([
            'id_paciente' => $paciente->id_paciente,
            'id_patologia' => $patologia->id_patologia,
            'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipo->id_tipo_registro,
            'tipo_tratamiento' => 'Control',
            'fecha_ingreso' => '2026-08-01',
            'fecha_limite' => '2026-08-08',
            'estado' => 'Completado',
            'observaciones' => 'Registro más antiguo',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/registros-ges/' . $registroActual->id_registro . '/anteriores')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_registro', $registroAnterior->id_registro)
            ->assertJsonPath('data.0.estado', 'Completado');
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
            'puede_editar' => true,
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
            $table->unsignedBigInteger('id_rol');
            $table->string('nombre');
            $table->string('apellido');
            $table->string('username')->unique();
            $table->string('password');
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('ultimo_acceso')->nullable();
        });

        Schema::create('patologias', function ($table): void {
            $table->id('id_patologia');
            $table->integer('numero_ges');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('confidencial')->default(false);
            $table->boolean('activo')->default(true);
        });

        Schema::create('prioridades', function ($table): void {
            $table->id('id_prioridad');
            $table->string('nombre');
            $table->integer('nivel');
            $table->text('descripcion')->nullable();
        });

        Schema::create('tipos_registro', function ($table): void {
            $table->id('id_tipo_registro');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
        });

        Schema::create('pacientes', function ($table): void {
            $table->id('id_paciente');
            $table->string('rut')->unique();
            $table->string('nombre');
            $table->string('apellido_paterno');
            $table->string('apellido_materno')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo', 10)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_registro')->nullable();
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
            $table->string('estado')->default('Pendiente');
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_actualizacion')->nullable();
        });

        Schema::create('permisos_patologia', function ($table): void {
            $table->id('id_permiso_patologia');
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_patologia');
            $table->boolean('puede_ver')->default(false);
            $table->boolean('puede_editar')->default(false);
            $table->boolean('puede_asignar')->default(false);
        });

        Prioridad::create([
            'nombre' => 'Normal',
            'nivel' => 1,
            'descripcion' => 'Normal',
        ]);

        TipoRegistro::create([
            'nombre' => 'Consulta',
            'descripcion' => 'Consulta',
            'activo' => true,
        ]);

        Patologia::create([
            'numero_ges' => 1,
            'nombre' => 'Patología accesible',
            'descripcion' => 'No confidencial',
            'confidencial' => false,
            'activo' => true,
        ]);
    }
}
