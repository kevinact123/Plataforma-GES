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
use App\Services\AsignacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditoriaRegistroGesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAuditSchema();
    }

    public function test_records_registry_creation_and_assignment_history(): void
    {
        $admin = $this->createUser('admin', 'Admin', 'admin', 'Administrador del sistema');
        $operador = $this->createUser('digitadora', 'Carmen', 'carmen.avendano', 'Digitadora');

        $patologia = Patologia::create([
            'numero_ges' => 201,
            'nombre' => 'Patología auditada',
            'descripcion' => 'Patología para auditoría',
            'confidencial' => false,
            'activo' => true,
        ]);

        PermisoPatologia::create([
            'id_usuario' => $operador->id_usuario,
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
            'observaciones' => 'Registro de prueba',
        ]);

        $this->assertDatabaseHas('historial_registros', [
            'id_registro' => $registro->id_registro,
            'accion' => 'registro_creado',
        ]);

        $service = app(AsignacionService::class);
        $service->asignar($admin, [
            'id_registro' => $registro->id_registro,
            'id_usuario' => $operador->id_usuario,
            'observacion' => 'Asignación de prueba',
        ]);

        $this->assertDatabaseHas('historial_registros', [
            'id_registro' => $registro->id_registro,
            'accion' => 'asignacion',
            'campo_modificado' => 'estado',
            'valor_nuevo' => 'Asignado',
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

    private function createAuditSchema(): void
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

        Schema::create('historial_registros', function ($table): void {
            $table->id('id_historial');
            $table->unsignedBigInteger('id_registro')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('accion');
            $table->string('campo_modificado')->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->timestamp('fecha')->nullable();
            $table->string('ip')->nullable();
        });
    }
}
