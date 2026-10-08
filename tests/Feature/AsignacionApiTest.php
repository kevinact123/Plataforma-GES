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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AsignacionApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'notifications',
            'asignaciones',
            'registros_ges',
            'tipos_registro',
            'prioridades',
            'permisos_patologia',
            'complejidad_registro',
            'complejidad_patologia',
            'patologias',
            'usuarios',
            'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }

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
            ->postJson('/api/asignaciones/'.$asignacion->id_asignacion.'/reasignar', [
                'id_usuario' => $operador2->id_usuario,
                'observacion' => 'Reasignación por carga',
            ])
            ->assertOk()
            ->assertJsonPath('data.id_usuario', $operador2->id_usuario);

        $nuevaAsignacion = Asignacion::findOrFail($respuestaReasignacion->json('data.id_asignacion'));

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/asignaciones/'.$nuevaAsignacion->id_asignacion.'/finalizar', [
                'observacion' => 'Trabajo concluido',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'finalizada');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/registros-ges/'.$registro->id_registro.'/historial-asignaciones')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/usuarios/'.$operador2->id_usuario.'/carga')
            ->assertOk()
            ->assertJsonPath('data.total_activas', 0);
    }

    public function test_supervisor_can_assign_without_digitadora_pathology_permissions(): void
    {
        $supervisor = $this->createUser('supervisor', 'Sofía', 'sofia.supervisora', 'Supervisor');
        $digitadora = $this->createUser('digitadora', 'Carmen', 'carmen.digitadora', 'Digitadora');
        $patologia = Patologia::create([
            'numero_ges' => 11,
            'nombre' => 'Patología para supervisión',
            'confidencial' => false,
            'activo' => true,
        ]);
        PermisoPatologia::create([
            'id_usuario' => $digitadora->id_usuario,
            'id_patologia' => $patologia->id_patologia,
            'puede_ver' => true,
            'puede_editar' => false,
            'puede_asignar' => true,
        ]);
        $prioridad = Prioridad::create(['nombre' => 'Normal', 'nivel' => 1]);
        $tipoRegistro = TipoRegistro::create(['nombre' => 'Consulta', 'activo' => true]);
        $registro = RegistroGes::create([
            'id_paciente' => 1,
            'id_patologia' => $patologia->id_patologia,
            'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipoRegistro->id_tipo_registro,
            'estado' => 'Pendiente',
        ]);

        $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/asignaciones', [
                'id_registro' => $registro->id_registro,
                'id_usuario' => $digitadora->id_usuario,
            ])
            ->assertOk()
            ->assertJsonPath('data.id_usuario', $digitadora->id_usuario);
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

    public function test_automatic_assignment_uses_active_digitadora_permission_and_id_tiebreaker(): void
    {
        $admin = $this->createUser('admin', 'Admin', 'admin.auto', 'Administrador del sistema');
        $first = $this->createUser('digitadora', 'Primera', 'first.auto', 'Digitadora');
        $second = $this->createUser('digitadora', 'Segunda', 'second.auto', 'Digitadora');

        $patologia = Patologia::create([
            'numero_ges' => 14,
            'nombre' => 'Patología automática',
            'descripcion' => 'Patología activa',
            'confidencial' => false,
            'activo' => true,
        ]);
        foreach ([$first, $second] as $operador) {
            PermisoPatologia::create([
                'id_usuario' => $operador->id_usuario,
                'id_patologia' => $patologia->id_patologia,
                'puede_ver' => true,
                'puede_editar' => false,
                'puede_asignar' => true,
            ]);
        }

        $prioridad = Prioridad::create(['nombre' => 'Normal', 'nivel' => 1, 'descripcion' => 'Normal']);
        $tipo = TipoRegistro::create(['nombre' => 'Automático', 'descripcion' => 'Automático', 'activo' => true]);
        $ocupado = RegistroGes::create([
            'id_paciente' => 1,
            'id_patologia' => $patologia->id_patologia,
            'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipo->id_tipo_registro,
            'estado' => 'Pendiente',
        ]);
        Asignacion::create([
            'id_registro' => $ocupado->id_registro,
            'id_usuario' => $second->id_usuario,
            'asignado_por' => $admin->id_usuario,
            'fecha_asignacion' => now(),
            'estado' => 'activa',
        ]);
        $pendiente = RegistroGes::create([
            'id_paciente' => 2,
            'id_patologia' => $patologia->id_patologia,
            'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipo->id_tipo_registro,
            'estado' => 'Pendiente',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/asignaciones/automaticas/registro/'.$pendiente->id_registro)
            ->assertOk()
            ->assertJsonPath('data.id_usuario', $first->id_usuario)
            ->assertJsonPath('data.observacion', 'Asignación automática por menor carga activa');
    }

    public function test_confidential_pathology_excludes_non_confidential_digitadora_from_every_assignment_flow(): void
    {
        $admin = $this->createUser('admin', 'Admin', 'admin.conf', 'Administrador del sistema');
        $noConf = $this->createUser('digitadora', 'Nora', 'nora.noconf', 'Digitadora');
        $conf = $this->createUser('digitadora', 'Cata', 'cata.conf', 'Digitadora');
        $noConf->update(['tipo_digitadora' => User::TIPO_DIGITADORA_NO_CONFIDENCIAL]);
        $conf->update(['tipo_digitadora' => User::TIPO_DIGITADORA_CONFIDENCIAL]);

        $patologia = Patologia::create(['numero_ges' => 18, 'nombre' => 'VIH/SIDA', 'confidencial' => true, 'activo' => true]);
        // La NO_CONFIDENCIAL tiene permiso explícito y menor carga: igual debe quedar excluida.
        foreach ([$noConf, $conf] as $operador) {
            PermisoPatologia::create([
                'id_usuario' => $operador->id_usuario,
                'id_patologia' => $patologia->id_patologia,
                'puede_ver' => true,
                'puede_editar' => true,
                'puede_asignar' => true,
            ]);
        }

        $prioridad = Prioridad::create(['nombre' => 'Normal', 'nivel' => 1]);
        $tipo = TipoRegistro::create(['nombre' => 'Consulta', 'activo' => true]);
        $nuevoRegistro = fn (int $paciente) => RegistroGes::create([
            'id_paciente' => $paciente,
            'id_patologia' => $patologia->id_patologia,
            'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipo->id_tipo_registro,
            'estado' => 'Pendiente',
        ]);
        $registro = $nuevoRegistro(1);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/asignaciones', ['id_registro' => $registro->id_registro, 'id_usuario' => $noConf->id_usuario])
            ->assertUnprocessable();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/asignaciones/sugerir', ['id_registro' => $registro->id_registro])
            ->assertOk()
            ->assertJsonPath('operador_recomendado.id_usuario', $conf->id_usuario);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/asignaciones/automaticas/registro/'.$registro->id_registro)
            ->assertOk()
            ->assertJsonPath('data.id_usuario', $conf->id_usuario);

        $asignacion = Asignacion::query()->where('id_registro', $registro->id_registro)->firstOrFail();
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/asignaciones/'.$asignacion->id_asignacion.'/reasignar', ['id_usuario' => $noConf->id_usuario])
            ->assertUnprocessable();

        $conf->update(['tipo_digitadora' => User::TIPO_DIGITADORA_NO_CONFIDENCIAL]);
        $otro = $nuevoRegistro(2);
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/asignaciones/automaticas/registro/'.$otro->id_registro)
            ->assertUnprocessable();
    }

    public function test_scheduled_command_auto_assigns_pending_records_every_five_minutes_respecting_confidentiality(): void
    {
        $this->createUser('admin', 'Admin', 'admin-cmd', 'Administrador del sistema');
        $digitadora = $this->createUser('digitadora', 'Nora', 'nora-cmd', 'Digitadora');
        $normal = Patologia::create(['numero_ges' => 21, 'nombre' => 'Normal', 'confidencial' => false, 'activo' => true]);
        $confidencial = Patologia::create(['numero_ges' => 18, 'nombre' => 'VIH/SIDA', 'confidencial' => true, 'activo' => true]);
        foreach ([$normal, $confidencial] as $patologia) {
            PermisoPatologia::create(['id_usuario' => $digitadora->id_usuario, 'id_patologia' => $patologia->id_patologia, 'puede_ver' => true, 'puede_editar' => true, 'puede_asignar' => true]);
        }
        $prioridad = Prioridad::create(['nombre' => 'Normal', 'nivel' => 1, 'descripcion' => 'Normal']);
        $tipo = TipoRegistro::create(['nombre' => 'Consulta', 'descripcion' => 'Consulta', 'activo' => true]);
        $crear = fn (Patologia $patologia) => RegistroGes::create([
            'id_paciente' => 1, 'id_patologia' => $patologia->id_patologia, 'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipo->id_tipo_registro, 'fecha_ingreso' => '2026-08-20', 'estado' => 'Pendiente',
        ]);
        $registroNormal = $crear($normal);
        $registroConfidencial = $crear($confidencial);

        $this->artisan('asignaciones:automaticas')->assertSuccessful();

        $this->assertSame($digitadora->id_usuario, $registroNormal->asignaciones()->where('estado', 'activa')->value('id_usuario'));
        $this->assertSame(0, $registroConfidencial->asignaciones()->count());

        $this->artisan('asignaciones:automaticas')->assertSuccessful();
        $this->assertSame(1, $registroNormal->asignaciones()->count());

        $evento = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'asignaciones:automaticas'));
        $this->assertNotNull($evento);
        $this->assertSame('*/5 * * * *', $evento->expression);
    }

    public function test_scheduled_command_distributes_records_equitably_and_picks_up_new_ones(): void
    {
        $this->createUser('admin', 'Admin', 'admin-eq', 'Administrador del sistema');
        $digitadoras = collect(['a', 'b', 'c'])->map(fn ($n) => $this->createUser('digitadora', $n, "dig-$n", 'Digitadora'));
        $patologia = Patologia::create(['numero_ges' => 30, 'nombre' => 'Normal', 'confidencial' => false, 'activo' => true]);
        foreach ($digitadoras as $d) {
            PermisoPatologia::create(['id_usuario' => $d->id_usuario, 'id_patologia' => $patologia->id_patologia, 'puede_ver' => true, 'puede_editar' => true, 'puede_asignar' => true]);
        }
        $prioridad = Prioridad::create(['nombre' => 'Normal', 'nivel' => 1, 'descripcion' => 'Normal']);
        $tipo = TipoRegistro::create(['nombre' => 'Consulta', 'descripcion' => 'Consulta', 'activo' => true]);
        $crear = fn () => RegistroGes::create([
            'id_paciente' => 1, 'id_patologia' => $patologia->id_patologia, 'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipo->id_tipo_registro, 'fecha_ingreso' => '2026-08-20', 'estado' => 'Pendiente',
        ]);
        $cargas = fn () => $digitadoras->map(fn ($d) => Asignacion::where('id_usuario', $d->id_usuario)->where('estado', 'activa')->count())->all();

        foreach (range(1, 6) as $i) { $crear(); }
        $this->artisan('asignaciones:automaticas')->assertSuccessful();
        $this->assertSame([2, 2, 2], $cargas());

        foreach (range(1, 4) as $i) { $crear(); }
        $this->artisan('asignaciones:automaticas')->assertSuccessful();
        $c = $cargas();
        $this->assertSame(10, array_sum($c));
        $this->assertLessThanOrEqual(1, max($c) - min($c));
        $this->assertSame(0, RegistroGes::unassigned()->count());
    }

    public function test_digitadora_is_notified_of_manual_and_grouped_automatic_assignments(): void
    {
        $admin = $this->createUser('admin', 'Admin', 'admin-not', 'Administrador del sistema');
        $dig = $this->createUser('digitadora', 'Nora', 'nora-not', 'Digitadora');
        $otra = $this->createUser('digitadora', 'Otra', 'otra-not', 'Digitadora');
        $patologia = Patologia::create(['numero_ges' => 40, 'nombre' => 'Normal', 'confidencial' => false, 'activo' => true]);
        PermisoPatologia::create(['id_usuario' => $dig->id_usuario, 'id_patologia' => $patologia->id_patologia, 'puede_ver' => true, 'puede_editar' => true, 'puede_asignar' => true]);
        $prioridad = Prioridad::create(['nombre' => 'Normal', 'nivel' => 1, 'descripcion' => 'Normal']);
        $tipo = TipoRegistro::create(['nombre' => 'Consulta', 'descripcion' => 'Consulta', 'activo' => true]);
        $crear = fn () => RegistroGes::create([
            'id_paciente' => 1, 'id_patologia' => $patologia->id_patologia, 'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipo->id_tipo_registro, 'fecha_ingreso' => '2026-08-20', 'estado' => 'Pendiente',
        ]);

        $manual = $crear();
        app(\App\Services\AsignacionService::class)->asignar($admin, ['id_registro' => $manual->id_registro, 'id_usuario' => $dig->id_usuario]);
        $this->assertSame(1, $dig->notifications()->count());
        $this->assertSame('manual', $dig->notifications()->first()->data['origen']);

        $crear();
        $crear();
        $this->artisan('asignaciones:automaticas')->assertSuccessful();
        $this->assertSame(2, $dig->notifications()->count());
        $auto = $dig->notifications()->latest()->get()->firstWhere('data.origen', 'automatica');
        $this->assertSame(2, $auto->data['cantidad']);

        $this->assertSame(0, $otra->notifications()->count());

        \Laravel\Sanctum\Sanctum::actingAs($dig);
        $this->getJson('/api/notificaciones')->assertOk()->assertJsonPath('no_leidas', 2);
        $this->postJson('/api/notificaciones/leer-todas')->assertOk();
        $this->assertSame(0, $dig->unreadNotifications()->count());
    }

    private function createUser(string $rolNombre, string $nombre, string $username, string $descripcion): User
    {
        $role = Rol::create([
            'nombre' => match ($rolNombre) {
                'admin' => 'Administrador',
                'supervisor' => 'Supervisor',
                default => 'Digitadora',
            },
            'descripcion' => $descripcion,
        ]);

        $user = User::create([
            'nombre' => $nombre,
            'apellido' => 'Test',
            'username' => $username,
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $permissionNames = match ($rolNombre) {
            'admin' => [
                'ver_registros',
                'crear_registros',
                'editar_registros',
                'asignar_pacientes',
                'reasignar_pacientes',
                'ver_dashboard',
                'administrar_usuarios',
                'administrar_patologias',
                'gestionar_hitos',
            ],
            'supervisor' => ['ver_registros', 'asignar_pacientes', 'reasignar_pacientes', 'ver_dashboard', 'gestionar_hitos'],
            default => ['ver_registros', 'crear_registros', 'editar_registros', 'gestionar_hitos'],
        };

        foreach ($permissionNames as $permissionName) {
            $permissionId = DB::table('permisos')
                ->where('nombre', $permissionName)
                ->value('id_permiso');

            if ($permissionId === null) {
                $permissionId = DB::table('permisos')->insertGetId([
                    'nombre' => $permissionName,
                    'descripcion' => $permissionName,
                ]);
            }

            DB::table('permisos_roles')->insert([
                'id_rol' => $role->id_rol,
                'id_permiso' => $permissionId,
            ]);
        }

        return $user;
    }

    private function createAssignmentSchema(): void
    {
        Schema::create('notifications', function ($table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function ($table): void {
            $table->id('id_rol');
            $table->string('nombre');
            $table->string('descripcion')->nullable();
        });

        Schema::create('permisos', function ($table): void {
            $table->id('id_permiso');
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
        });

        Schema::create('permisos_roles', function ($table): void {
            $table->unsignedBigInteger('id_rol');
            $table->unsignedBigInteger('id_permiso');
            $table->primary(['id_rol', 'id_permiso']);
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
