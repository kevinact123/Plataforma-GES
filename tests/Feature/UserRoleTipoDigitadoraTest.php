<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserRoleTipoDigitadoraTest extends TestCase
{
    private User $admin;

    private Rol $rolAdmin;

    private Rol $rolSupervisor;

    private Rol $rolDigitadora;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('personal_access_tokens');
        Schema::create('personal_access_tokens', function ($table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
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
        });
        Schema::create('usuarios', function ($table): void {
            $table->id('id_usuario');
            $table->unsignedBigInteger('id_rol')->nullable();
            $table->string('tipo_digitadora')->nullable();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('username')->unique();
            $table->string('correo')->nullable()->unique();
            $table->string('password');
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_acceso')->nullable();
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('eliminado_en')->nullable();
        });
        Schema::create('patologias', function ($table): void {
            $table->id('id_patologia');
            $table->integer('numero_ges')->unique();
            $table->string('nombre');
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

        $this->rolAdmin = Rol::create(['nombre' => 'Administrador']);
        $this->rolSupervisor = Rol::create(['nombre' => 'Supervisor']);
        $this->rolDigitadora = Rol::create(['nombre' => 'Digitadora']);

        $permisoId = DB::table('permisos')->insertGetId(['nombre' => 'administrar_usuarios']);
        DB::table('permisos_roles')->insert(['id_rol' => $this->rolAdmin->id_rol, 'id_permiso' => $permisoId]);

        $this->admin = User::create([
            'nombre' => 'Admin', 'apellido' => 'Test', 'username' => 'admin.test',
            'password' => bcrypt('secret123'), 'id_rol' => $this->rolAdmin->id_rol, 'activo' => true,
        ]);
    }

    private function payload(string $username, array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Nuevo',
            'apellido' => 'Usuario',
            'username' => $username,
            'correo' => $username.'@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ], $extra);
    }

    private function crear(array $payload)
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/digitadoras', $payload);
    }

    public function test_administrador_and_supervisor_are_created_with_null_tipo_digitadora(): void
    {
        foreach (['Administrador' => $this->rolAdmin, 'Supervisor' => $this->rolSupervisor] as $nombre => $rol) {
            $this->crear($this->payload('usr-'.strtolower($nombre), ['id_rol' => $rol->id_rol]))
                ->assertCreated()
                ->assertJsonPath('data.rol', $nombre)
                ->assertJsonPath('data.tipo_digitadora', null);

            $this->assertNull(User::where('username', 'usr-'.strtolower($nombre))->value('tipo_digitadora'));
        }
    }

    public function test_digitadora_accepts_only_the_two_valid_types(): void
    {
        foreach ([User::TIPO_DIGITADORA_NO_CONFIDENCIAL, User::TIPO_DIGITADORA_CONFIDENCIAL] as $tipo) {
            $this->crear($this->payload('dig-'.strtolower($tipo), ['id_rol' => $this->rolDigitadora->id_rol, 'tipo_digitadora' => $tipo]))
                ->assertCreated()
                ->assertJsonPath('data.tipo_digitadora', $tipo);
        }

        $this->crear($this->payload('dig-invalida', ['id_rol' => $this->rolDigitadora->id_rol, 'tipo_digitadora' => 'ESPECIAL']))
            ->assertStatus(422)->assertJsonValidationErrors('tipo_digitadora');

        $this->crear($this->payload('dig-nula', ['id_rol' => $this->rolDigitadora->id_rol, 'tipo_digitadora' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('tipo_digitadora');
    }

    public function test_digitadora_personal_data_and_password_can_be_edited(): void
    {
        $id = $this->crear($this->payload('dig-edit', ['id_rol' => $this->rolDigitadora->id_rol]))->json('data.id_usuario');
        $this->crear($this->payload('dig-otra', ['id_rol' => $this->rolDigitadora->id_rol]))->assertCreated();
        $url = '/api/admin/digitadoras/'.$id;
        $admin = fn () => $this->actingAs($this->admin, 'sanctum');

        $admin()->putJson($url, ['nombre' => 'Ana', 'apellido' => 'Pérez', 'username' => 'ana-perez', 'correo' => 'ana@example.com', 'password' => 'nuevaclave1', 'password_confirmation' => 'nuevaclave1'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Ana Pérez')
            ->assertJsonPath('data.username', 'ana-perez');

        $user = User::findOrFail($id);
        $this->assertSame('ana@example.com', $user->correo);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('nuevaclave1', $user->password));

        $admin()->putJson($url, ['password' => null, 'nombre' => 'Ana María'])->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('nuevaclave1', User::findOrFail($id)->password));

        $admin()->putJson($url, ['username' => 'dig-otra'])->assertStatus(422)->assertJsonValidationErrors('username');
        $admin()->putJson($url, ['password' => 'nuevaclave2', 'password_confirmation' => 'distinta'])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_digitadora_can_be_deleted_but_other_roles_cannot(): void
    {
        $id = $this->crear($this->payload('dig-borrar', ['id_rol' => $this->rolDigitadora->id_rol]))->json('data.id_usuario');
        $supervisorId = $this->crear($this->payload('sup-intocable', ['id_rol' => $this->rolSupervisor->id_rol]))->json('data.id_usuario');

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/admin/digitadoras/'.$supervisorId)->assertStatus(422);
        $this->assertNotNull(User::find($supervisorId));

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/admin/digitadoras/'.$id)->assertOk();
        $this->assertNotNull(User::find($id)->eliminado_en);
        $this->assertFalse(User::find($id)->activo);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/digitadoras')->assertJsonMissing(['id_usuario' => $id]);
    }

    public function test_digitadora_without_explicit_type_defaults_to_non_confidential(): void
    {
        $this->crear($this->payload('dig-defecto', ['id_rol' => $this->rolDigitadora->id_rol]))
            ->assertCreated()
            ->assertJsonPath('data.tipo_digitadora', User::TIPO_DIGITADORA_NO_CONFIDENCIAL);
    }

    public function test_non_digitadora_roles_reject_a_tipo_digitadora_or_pathology_permissions(): void
    {
        foreach ([$this->rolAdmin, $this->rolSupervisor] as $rol) {
            foreach ([User::TIPO_DIGITADORA_NO_CONFIDENCIAL, User::TIPO_DIGITADORA_CONFIDENCIAL] as $tipo) {
                $this->crear($this->payload('inv-'.$rol->id_rol.strtolower($tipo), ['id_rol' => $rol->id_rol, 'tipo_digitadora' => $tipo]))
                    ->assertStatus(422)->assertJsonValidationErrors('tipo_digitadora');
            }
        }

        $patologia = DB::table('patologias')->insertGetId(['numero_ges' => 1, 'nombre' => 'P1']);
        $this->crear($this->payload('inv-permisos', ['id_rol' => $this->rolSupervisor->id_rol, 'permisos' => [['id_patologia' => $patologia, 'puede_ver' => true]]]))
            ->assertStatus(422)->assertJsonValidationErrors('permisos');

        $this->assertDatabaseCount('usuarios', 1);
    }

    public function test_unknown_role_is_rejected_and_non_digitadora_cannot_get_a_type_by_update(): void
    {
        $this->crear($this->payload('rol-falso', ['id_rol' => 999]))->assertStatus(422)->assertJsonValidationErrors('id_rol');

        $supervisor = User::create([
            'nombre' => 'Sup', 'apellido' => 'Test', 'username' => 'sup.test',
            'password' => bcrypt('secret123'), 'id_rol' => $this->rolSupervisor->id_rol, 'activo' => true,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/digitadoras/'.$supervisor->id_usuario, ['tipo_digitadora' => User::TIPO_DIGITADORA_CONFIDENCIAL])
            ->assertStatus(422);

        $this->assertNull($supervisor->fresh()->tipo_digitadora);
    }

    public function test_model_never_persists_an_invalid_role_type_combination(): void
    {
        $supervisor = User::create([
            'nombre' => 'Sup', 'apellido' => 'Test', 'username' => 'sup.modelo',
            'password' => bcrypt('secret123'), 'id_rol' => $this->rolSupervisor->id_rol,
            'tipo_digitadora' => User::TIPO_DIGITADORA_CONFIDENCIAL, 'activo' => true,
        ]);
        $this->assertNull($supervisor->fresh()->tipo_digitadora);

        $this->expectException(\InvalidArgumentException::class);
        User::create([
            'nombre' => 'Dig', 'apellido' => 'Test', 'username' => 'dig.modelo',
            'password' => bcrypt('secret123'), 'id_rol' => $this->rolDigitadora->id_rol,
            'tipo_digitadora' => 'ESPECIAL', 'activo' => true,
        ]);
    }
}
