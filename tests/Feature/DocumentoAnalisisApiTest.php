<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentoAnalisisApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ingests_document_and_returns_scan_preview(): void
    {
        Storage::fake('local');
        $role = $this->createRoleWithDefaultPermissions('digitadora', 'Digitadora');
        $user = User::create([
            'nombre' => 'Importadora',
            'apellido' => 'GES',
            'username' => 'importadora.ges',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->post('/api/documentacion/ingerir', [
            'documento' => UploadedFile::fake()->createWithContent(
                'paciente.csv',
                "rut: 11.111.111-1\nnombre: Ana\nhora_cierre_dau: 2026-09-01 07:58:00\nfecha_ingreso: 2026-09-14",
            ),
        ]);
        $response->assertCreated()
            ->assertJsonPath('data.nombre', 'paciente.csv')
            ->assertJsonPath('data.puede_importar', true)
            ->assertJsonPath('data.datos_previsualizacion.0.campo', 'rut');

        $this->assertDatabaseCount('documentos_generales', 1);
        Storage::disk('local')->assertExists($response->json('data.ruta'));
    }

    public function test_scans_xlsx_rows_and_detects_patient_fields(): void
    {
        Storage::fake('local');
        $role = $this->createRoleWithDefaultPermissions('digitadora', 'Digitadora');
        $user = User::create([
            'nombre' => 'Analista Excel',
            'apellido' => 'GES',
            'username' => 'analista.excel',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $xlsxPath = tempnam(sys_get_temp_dir(), 'ges-xlsx-');
        $zip = new \ZipArchive();
        $zip->open($xlsxPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst><si><t>RUT</t></si><si><t>Nombre</t></si><si><t>Fecha Nacimiento</t></si><si><t>Fecha Ingreso</t></si><si><t>11.111.111-1</t></si><si><t>Ana</t></si><si><t>1990-01-01</t></si><si><t>2026-09-14</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row><row r="2"><c r="A2" t="s"><v>4</v></c><c r="B2" t="s"><v>5</v></c><c r="C2" t="s"><v>6</v></c><c r="D2" t="s"><v>7</v></c></row></sheetData></worksheet>');
        $zip->close();

        $response = $this->actingAs($user, 'sanctum')->post('/api/documentacion/ingerir', [
            'documento' => UploadedFile::fake()->createWithContent('pacientes.xlsx', file_get_contents($xlsxPath)),
        ]);
        unlink($xlsxPath);

        $response->assertCreated()
            ->assertJsonPath('data.puede_importar', true)
            ->assertJsonPath('data.datos_previsualizacion.0.valor', '11.111.111-1')
            ->assertJsonPath('data.datos_previsualizacion.1.valor', 'Ana');
    }

    public function test_allows_patient_without_birth_date_when_scanned_file_contains_patient(): void
    {
        Storage::fake('local');
        $role = $this->createRoleWithDefaultPermissions('digitadora', 'Digitadora');
        $user = User::create([
            'nombre' => 'Validadora',
            'apellido' => 'GES',
            'username' => 'validadora.paciente',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/documentacion/analizar', [
            'documento' => UploadedFile::fake()->createWithContent(
                'paciente.csv',
                "RUT,Nombre,Fecha de Nacimiento\n23.261.921-K,Madelaine,",
            ),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.puede_importar', true)
            ->assertJsonPath('data.datos.rut', '23.261.921-K');
    }

    public function test_scans_all_patient_rows_in_csv(): void
    {
        Storage::fake('local');
        $role = $this->createRoleWithDefaultPermissions('digitadora', 'Digitadora');
        $user = User::create([
            'nombre' => 'Validadora',
            'apellido' => 'Lote',
            'username' => 'validadora.lote',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/documentacion/analizar', [
            'documento' => UploadedFile::fake()->createWithContent(
                'pacientes.csv',
                "RUT,Nombre,Sexo\n11.111.111-1,Ana,Femenino\n22.222.222-2,Luis,Masculino",
            ),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.cantidad_filas', 2)
            ->assertJsonCount(2, 'data.filas')
            ->assertJsonPath('data.filas.1.rut', '22.222.222-2');
    }

    public function test_maps_dau_patient_headers_to_existing_fields(): void
    {
        Storage::fake('local');
        $role = $this->createRoleWithDefaultPermissions('digitadora', 'Digitadora');
        $user = User::create([
            'nombre' => 'Validadora',
            'apellido' => 'DAU',
            'username' => 'validadora.dau',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $headers = 'N° DAU,A.PATERNO,A.MATERNO,DOMICILIO,ATENCION,HORA CIERRE DAU,PREVISION,FORMA PAGO,INGRESO,EDAD,CONSULTORIO,REPORTE,GES,DESTINO,DESC.CIE10,SERVICIO EGRE';
        $values = '12345,Pérez,López,Calle 1,Urgencia,01-09-2026 07:58,Fonasa,Gratuito,2026-09-22,46,Central,Reporte,GES,Hospital,CIE10,Alta';
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/documentacion/analizar', [
            'documento' => UploadedFile::fake()->createWithContent('dau.csv', $headers . "\n" . $values),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.datos.numero_dau', '12345')
            ->assertJsonPath('data.datos.apellido_paterno', 'Pérez')
            ->assertJsonPath('data.datos.hora_cierre_dau', '2026-09-01 07:58:00')
            ->assertJsonPath('data.datos_ignorados', []);
    }

    public function test_warns_about_same_file_but_scans_it_again(): void
    {
        Storage::fake('local');
        $role = $this->createRoleWithDefaultPermissions('digitadora', 'Digitadora');
        $user = User::create([
            'nombre' => 'Validadora',
            'apellido' => 'Duplicados',
            'username' => 'validadora.duplicados',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);
        $content = "rut: 11.111.111-1\nnombre: Ana\nhora_cierre_dau: 2026-09-01 07:58:00";

        $this->actingAs($user, 'sanctum')->post('/api/documentacion/ingerir', [
            'documento' => UploadedFile::fake()->createWithContent('padron.csv', $content),
        ]);
        $response = $this->actingAs($user, 'sanctum')->post('/api/documentacion/ingerir', [
            'documento' => UploadedFile::fake()->createWithContent('padron-copia.csv', $content),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.puede_importar', true)
            ->assertJsonPath('data.archivos_duplicados.0.nombre', 'padron.csv');
    }

    public function test_normalizes_rut_without_check_digit(): void
    {
        Storage::fake('local');
        $role = $this->createRoleWithDefaultPermissions('digitadora', 'Digitadora');
        $user = User::create([
            'nombre' => 'Validadora',
            'apellido' => 'RUT',
            'username' => 'validadora.rut',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/documentacion/analizar', [
            'documento' => UploadedFile::fake()->createWithContent(
                'rut.csv',
                "RUT,Nombre\n27582904,Leon",
            ),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.datos.rut', '27.582.904-8');
    }

    public function test_analyzes_csv_without_persisting_and_ignores_unknown_fields(): void
    {
        Storage::fake('local');
        $role = $this->createRoleWithDefaultPermissions('digitadora', 'Digitadora');
        $user = User::create([
            'nombre' => 'Analista',
            'apellido' => 'GES',
            'username' => 'analista.ges',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/documentacion/analizar', [
            'documento' => UploadedFile::fake()->createWithContent(
                'paciente.csv',
                "rut: 11.111.111-1\nnombre: Ana\nhora_cierre_dau: 2026-09-01 07:58:00\ncampo_externo: ignorar\nfecha_ingreso: 2026-09-14",
            ),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.tipo_documento', 'hoja_calculo')
            ->assertJsonPath('data.datos.rut', '11.111.111-1')
            ->assertJsonPath('data.datos.nombre', 'Ana')
            ->assertJsonPath('data.datos_ignorados.0', 'campo_externo')
            ->assertJsonPath('data.puede_importar', true)
            ->assertJsonPath('message', 'Documento analizado sin modificar la base de datos.');

        $this->assertDatabaseCount('documentos_generales', 0);
    }

    public function test_reports_invalid_import_data_without_persisting(): void
    {
        $role = $this->createRoleWithDefaultPermissions('digitadora', 'Digitadora');
        $user = User::create([
            'nombre' => 'Validador',
            'apellido' => 'GES',
            'username' => 'validador.ges',
            'password' => bcrypt('secret123'),
            'id_rol' => $role->id_rol,
            'activo' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/documentacion/analizar', [
            'documento' => UploadedFile::fake()->createWithContent(
                'paciente.csv',
                "rut,nombre,hora_cierre_dau,fecha_ingreso\n11.111.111-1,Ana,2026-09-01 07:58:00,fecha-invalida",
            ),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.datos', [])
            ->assertJsonPath('data.puede_importar', false)
            ->assertJsonStructure(['data' => ['errores' => ['2' => ['fecha_ingreso']]]])
            ->assertJsonPath('data.datos_ignorados', []);
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function ($table): void {
                $table->id('id_rol');
                $table->string('nombre');
                $table->string('descripcion')->nullable();
            });
        }
        if (! Schema::hasTable('usuarios')) {
            Schema::create('usuarios', function ($table): void {
                $table->id('id_usuario');
                $table->unsignedBigInteger('id_rol');
                $table->string('tipo_digitadora')->nullable();
                $table->string('nombre');
                $table->string('apellido');
                $table->string('username')->unique();
                $table->string('password');
                $table->boolean('activo')->default(true);
                $table->timestamp('fecha_creacion')->nullable();
                $table->timestamp('ultimo_acceso')->nullable();
            });
        }
        $this->createRolePermissionSchema();
        $this->createDocumentTables();
    }
}