<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureConfidentialAccess;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConfidentialAccessSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['hitos', 'asignaciones', 'registros_ges', 'patologias', 'permisos_patologia', 'permisos_roles', 'permisos', 'usuarios', 'roles', 'personal_access_tokens'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('roles', function ($t): void {
            $t->id('id_rol');
            $t->string('nombre');
            $t->string('descripcion')->nullable();
        });
        Schema::create('permisos', function ($t): void {
            $t->id('id_permiso');
            $t->string('nombre')->unique();
            $t->string('descripcion')->nullable();
        });
        Schema::create('permisos_roles', function ($t): void {
            $t->unsignedBigInteger('id_rol');
            $t->unsignedBigInteger('id_permiso');
        });
        Schema::create('usuarios', function ($t): void {
            $t->id('id_usuario');
            $t->unsignedBigInteger('id_rol')->nullable();
            $t->string('tipo_digitadora')->nullable();
            $t->string('nombre');
            $t->string('apellido');
            $t->string('username');
            $t->string('password');
            $t->boolean('activo')->default(true);
            $t->timestamp('fecha_creacion')->nullable();
        });
        Schema::create('personal_access_tokens', function ($t): void {
            $t->id();
            $t->morphs('tokenable');
            $t->text('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('patologias', function ($t): void {
            $t->id('id_patologia');
            $t->unsignedInteger('numero_ges')->nullable();
            $t->string('nombre');
            $t->string('descripcion')->nullable();
            $t->boolean('confidencial')->default(false);
            $t->boolean('activo')->default(true);
        });
        Schema::create('registros_ges', function ($t): void {
            $t->timestamp('eliminado_en')->nullable();
            $t->id('id_registro');
            $t->unsignedBigInteger('id_patologia');
            $t->unsignedBigInteger('id_paciente')->nullable();
        });
        Schema::create('asignaciones', function ($t): void {
            $t->id('id_asignacion');
            $t->unsignedBigInteger('id_registro');
        });
        Schema::dropIfExists('pacientes');
        Schema::create('pacientes', function ($t): void {
            $t->id('id_paciente');
            $t->string('rut')->nullable();
            $t->boolean('activo')->default(true);
        });
        DB::table('pacientes')->insert([['id_paciente' => 10], ['id_paciente' => 11]]);
        Schema::create('hitos', function ($t): void {
            $t->id('id_hito');
            $t->unsignedBigInteger('id_registro');
        });

        DB::table('patologias')->insert([
            ['id_patologia' => 18, 'numero_ges' => 18, 'nombre' => 'VIH/SIDA', 'confidencial' => true],
            ['id_patologia' => 5, 'numero_ges' => 5, 'nombre' => 'Normal', 'confidencial' => false],
        ]);
        DB::table('registros_ges')->insert([
            ['id_registro' => 1, 'id_patologia' => 18, 'id_paciente' => 10],
            ['id_registro' => 2, 'id_patologia' => 5, 'id_paciente' => 11],
        ]);
        DB::table('asignaciones')->insert([['id_registro' => 1], ['id_registro' => 2]]);
        DB::table('hitos')->insert([['id_registro' => 1], ['id_registro' => 2]]);
    }

    public function test_non_confidential_digitadora_gets_403_on_confidential_resources(): void
    {
        $user = $this->makeUser('Digitadora', User::TIPO_DIGITADORA_NO_CONFIDENCIAL);
        $token = $user->createToken('t')->plainTextToken;

        $forbidden = [
            ['GET', '/api/registros-ges/1'],
            ['PUT', '/api/registros-ges/1'],
            ['GET', '/api/registros-ges/1/hitos'],
            ['GET', '/api/registros-ges/1/historial-asignaciones'],
            ['GET', '/api/registros-ges/1/documentos'],
            ['GET', '/api/patologias/18'],
            ['GET', '/api/patologias/18/registros'],
            ['GET', '/api/pacientes/10'],
            ['GET', '/api/pacientes/10/registros-ges'],
            ['POST', '/api/hitos/1/iniciar'],
            ['POST', '/api/asignaciones/1/reasignar'],
            ['POST', '/api/asignaciones', ['id_registro' => 1, 'id_usuario' => 1]],
            ['POST', '/api/registros-ges', ['id_patologia' => 18]],
        ];

        foreach ($forbidden as $case) {
            $this->withToken($token)
                ->json($case[0], $case[1], $case[2] ?? [])
                ->assertForbidden();
        }
    }

    public function test_middleware_lets_authorized_users_and_non_confidential_resources_pass(): void
    {
        $middleware = new EnsureConfidentialAccess;

        $cases = [
            [$this->makeUser('Administrador', null, 'adm'), 1],
            [$this->makeUser('Supervisor', null, 'sup'), 1],
            [$this->makeUser('Digitadora', User::TIPO_DIGITADORA_CONFIDENCIAL, 'dc'), 1],
            [$this->makeUser('Digitadora', User::TIPO_DIGITADORA_NO_CONFIDENCIAL, 'dn'), 2],
        ];

        foreach ($cases as [$user, $id]) {
            $request = Request::create('/api/registros-ges/'.$id, 'GET');
            $request->setUserResolver(fn () => $user);
            $route = new \Illuminate\Routing\Route('GET', '/api/registros-ges/{registro}', fn () => null);
            $route->bind($request);
            $request->setRouteResolver(fn () => $route);

            $response = $middleware->handle($request, fn () => response('ok'));

            $this->assertSame(200, $response->getStatusCode(), $user->username);
        }
    }

    private function makeUser(string $role, ?string $tipo, string $username = 'u'): User
    {
        $rol = Rol::firstOrCreate(['nombre' => $role]);

        foreach (['ver_registros', 'crear_registros', 'editar_registros', 'asignar_pacientes', 'reasignar_pacientes', 'gestionar_hitos'] as $name) {
            $id = DB::table('permisos')->where('nombre', $name)->value('id_permiso')
                ?? DB::table('permisos')->insertGetId(['nombre' => $name]);
            if (! DB::table('permisos_roles')->where(['id_rol' => $rol->id_rol, 'id_permiso' => $id])->exists()) {
                DB::table('permisos_roles')->insert(['id_rol' => $rol->id_rol, 'id_permiso' => $id]);
            }
        }

        return User::create([
            'nombre' => $role,
            'apellido' => 'Test',
            'username' => $username,
            'password' => bcrypt('secret123'),
            'id_rol' => $rol->id_rol,
            'tipo_digitadora' => $tipo,
            'activo' => true,
        ]);
    }
}
