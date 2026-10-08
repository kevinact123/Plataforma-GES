<?php

namespace Tests\Feature;

use App\Models\DocumentoGeneral;
use App\Models\Paciente;
use App\Models\Patologia;
use App\Models\Prioridad;
use App\Models\RegistroGes;
use App\Models\TipoRegistro;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentIngestionApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createIngestionSchema();
        $this->createRolePermissionSchema();
        $this->createDocumentTables();

        // Crear el rol admin
        $rolAdmin = \App\Models\Rol::create([
            'id_rol' => 1,
            'nombre' => 'admin',
            'descripcion' => 'Administrador del sistema',
        ]);
        $this->grantDefaultRolePermissions($rolAdmin);

        // Crear usuario autenticado
        $this->usuario = User::create([
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'username' => 'jpérez',
            'password' => bcrypt('password123'),
            'id_rol' => $rolAdmin->id_rol,
            'activo' => true,
        ]);

        // Crear datos de prueba
        $this->createTestData();

        // Usar el sistema de archivos fake
        Storage::fake('local');
    }

    private function createTestData(): void
    {
        // Crear patologías
        Patologia::create([
            'id_patologia' => 1,
            'numero_ges' => 1,
            'nombre' => 'Apendicitis',
            'activo' => true,
        ]);

        // Crear prioridades
        Prioridad::create([
            'id_prioridad' => 1,
            'nombre' => 'Urgente',
            'nivel' => 1,
        ]);

        // Crear tipos de registro
        TipoRegistro::create([
            'id_tipo_registro' => 1,
            'nombre' => 'Consulta',
            'activo' => true,
        ]);
    }

    private function createIngestionSchema(): void
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
            $table->string('password');
            $table->boolean('activo')->default(true);
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

        Schema::create('pacientes', function ($table): void {
            $table->id('id_paciente');
            $table->string('rut')->unique();
            $table->string('nombre');
            $table->string('apellido_paterno')->nullable();
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
            $table->string('tipo_tratamiento')->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->date('fecha_limite')->nullable();
            $table->string('estado')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_actualizacion')->nullable();
        });

    }

    /**
     * Test: Ingerir documento CSV válido
     */
    public function test_ingest_csv_document_successfully(): void
    {
        $csvContent = "RUT,Nombre,Apellido Paterno,Apellido Materno,Fecha de Nacimiento,Sexo,ID Patología,ID Prioridad,ID Tipo Registro,Fecha Ingreso\n";
        $csvContent .= "12345678-9,Juan,Pérez,García,1990-01-15,Masculino,1,1,1,2024-01-10\n";

        $archivo = UploadedFile::fake()->createWithContent(
            'pacientes.csv',
            $csvContent,
            'text/csv'
        );

        $response = $this->actingAs($this->usuario)
            ->post('/api/documentacion/ingerir', [
                'documento' => $archivo,
                'nombre' => 'Ingestión de pacientes',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id_documento',
                'nombre',
                'tipo',
                'extension',
                'estado',
                'puede_importar',
                'campos_detectados',
                'datos_previsualizacion',
                'campos_ignorados',
                'errores',
                'duplicados_detectados',
                'advertencias',
            ],
            'message',
        ]);

        $this->assertDatabaseHas('documentos_generales', [
            'nombre' => 'Ingestión de pacientes',
            'estado' => 'aprobado',
        ]);
    }

    /**
     * Test: Obtener previsualización de documento
     */
    public function test_preview_ingested_document(): void
    {
        // Primero ingerir el documento
        $csvContent = "RUT,Nombre,Fecha de Nacimiento\n12345678-9,Juan Pérez,1990-01-15\n";
        $archivo = UploadedFile::fake()->createWithContent('test.csv', $csvContent, 'text/csv');

        $ingestResponse = $this->actingAs($this->usuario)
            ->post('/api/documentacion/ingerir', ['documento' => $archivo]);

        $idDocumento = $ingestResponse->json('data.id_documento');

        // Obtener previsualización
        $response = $this->actingAs($this->usuario)
            ->get("/api/documentacion/{$idDocumento}/preview");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id_documento',
                'nombre',
                'tipo',
                'extension',
                'estado',
                'puede_importar',
                'campos_detectados',
                'datos_previsualizacion',
                'campos_ignorados',
                'errores',
                'duplicados_detectados',
                'advertencias',
            ],
        ]);
    }

    /**
     * Test: Importar documento válido a la base de datos
     */
    public function test_import_document_creates_patient_and_registro(): void
    {
        $csvContent = "RUT,Nombre,Apellido Paterno,Apellido Materno,Fecha de Nacimiento,Sexo,ID Patología,ID Prioridad,ID Tipo Registro,Fecha Ingreso\n";
        $csvContent .= "11111111-1,Carlos,López,Martínez,1985-05-20,Masculino,1,1,1,2024-03-15\n";

        $archivo = UploadedFile::fake()->createWithContent('import.csv', $csvContent, 'text/csv');

        // Ingerir documento
        $ingestResponse = $this->actingAs($this->usuario)
            ->post('/api/documentacion/ingerir', ['documento' => $archivo]);

        $idDocumento = $ingestResponse->json('data.id_documento');

        $ingestResponse->assertCreated()
            ->assertJsonPath('data.estado', 'aprobado');

        // Verificar que se creó el paciente
        $this->assertDatabaseHas('pacientes', [
            'rut' => '11.111.111-1',
            'nombre' => 'Carlos',
        ]);

        // Verificar que se creó el registro GES
        $this->assertDatabaseHas('registros_ges', [
            'id_patologia' => 1,
            'id_prioridad' => 1,
        ]);

        // Verificar que el documento se actualizó
        $this->assertDatabaseHas('documentos_generales', [
            'id_documento' => $idDocumento,
            'estado' => 'aprobado',
        ]);
    }

    /**
     * Test: Detectar duplicados de pacientes
     */
    public function test_detect_duplicate_patients(): void
    {
        // Crear paciente existente
        Paciente::create([
            'rut' => '99999999-9',
            'nombre' => 'Paciente Existente',
            'apellido_paterno' => 'Apellido',
            'fecha_nacimiento' => '1980-01-01',
            'activo' => true,
        ]);

        $csvContent = "RUT,Nombre,Apellido Paterno,Fecha de Nacimiento\n99999999-9,Otro Nombre,Otro Apellido,1980-01-01\n";
        $archivo = UploadedFile::fake()->createWithContent('duplicado.csv', $csvContent, 'text/csv');

        $response = $this->withHeader('Accept', 'application/json')->actingAs($this->usuario)
            ->post('/api/documentacion/ingerir', ['documento' => $archivo]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.duplicados_detectados.2.pacientes_por_rut', function ($duplicates) {
            return is_array($duplicates) && count($duplicates) > 0;
        });
    }

    /**
     * Test: Rechazar importación con errores de validación
     */
    public function test_reject_import_with_validation_errors(): void
    {
        $csvContent = "RUT,Fecha de Nacimiento\n12345678-9,invalid-date\n";
        $archivo = UploadedFile::fake()->createWithContent('invalido.csv', $csvContent, 'text/csv');

        $ingestResponse = $this->actingAs($this->usuario)
            ->post('/api/documentacion/ingerir', ['documento' => $archivo]);

        $idDocumento = $ingestResponse->json('data.id_documento');

        // Intentar importar documento con errores
        $response = $this->actingAs($this->usuario)
            ->post("/api/documentacion/{$idDocumento}/importar");

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'El documento no está listo para importar. Hay errores en la validación.',
        ]);
    }

    /**
     * Test: Usuario sin permisos no puede acceder a previsualización
     */
    public function test_unauthorized_user_cannot_preview_document(): void
    {
        // Crear otro usuario
        $otroUsuario = User::create([
            'nombre' => 'Otro',
            'apellido' => 'Usuario',
            'username' => 'otro',
            'password' => bcrypt('pass'),
            'id_rol' => 1,
            'activo' => true,
        ]);

        $csvContent = "RUT,Nombre\n12345678-9,Test\n";
        $archivo = UploadedFile::fake()->createWithContent('test.csv', $csvContent, 'text/csv');

        $ingestResponse = $this->actingAs($this->usuario)
            ->post('/api/documentacion/ingerir', ['documento' => $archivo]);

        $idDocumento = $ingestResponse->json('data.id_documento');

        // Intentar acceder como otro usuario
        $response = $this->actingAs($otroUsuario)
            ->get("/api/documentacion/{$idDocumento}/preview");

        $response->assertStatus(403);
    }

    /**
     * Test: Documento XLSX es analizado correctamente
     */
    public function test_ingest_xlsx_document(): void
    {
        $xlsxPath = tempnam(sys_get_temp_dir(), 'ges-ingestion-xlsx-');
        $zip = new \ZipArchive();
        $zip->open($xlsxPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet><sheetData/></worksheet>');
        $zip->close();

        $archivo = UploadedFile::fake()->createWithContent(
            'datos.xlsx',
            file_get_contents($xlsxPath),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        unlink($xlsxPath);

        $response = $this->actingAs($this->usuario)
            ->post('/api/documentacion/ingerir', ['documento' => $archivo]);

        // El documento debe ser identificado como XLSX
        $response->assertStatus(201);
        $response->assertJsonPath('data.tipo', 'hoja_calculo');
    }

    /**
     * Test: Obtener historial de análisis
     */
    public function test_get_ingestion_history(): void
    {
        $csvContent = "RUT,Nombre\n12345678-9,Test User\n";
        $archivo = UploadedFile::fake()->createWithContent('test.csv', $csvContent, 'text/csv');

        $ingestResponse = $this->actingAs($this->usuario)
            ->post('/api/documentacion/ingerir', ['documento' => $archivo]);

        $idDocumento = $ingestResponse->json('data.id_documento');

        $response = $this->actingAs($this->usuario)
            ->get("/api/documentacion/{$idDocumento}/historial-ingestion");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id_documento',
                'nombre',
                'estado_actual',
                'ingestion_status',
                'registered_at',
                'analyzed_at',
                'updated_at',
                'status_details',
                'analisis',
                'duplicados',
            ],
        ]);
    }

    /**
     * Test: Usuario inactivo no puede ingerir documentos
     */
    public function test_inactive_user_cannot_ingest(): void
    {
        $usuarioInactivo = User::create([
            'nombre' => 'Inactivo',
            'apellido' => 'Usuario',
            'username' => 'inactivo',
            'password' => bcrypt('pass'),
            'id_rol' => 1,
            'activo' => false,
        ]);

        $csvContent = "RUT,Nombre\n12345678-9,Test\n";
        $archivo = UploadedFile::fake()->createWithContent('test.csv', $csvContent, 'text/csv');

        $response = $this->actingAs($usuarioInactivo)
            ->post('/api/documentacion/ingerir', ['documento' => $archivo]);

        $response->assertStatus(403);
    }

    /**
     * Test: Validar que archivo sea requerido
     */
    public function test_document_file_is_required(): void
    {
        $response = $this->withHeader('Accept', 'application/json')->actingAs($this->usuario)
            ->post('/api/documentacion/ingerir', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('documento');
    }

    /**
     * Test: Validar tamaño máximo de archivo
     */
    public function test_file_size_limit_exceeded(): void
    {
        $largeFile = UploadedFile::fake()->create('large.csv', 25000); // 25 MB, límite es 20 MB

        $response = $this->withHeader('Accept', 'application/json')->actingAs($this->usuario)
            ->post('/api/documentacion/ingerir', ['documento' => $largeFile]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('documento');
    }

    /**
     * Test: Validar tipos MIME permitidos
     */
    public function test_invalid_mime_type_rejected(): void
    {
        $invalidFile = UploadedFile::fake()->create('archivo.exe', 100);

        $response = $this->withHeader('Accept', 'application/json')->actingAs($this->usuario)
            ->post('/api/documentacion/ingerir', ['documento' => $invalidFile]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('documento');
    }
}
