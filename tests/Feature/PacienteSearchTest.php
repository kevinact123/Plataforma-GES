<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PacienteSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createDocumentTables();

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
        Schema::create('registros_ges', function ($table): void {
            $table->timestamp('eliminado_en')->nullable();
            $table->id('id_registro');
            $table->unsignedBigInteger('id_paciente');
            $table->unsignedBigInteger('id_patologia');
            $table->unsignedBigInteger('id_prioridad');
            $table->unsignedBigInteger('id_tipo_registro');
            $table->string('estado')->nullable();
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_actualizacion')->nullable();
        });
    }

    public function test_patients_search_matches_prefixes_of_name_and_partial_rut(): void
    {
        $rol = Rol::create(['nombre' => 'Administrador']);
        $permiso = DB::table('permisos')->insertGetId(['nombre' => 'ver_registros']);
        DB::table('permisos_roles')->insert(['id_rol' => $rol->id_rol, 'id_permiso' => $permiso]);
        $user = User::create(['nombre' => 'Ana', 'apellido' => 'Soto', 'username' => 'ana-prefijo', 'password' => bcrypt('secret123'), 'id_rol' => $rol->id_rol, 'activo' => true]);

        foreach ([['18.765.432-1', 'Roberto Claudio', 'Contreras'], ['12.345.678-9', 'Alicia', 'Rojas'], ['9.111.222-3', 'Pedro', 'Araya']] as [$rut, $nombre, $apellido]) {
            Paciente::create(['rut' => $rut, 'nombre' => $nombre, 'apellido_paterno' => $apellido, 'apellido_materno' => 'Gomez', 'fecha_nacimiento' => '1980-01-01', 'activo' => true]);
        }
        $buscar = fn (string $query) => collect($this->actingAs($user, 'sanctum')->getJson('/api/pacientes?'.$query)->assertOk()->json('data'))->pluck('nombre')->sort()->values()->all();

        $this->assertSame(['Alicia', 'Pedro'], $buscar('nombre=a'));
        $this->assertSame(['Alicia'], $buscar('nombre=ali'));
        $this->assertSame(['Alicia'], $buscar('nombre=alicia+ro'));
        $this->assertSame(['Roberto Claudio'], $buscar('nombre=claudio'));
        $this->assertSame([], $buscar('nombre=ntreras'));
        $this->assertSame(['Alicia'], $buscar('rut=12.34'));
        $this->assertSame(['Alicia'], $buscar('rut=1234'));
        $this->assertSame(['Roberto Claudio'], $buscar('rut=18'));
        $this->assertSame(['Pedro'], $buscar('rut=9111222-3'));
    }
}