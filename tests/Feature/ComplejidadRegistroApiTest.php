<?php

namespace Tests\Feature;

use App\Models\ComplejidadRegistro;
use App\Models\Patologia;
use App\Models\Prioridad;
use App\Models\RegistroGes;
use App\Models\Rol;
use App\Models\TipoRegistro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ComplejidadRegistroApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createComplexitySchema();
    }

    public function test_can_query_complexity_averages_and_operator_load(): void
    {
        $admin = $this->createUser('admin', 'Admin', 'admin', 'Administrador del sistema');
        $carmen = $this->createUser('digitadora', 'Carmen', 'carmen.avendano', 'Digitadora');
        $carolina = $this->createUser('digitadora', 'Carolina', 'carolina.acuna', 'Digitadora');
        $luciana = $this->createUser('digitadora', 'Luciana', 'luciana', 'Digitadora');
        $sandra = $this->createUser('digitadora', 'Sandra', 'sandra', 'Digitadora');

        $patologiaA = Patologia::create([
            'numero_ges' => 1,
            'nombre' => 'Patología A',
            'descripcion' => 'Patología A',
            'confidencial' => false,
            'activo' => true,
        ]);

        $patologiaB = Patologia::create([
            'numero_ges' => 2,
            'nombre' => 'Patología B',
            'descripcion' => 'Patología B',
            'confidencial' => false,
            'activo' => true,
        ]);

        $prioridad = Prioridad::create([
            'nombre' => 'Urgente',
            'nivel' => 2,
            'descripcion' => 'Urgente',
        ]);

        $tipos = [
            'Prestación Otorgada' => TipoRegistro::create(['nombre' => 'Prestación Otorgada', 'descripcion' => 'Prestación Otorgada', 'activo' => true]),
            'IPD' => TipoRegistro::create(['nombre' => 'IPD', 'descripcion' => 'IPD', 'activo' => true]),
            'Orden de Atención' => TipoRegistro::create(['nombre' => 'Orden de Atención', 'descripcion' => 'Orden de Atención', 'activo' => true]),
            'EGOS y cierres' => TipoRegistro::create(['nombre' => 'EGOS y cierres', 'descripcion' => 'EGOS y cierres', 'activo' => true]),
            'SIC' => TipoRegistro::create(['nombre' => 'SIC', 'descripcion' => 'SIC', 'activo' => true]),
        ];

        foreach ([
            ['usuario' => $carmen, 'tipo' => 'Prestación Otorgada', 'puntaje' => 5],
            ['usuario' => $carmen, 'tipo' => 'IPD', 'puntaje' => 3],
            ['usuario' => $carmen, 'tipo' => 'Orden de Atención', 'puntaje' => 3],
            ['usuario' => $carmen, 'tipo' => 'EGOS y cierres', 'puntaje' => 5],
            ['usuario' => $carmen, 'tipo' => 'SIC', 'puntaje' => 4],
            ['usuario' => $carolina, 'tipo' => 'Prestación Otorgada', 'puntaje' => 4],
            ['usuario' => $carolina, 'tipo' => 'IPD', 'puntaje' => 4],
            ['usuario' => $carolina, 'tipo' => 'Orden de Atención', 'puntaje' => 4],
            ['usuario' => $carolina, 'tipo' => 'EGOS y cierres', 'puntaje' => 4],
            ['usuario' => $carolina, 'tipo' => 'SIC', 'puntaje' => 4],
            ['usuario' => $luciana, 'tipo' => 'Prestación Otorgada', 'puntaje' => 4],
            ['usuario' => $luciana, 'tipo' => 'IPD', 'puntaje' => 2],
            ['usuario' => $luciana, 'tipo' => 'Orden de Atención', 'puntaje' => 3],
            ['usuario' => $luciana, 'tipo' => 'EGOS y cierres', 'puntaje' => 2],
            ['usuario' => $luciana, 'tipo' => 'SIC', 'puntaje' => 4],
            ['usuario' => $sandra, 'tipo' => 'Prestación Otorgada', 'puntaje' => 4],
            ['usuario' => $sandra, 'tipo' => 'IPD', 'puntaje' => 5],
            ['usuario' => $sandra, 'tipo' => 'Orden de Atención', 'puntaje' => 4],
            ['usuario' => $sandra, 'tipo' => 'EGOS y cierres', 'puntaje' => 3],
            ['usuario' => $sandra, 'tipo' => 'SIC', 'puntaje' => 2],
        ] as $item) {
            ComplejidadRegistro::create([
                'id_usuario' => $item['usuario']->id_usuario,
                'id_tipo_registro' => $tipos[$item['tipo']]->id_tipo_registro,
                'puntaje' => $item['puntaje'],
                'observacion' => 'Evaluación registrada',
                'fecha_evaluacion' => now(),
            ]);
        }

        RegistroGes::create([
            'id_paciente' => 1,
            'id_patologia' => $patologiaA->id_patologia,
            'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipos['Prestación Otorgada']->id_tipo_registro,
            'tipo_tratamiento' => 'Control',
            'fecha_ingreso' => '2026-08-20',
            'fecha_limite' => '2026-08-27',
            'estado' => 'Pendiente',
            'observaciones' => 'Registro de prueba',
        ]);

        RegistroGes::create([
            'id_paciente' => 2,
            'id_patologia' => $patologiaB->id_patologia,
            'id_tipo_registro' => $tipos['IPD']->id_tipo_registro,
            'id_prioridad' => $prioridad->id_prioridad,
            'tipo_tratamiento' => 'Seguimiento',
            'fecha_ingreso' => '2026-08-21',
            'fecha_limite' => '2026-08-28',
            'estado' => 'Pendiente',
            'observaciones' => 'Segundo registro',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/complejidad')
            ->assertOk()
            ->assertJsonCount(20, 'data');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/complejidad/promedio-por-tipo')
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Prestación Otorgada'])
            ->assertJsonPath('data.0.promedio', 4.25);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/complejidad/operadores')
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Carmen Test'])
            ->assertJsonPath('data.0.promedio', 4);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/complejidad/patologias')
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Patología A']);
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

    private function createComplexitySchema(): void
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

        Schema::create('complejidad_registro', function ($table): void {
            $table->id('id_complejidad');
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_tipo_registro');
            $table->integer('puntaje');
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_evaluacion')->nullable();
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
