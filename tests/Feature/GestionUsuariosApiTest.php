<?php

namespace Tests\Feature;

use App\Models\Patologia;
use App\Models\PermisoPatologia;
use App\Models\Rol;
use App\Models\User;
use App\Services\GestionUsuariosService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GestionUsuariosApiTest extends TestCase
{
    // No se usa RefreshDatabase: el esquema real de "usuarios" se gestiona fuera de las
    // migraciones de Laravel, por lo que creamos aquí el subconjunto de tablas necesario.
    protected function setUp(): void
    {
        parent::setUp();

        $this->createGestionUsuariosTables();
    }

    /**
     * Reproduce la condición de carrera real (p. ej. doble clic en el formulario):
     * dos solicitudes pasan la validación de unicidad casi al mismo tiempo, por lo
     * que el conflicto solo aparece al insertar en la base de datos. Antes de este
     * cambio, el QueryException crudo se propagaba como un 500; ahora el servicio
     * lo traduce a un ValidationException legible.
     */
    public function test_crear_digitadora_rejects_duplicate_username_inserted_after_validation(): void
    {
        $rol = Rol::create(['nombre' => 'digitadora', 'descripcion' => 'Digitadora estándar']);

        User::create([
            'nombre' => 'Alan',
            'apellido' => 'Brito',
            'username' => 'digiprueba',
            'correo' => 'existente@example.com',
            'password' => bcrypt('secret123'),
            'id_rol' => $rol->id_rol,
            'activo' => true,
        ]);

        $service = app(GestionUsuariosService::class);

        try {
            $service->crearDigitadora([
                'nombre' => 'Alan',
                'apellido' => 'Brito',
                'username' => 'digiprueba',
                'correo' => 'armando.canto@redsalud.gob.cl',
                'password' => 'secret123',
                'permisos' => [],
            ]);
            $this->fail('Se esperaba un ValidationException por username duplicado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('username', $exception->errors());
        }
    }

    public function test_crear_digitadora_persists_the_confidentiality_type_separately_from_role(): void
    {
        Rol::create(['nombre' => 'digitadora', 'descripcion' => 'Digitadora estándar']);

        $data = app(GestionUsuariosService::class)->crearDigitadora([
            'nombre' => 'Eva',
            'apellido' => 'Confidencial',
            'username' => 'eva.confidencial',
            'correo' => 'eva.confidencial@example.com',
            'password' => 'secret123',
            'tipo_digitadora' => User::TIPO_DIGITADORA_CONFIDENCIAL,
            'permisos' => [],
        ]);

        $user = User::findOrFail($data['id_usuario']);
        $this->assertSame('digitadora', strtolower($user->rol->nombre));
        $this->assertSame(User::TIPO_DIGITADORA_CONFIDENCIAL, $user->tipo_digitadora);
        $this->assertSame(User::TIPO_DIGITADORA_CONFIDENCIAL, $data['tipo_digitadora']);
        $this->assertSame([], $data['permisos']);
    }

    public function test_updating_type_without_permissions_keeps_existing_explicit_pathology_permissions(): void
    {
        $role = Rol::create(['nombre' => 'digitadora', 'descripcion' => 'Digitadora estándar']);
        $user = User::create([
            'nombre' => 'Iris',
            'apellido' => 'Prueba',
            'username' => 'iris.prueba',
            'correo' => 'iris.prueba@example.com',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);
        $patologia = Patologia::create([
            'numero_ges' => 92,
            'nombre' => 'Patología de prueba',
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

        app(GestionUsuariosService::class)->actualizarDigitadora($user, [
            'tipo_digitadora' => User::TIPO_DIGITADORA_CONFIDENCIAL,
        ]);

        $this->assertDatabaseHas('permisos_patologia', [
            'id_usuario' => $user->id_usuario,
            'id_patologia' => $patologia->id_patologia,
            'puede_ver' => true,
        ]);
    }

    public function test_non_confidential_digitadora_loses_permissions_on_confidential_pathologies(): void
    {
        $role = Rol::create(['nombre' => 'digitadora', 'descripcion' => 'Digitadora']);
        $user = User::create([
            'nombre' => 'Ana',
            'apellido' => 'Cambio',
            'username' => 'ana.cambio',
            'correo' => 'ana.cambio@example.com',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'tipo_digitadora' => User::TIPO_DIGITADORA_CONFIDENCIAL,
            'activo' => true,
        ]);
        $confidencial = Patologia::create(['numero_ges' => 18, 'nombre' => 'VIH/SIDA', 'confidencial' => true, 'activo' => true]);
        $normal = Patologia::create(['numero_ges' => 93, 'nombre' => 'Normal', 'confidencial' => false, 'activo' => true]);
        $permiso = ['puede_ver' => true, 'puede_editar' => true, 'puede_asignar' => false];

        $service = app(GestionUsuariosService::class);
        $service->actualizarDigitadora($user, ['permisos' => [
            ['id_patologia' => $confidencial->id_patologia] + $permiso,
            ['id_patologia' => $normal->id_patologia] + $permiso,
        ]]);
        $this->assertSame(2, $user->permisosPatologia()->count());

        $service->actualizarDigitadora($user, ['tipo_digitadora' => User::TIPO_DIGITADORA_NO_CONFIDENCIAL]);

        $this->assertSame([$normal->id_patologia], $user->permisosPatologia()->pluck('id_patologia')->all());

        $service->actualizarDigitadora($user, ['permisos' => [['id_patologia' => $confidencial->id_patologia] + $permiso]]);
        $this->assertSame(0, $user->permisosPatologia()->count());
    }

    public function test_registered_users_list_includes_administrator_and_digitadoras(): void
    {
        $adminRole = Rol::create(['nombre' => 'Administrador']);
        $digitadoraRole = Rol::create(['nombre' => 'Digitadora']);
        User::create([
            'nombre' => 'Administradora',
            'apellido' => 'Prueba',
            'username' => 'admin.prueba',
            'correo' => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'id_rol' => $adminRole->id_rol,
            'activo' => true,
        ]);
        User::create([
            'nombre' => 'Digitadora',
            'apellido' => 'Prueba',
            'username' => 'dig.prueba',
            'correo' => 'digitadora@example.com',
            'password' => bcrypt('secret123'),
            'id_rol' => $digitadoraRole->id_rol,
            'activo' => true,
        ]);

        $users = app(GestionUsuariosService::class)->usuariosRegistrados();

        $this->assertCount(2, $users);
        $this->assertSame(['Administrador', 'Digitadora'], array_column($users, 'rol'));
        $this->assertSame('Administrador del sistema', $users[0]['nombre']);
        $this->assertNull($users[0]['tipo_digitadora']);
        $this->assertSame(User::TIPO_DIGITADORA_NO_CONFIDENCIAL, $users[1]['tipo_digitadora']);
    }

    private function createGestionUsuariosTables(): void
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
            $table->string('correo')->nullable()->unique();
            $table->string('password');
            $table->boolean('activo')->default(true);
            $table->timestamp('eliminado_en')->nullable();
            $table->unsignedTinyInteger('intentos_fallidos')->default(0);
            $table->timestamp('bloqueado_en')->nullable();
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
    }
}
