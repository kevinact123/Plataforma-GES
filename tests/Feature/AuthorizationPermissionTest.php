<?php

namespace Tests\Feature;

use App\Models\Patologia;
use App\Models\PermisoPatologia;
use App\Models\RegistroGes;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthorizationPermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('permisos_patologia');
        Schema::dropIfExists('registros_ges');
        Schema::dropIfExists('patologias');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('roles');
        $this->createAuthorizationTables();
    }

    public function test_digitadora_without_permission_cannot_view_confidential_patologia(): void
    {
        $role = Rol::create([
            'nombre' => 'digitadora',
            'descripcion' => 'Digitadora estándar',
        ]);

        $user = User::create([
            'nombre' => 'Ana',
            'apellido' => 'Gomez',
            'username' => 'ana.digitadora',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);
        $this->grantRolePermission($role, 'ver_registros');

        $patologia = Patologia::create([
            'numero_ges' => 18,
            'nombre' => 'VIH/SIDA',
            'descripcion' => 'Patología confidencial',
            'confidencial' => true,
            'activo' => true,
        ]);

        Route::middleware(['auth', 'patologia.autorizacion:view'])->get('/test-patologia/{patologia}', fn () => 'ok');

        $this->actingAs($user)
            ->get('/test-patologia/'.$patologia->id_patologia)
            ->assertStatus(403);
    }

    public function test_admin_can_view_confidential_patologia_without_explicit_permission(): void
    {
        $role = Rol::create([
            'nombre' => 'Administrador',
            'descripcion' => 'Administrador del sistema',
        ]);

        $user = User::create([
            'nombre' => 'Beto',
            'apellido' => 'Admin',
            'username' => 'beto.admin',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);
        $this->grantRolePermission($role, 'ver_registros');

        $patologia = Patologia::create([
            'numero_ges' => 86,
            'nombre' => 'Atención Integral de Salud en Agresión Sexual Aguda',
            'descripcion' => 'Patología confidencial',
            'confidencial' => true,
            'activo' => true,
        ]);

        Route::middleware(['auth', 'patologia.autorizacion:view'])->get('/test-patologia/{patologia}', fn () => 'ok');

        $this->actingAs($user)
            ->get('/test-patologia/'.$patologia->id_patologia)
            ->assertOk();
    }

    public function test_non_confidential_digitadora_cannot_view_confidential_patologia_even_with_explicit_permission(): void
    {
        $role = Rol::create([
            'nombre' => 'digitadora',
            'descripcion' => 'Digitadora estándar',
        ]);

        $user = User::create([
            'nombre' => 'Carla',
            'apellido' => 'digitadora',
            'username' => 'carla.digitadora',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);
        $this->grantRolePermission($role, 'ver_registros');

        $patologia = Patologia::create([
            'numero_ges' => 18,
            'nombre' => 'VIH/SIDA',
            'descripcion' => 'Patología confidencial',
            'confidencial' => true,
            'activo' => true,
        ]);

        PermisoPatologia::create([
            'id_usuario' => $user->id_usuario,
            'id_patologia' => $patologia->id_patologia,
            'puede_ver' => true,
            'puede_editar' => false,
            'puede_asignar' => false,
        ]);

        Route::middleware(['auth', 'patologia.autorizacion:view'])->get('/test-patologia/{patologia}', fn () => 'ok');

        $this->actingAs($user)
            ->get('/test-patologia/'.$patologia->id_patologia)
            ->assertForbidden();
    }

    public function test_non_confidential_digitadora_cannot_edit_confidential_patologia_even_with_explicit_permission(): void
    {
        $role = Rol::create([
            'nombre' => 'digitadora',
            'descripcion' => 'Digitadora estándar',
        ]);

        $user = User::create([
            'nombre' => 'Daniela',
            'apellido' => 'digitadora',
            'username' => 'daniela.digitadora',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $patologia = Patologia::create([
            'numero_ges' => 19,
            'nombre' => 'Patología confidencial con edición',
            'descripcion' => 'Permiso de edición sin vista explícita',
            'confidencial' => true,
            'activo' => true,
        ]);

        PermisoPatologia::create([
            'id_usuario' => $user->id_usuario,
            'id_patologia' => $patologia->id_patologia,
            'puede_ver' => false,
            'puede_editar' => true,
            'puede_asignar' => false,
        ]);

        $this->assertFalse($user->puedeVerPatologia($patologia));
        $this->assertFalse($user->puedeEditarPatologia($patologia));
    }

    public function test_confidential_digitadora_gets_all_actions_for_confidential_pathologies_only(): void
    {
        $role = Rol::create([
            'nombre' => 'digitadora',
            'descripcion' => 'Digitadora confidencial',
        ]);
        $this->grantRolePermission($role, 'ver_registros');

        $user = User::create([
            'nombre' => 'Elena',
            'apellido' => 'Confidencial',
            'username' => 'elena.confidencial',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'tipo_digitadora' => User::TIPO_DIGITADORA_CONFIDENCIAL,
            'activo' => true,
        ]);

        $patologiaConfidencial = Patologia::create([
            'numero_ges' => 90,
            'nombre' => 'Patología confidencial',
            'confidencial' => true,
            'activo' => true,
        ]);
        $patologiaNormal = Patologia::create([
            'numero_ges' => 91,
            'nombre' => 'Patología normal',
            'confidencial' => false,
            'activo' => true,
        ]);

        $this->assertTrue($user->puedeVerPatologia($patologiaConfidencial));
        $this->assertTrue($user->puedeEditarPatologia($patologiaConfidencial));
        $this->assertTrue($user->puedeAsignarPatologia($patologiaConfidencial));
        $this->assertFalse($user->puedeVerPatologia($patologiaNormal));
        $this->assertFalse($user->puedeEditarPatologia($patologiaNormal));
        $this->assertFalse($user->puedeAsignarPatologia($patologiaNormal));
        $this->assertSame(
            [$patologiaConfidencial->id_patologia],
            Patologia::query()->visibleTo($user)->pluck('id_patologia')->all(),
        );
        DB::table('registros_ges')->insert([
            ['id_patologia' => $patologiaConfidencial->id_patologia],
            ['id_patologia' => $patologiaNormal->id_patologia],
        ]);
        $this->assertSame(
            [$patologiaConfidencial->id_patologia],
            RegistroGes::query()->visibleTo($user)->pluck('id_patologia')->all(),
        );
        $this->assertSame(
            [$user->id_usuario],
            User::query()->withAssignmentAccessTo($patologiaConfidencial)->pluck('id_usuario')->all(),
        );
    }

    public function test_confidential_access_matrix_by_role_and_digitadora_type(): void
    {
        $permissions = ['ver_registros', 'editar_registros', 'asignar_pacientes'];
        $permissionIds = [];
        foreach ($permissions as $name) {
            $permissionIds[$name] = DB::table('permisos')->insertGetId(['nombre' => $name, 'descripcion' => $name]);
        }

        $make = function (string $roleName, ?string $tipo) use ($permissionIds): User {
            $role = Rol::firstOrCreate(['nombre' => $roleName]);
            foreach ($permissionIds as $id) {
                DB::table('permisos_roles')->insertOrIgnore(['id_rol' => $role->id_rol, 'id_permiso' => $id]);
            }

            return User::create([
                'nombre' => $roleName,
                'apellido' => (string) $tipo,
                'username' => strtolower($roleName.$tipo),
                'password' => bcrypt('secret123'),
                'id_rol' => $role->id_rol,
                'tipo_digitadora' => $tipo,
                'activo' => true,
            ]);
        };

        $confidencial = Patologia::create(['numero_ges' => 18, 'nombre' => 'VIH/SIDA', 'confidencial' => true, 'activo' => true]);
        $normal = Patologia::create(['numero_ges' => 1, 'nombre' => 'Normal', 'confidencial' => false, 'activo' => true]);
        DB::table('registros_ges')->insert([
            ['id_patologia' => $confidencial->id_patologia],
            ['id_patologia' => $normal->id_patologia],
        ]);

        $admin = $make('Administrador', null);
        $supervisor = $make('Supervisor', null);
        $confidentialDigitadora = $make('Digitadora', User::TIPO_DIGITADORA_CONFIDENCIAL);
        $standardDigitadora = $make('Digitadora', User::TIPO_DIGITADORA_NO_CONFIDENCIAL);

        // Permisos explícitos por patología no deben abrir la confidencial a la NO_CONFIDENCIAL.
        PermisoPatologia::create([
            'id_usuario' => $standardDigitadora->id_usuario,
            'id_patologia' => $confidencial->id_patologia,
            'puede_ver' => true,
            'puede_editar' => true,
            'puede_asignar' => true,
        ]);
        PermisoPatologia::create([
            'id_usuario' => $standardDigitadora->id_usuario,
            'id_patologia' => $normal->id_patologia,
            'puede_ver' => true,
            'puede_editar' => true,
            'puede_asignar' => true,
        ]);

        foreach ([$admin, $supervisor, $confidentialDigitadora] as $allowed) {
            $this->assertTrue($allowed->puedeVerPatologia($confidencial), $allowed->nombre);
            $this->assertTrue($allowed->puedeEditarPatologia($confidencial), $allowed->nombre);
            $this->assertTrue($allowed->puedeAsignarPatologia($confidencial), $allowed->nombre);
        }

        $this->assertFalse($standardDigitadora->puedeVerPatologia($confidencial));
        $this->assertFalse($standardDigitadora->puedeEditarPatologia($confidencial));
        $this->assertFalse($standardDigitadora->puedeAsignarPatologia($confidencial));
        $this->assertTrue($standardDigitadora->puedeVerPatologia($normal));
        $this->assertTrue($standardDigitadora->puedeEditarPatologia($normal));

        $this->assertSame([$normal->id_patologia], Patologia::query()->visibleTo($standardDigitadora)->pluck('id_patologia')->all());
        $this->assertSame([$normal->id_patologia], RegistroGes::query()->visibleTo($standardDigitadora)->pluck('id_patologia')->all());
        $this->assertCount(2, Patologia::query()->visibleTo($supervisor)->get());
        $this->assertCount(2, RegistroGes::query()->visibleTo($supervisor)->get());
        $this->assertCount(2, Patologia::query()->visibleTo($admin)->get());

        $this->assertSame(
            [$confidentialDigitadora->id_usuario],
            User::query()->withAssignmentAccessTo($confidencial)->pluck('id_usuario')->all(),
        );
    }

    public function test_supervisor_role_permission_allows_global_record_visibility(): void
    {
        $role = Rol::create([
            'nombre' => 'Supervisor',
            'descripcion' => 'Supervisión y distribución de carga laboral',
        ]);
        $user = User::create([
            'nombre' => 'Sofía',
            'apellido' => 'Supervisora',
            'username' => 'sofia.supervisora',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);
        $this->grantRolePermission($role, 'ver_registros');

        $patologias = collect([
            ['numero_ges' => 31, 'nombre' => 'GES visible para supervisión'],
            ['numero_ges' => 32, 'nombre' => 'Otra patología visible'],
        ])->map(fn (array $data) => Patologia::create($data + [
            'confidencial' => false,
            'activo' => true,
        ]));

        DB::table('registros_ges')->insert(
            $patologias->map(fn (Patologia $patologia) => ['id_patologia' => $patologia->id_patologia])->all(),
        );

        $this->assertSame(2, Patologia::query()->visibleTo($user)->count());
        $this->assertSame(2, RegistroGes::query()->visibleTo($user)->count());
    }

    private function createAuthorizationTables(): void
    {
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

        Schema::create('registros_ges', function ($table): void {
            $table->timestamp('eliminado_en')->nullable();
            $table->id('id_registro');
            $table->unsignedBigInteger('id_patologia');
        });

        Schema::create('permisos_patologia', function ($table): void {
            $table->id('id_permiso');
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_patologia');
            $table->boolean('puede_ver')->default(false);
            $table->boolean('puede_editar')->default(false);
            $table->boolean('puede_asignar')->default(false);
        });
    }

    private function grantRolePermission(Rol $role, string $permission): void
    {
        $permissionId = DB::table('permisos')->insertGetId([
            'nombre' => $permission,
            'descripcion' => $permission,
        ]);

        DB::table('permisos_roles')->insert([
            'id_rol' => $role->id_rol,
            'id_permiso' => $permissionId,
        ]);
    }
}
