<?php

namespace App\Services;

use App\Models\CategoriaDocumento;
use App\Models\DocumentoGeneral;
use App\Models\Paciente;
use App\Models\Patologia;
use App\Models\Prioridad;
use App\Models\RegistroGes;
use App\Models\RegistroGesDocumento;
use App\Models\TipoRegistro;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentIngestionService
{
    private const FECHA_NACIMIENTO_DESCONOCIDA = '1900-01-01';

    public function __construct(
        private readonly DocumentAnalysisService $analysisService,
    ) {}

    /**
     * Registra un documento en la base de datos antes de procesarlo.
     *
     * @return array Información del documento registrado con ID para tracking
     */
    public function registerDocument(
        $uploadedFile,
        User $user,
        ?string $nombre = null,
        ?int $id_categoria = null,
    ): array {
        $nombreArchivo = $this->generateFileName($uploadedFile);
        $ruta = $uploadedFile->storeAs('documentacion', $nombreArchivo, 'local');

        $documento = DocumentoGeneral::create([
            'id_usuario' => $user->id_usuario,
            'id_categoria' => $id_categoria,
            'nombre' => $nombre ?? $uploadedFile->getClientOriginalName(),
            'nombre_original' => $uploadedFile->getClientOriginalName(),
            'nombre_archivo' => $nombreArchivo,
            'ruta_archivo' => $ruta,
            'mime_type' => $uploadedFile->getMimeType(),
            'tamanio' => $uploadedFile->getSize(),
            'estado' => DocumentoGeneral::ESTADO_PENDIENTE,
            'descripcion' => json_encode([
                'ingestion_status' => 'registered',
                'registered_at' => now()->toIso8601String(),
                'file_hash' => hash_file('sha256', $uploadedFile->getRealPath()),
                'analysis' => null,
                'duplicates' => null,
                'import_ready' => false,
            ]),
        ]);

        return [
            'id_documento' => $documento->id_documento,
            'nombre' => $documento->nombre,
            'ruta' => $documento->ruta_archivo,
            'tamanio' => $documento->tamanio,
            'mime_type' => $documento->mime_type,
        ];
    }

    /**
     * Analiza el contenido del documento y detecta duplicados.
     *
     * @return array Información de análisis y duplicados
     */
    public function analyzeDocument(DocumentoGeneral $documento, ?User $usuario = null): array
    {
        $rutaCompleta = Storage::disk('local')->path($documento->ruta_archivo);

        if (! file_exists($rutaCompleta)) {
            return $this->updateDocumentStatus($documento, 'error', [
                'error' => 'El archivo no se encuentra en almacenamiento.',
            ]);
        }

        // Ejecutar análisis
        $analysis = $this->analysisService->analyze(
            $rutaCompleta,
            $documento->nombre_original,
            $documento->mime_type,
        );
        $patientMatch = $this->matchPatientFromDocumentName($documento->nombre_original);
        if ($patientMatch) {
            $analysis['patient_match'] = [
                'id_paciente' => $patientMatch->id_paciente,
                'rut' => $patientMatch->rut,
                'nombre' => trim($patientMatch->nombre.' '.$patientMatch->apellido_paterno.' '.$patientMatch->apellido_materno),
                'origen' => 'nombre_archivo',
            ];
            $analysis['puede_importar'] = true;
            $analysis['errores'] = [];
            $documento->update(['id_paciente' => $patientMatch->id_paciente]);
        }

        // Detectar duplicados por fila sin detener la importación del padrón.
        $duplicatesByRow = [];
        foreach ($analysis['filas'] ?? [] as $index => $row) {
            $duplicates = $this->detectDuplicates($row, $usuario);
            if ($duplicates !== []) {
                $duplicatesByRow[$index + 2] = $duplicates;
            }
        }
        $registeredMetadata = $this->loadMetadata($documento);
        $duplicateFiles = $this->detectDuplicateFiles($documento, $registeredMetadata['file_hash'] ?? null);

        // Guardar el detalle completo fuera de la columna limitada de la tabla.
        $metadatos = [
            'ingestion_status' => 'analyzed',
            'analyzed_at' => now()->toIso8601String(),
            'file_hash' => $registeredMetadata['file_hash'] ?? null,
            'analysis' => $analysis,
            'duplicates' => $duplicatesByRow,
            'duplicate_files' => $duplicateFiles,
            'import_ready' => $analysis['puede_importar'],
        ];

        $this->saveMetadata($documento, $metadatos);

        return $metadatos;
    }

    /**
     * Un archivo con filas tabulares válidas (pacientes y/o registros GES) se importa solo;
     * cualquier otro archivo queda únicamente como documento.
     */
    public function importarSiCorresponde(DocumentoGeneral $documento, User $usuario): ?array
    {
        $analysis = $this->loadMetadata($documento)['analysis'] ?? [];

        if (! ($analysis['puede_importar'] ?? false) || empty($analysis['filas'])) {
            return null;
        }

        return $this->importDocument($documento, $usuario);
    }

    /**
     * Obtiene la previsualización de datos extraídos del documento.
     *
     * @return array Datos para previsualización
     */
    public function getPreview(DocumentoGeneral $documento): array
    {
        $metadatos = $this->loadMetadata($documento);

        if (empty($metadatos['analysis'])) {
            return [
                'id_documento' => $documento->id_documento,
                'estado' => $documento->estado,
                'mensaje' => 'El documento aún no ha sido analizado.',
                'analisis' => null,
            ];
        }

        $analysis = $metadatos['analysis'];
        $duplicates = $metadatos['duplicates'] ?? [];
        $importado = ($metadatos['ingestion_status'] ?? null) === 'success';

        return [
            'id_documento' => $documento->id_documento,
            'nombre' => $documento->nombre,
            'tipo' => $analysis['tipo_documento'] ?? null,
            'extension' => $analysis['extension'] ?? null,
            'estado' => $documento->estado,
            'puede_importar' => ($analysis['puede_importar'] ?? false) && ! $importado,
            'importado' => $importado,
            'resultado_importacion' => $metadatos['status_details'] ?? null,
            'cantidad_filas' => $analysis['cantidad_filas'] ?? 0,
            'paciente_detectado' => $analysis['patient_match'] ?? null,
            'campos_detectados' => $analysis['campos_detectados'] ?? [],
            'datos_previsualizacion' => $this->formatDataForPreview($analysis['datos'] ?? []),
            'filas_previsualizacion' => array_map(
                fn (array $row): array => $this->formatDataForPreview($row),
                $analysis['filas'] ?? [],
            ),
            'campos_ignorados' => $analysis['datos_ignorados'] ?? [],
            'errores' => $analysis['errores'] ?? [],
            'duplicados_detectados' => $duplicates,
            'archivos_duplicados' => $metadatos['duplicate_files'] ?? [],
            'advertencias' => $this->generateWarnings($analysis, $duplicates, $metadatos['duplicate_files'] ?? []),
        ];
    }

    /**
     * Importa el documento confirmado a la base de datos.
     * Crea registros en pacientes, registros_ges, etc. según sea necesario.
     *
     * @return array Resultado de la importación
     */
    public function importDocument(DocumentoGeneral $documento, User $usuario): array
    {
        $metadatos = $this->loadMetadata($documento);

        if (empty($metadatos['analysis'])) {
            return $this->updateDocumentStatus($documento, 'error', [
                'error' => 'El documento no ha sido analizado. No se puede importar.',
            ]);
        }

        $analysis = $metadatos['analysis'];
        $filas = $analysis['filas'] ?? (! empty($analysis['datos']) ? [$analysis['datos']] : []);
        if ($filas === [] && ! empty($analysis['patient_match']['rut'])) {
            $filas = [['rut' => $analysis['patient_match']['rut']]];
        }

        if (! ($analysis['puede_importar'] ?? false) || $filas === []) {
            return $this->updateDocumentStatus($documento, 'error', [
                'error' => 'El documento no está listo para importar. Hay errores en la validación.',
                'errores' => $analysis['errores'] ?? [],
            ]);
        }

        try {
            DB::beginTransaction();

            $pacientes = [];
            $registros = [];
            $fechasCompletadas = 0;
            foreach ($filas as $datos) {
                $paciente = null;
                if (! empty($datos['rut'])) {
                    $missingBirthDate = empty($datos['fecha_nacimiento']);
                    $paciente = $this->resolveOrCreatePatient($datos);
                    if (! $paciente) {
                        throw new \RuntimeException('No se pudo resolver un paciente del documento.');
                    }
                    if ($missingBirthDate && $paciente->wasRecentlyCreated) {
                        $fechasCompletadas++;
                    }
                    $pacientes[$paciente->id_paciente] = $paciente;
                }

                $patologia = null;
                if (! empty($datos['id_patologia'])) {
                    $patologia = Patologia::find($datos['id_patologia']);
                    if (! $patologia) {
                        throw new \RuntimeException('La patología especificada no existe.');
                    }
                    if (! $usuario->puedeEditarPatologia($patologia)) {
                        throw new \RuntimeException('No tienes permiso para alimentar registros de esta patología.');
                    }
                }

                if ($paciente && $patologia && ! empty($datos['fecha_ingreso'])) {
                    $registro = $this->createOrUpdateRegistro($paciente, $patologia, $datos);
                    if (! $registro) {
                        throw new \RuntimeException('No se pudo crear un registro GES.');
                    }
                    $registros[$registro->id_registro] = $registro;
                }
            }

            $firstPaciente = reset($pacientes) ?: null;
            $firstRegistro = reset($registros) ?: null;

            // Actualizar documento con referencias
            $categoriaId = $documento->id_categoria;
            if ($categoriaId === null && $registros !== []) {
                $categoriaId = $this->defaultCategory(true)->id_categoria;
            } elseif ($categoriaId === null && $pacientes !== []) {
                $categoriaId = $this->defaultCategory(false)->id_categoria;
            }
            $documento->update([
                'id_paciente' => $firstPaciente?->id_paciente,
                'id_registro' => $firstRegistro?->id_registro,
                'id_categoria' => $categoriaId,
                'estado' => DocumentoGeneral::ESTADO_APROBADO,
            ]);

            foreach ($registros as $registro) {
                RegistroGesDocumento::create([
                    'id_registro' => $registro->id_registro,
                    'nombre_original' => $documento->nombre_original,
                    'nombre_archivo' => $documento->nombre_archivo,
                    'ruta_archivo' => $documento->ruta_archivo,
                    'mime_type' => $documento->mime_type,
                    'tamanio' => $documento->tamanio,
                    'observaciones' => "Importado desde: {$documento->nombre_original}",
                ]);
            }

            DB::commit();

            return $this->updateDocumentStatus($documento, 'success', [
                'mensaje' => 'Documento importado exitosamente.',
                'pacientes_procesados' => count($pacientes),
                'registros_procesados' => count($registros),
                'fechas_nacimiento_completadas' => $fechasCompletadas,
                'id_paciente' => $firstPaciente?->id_paciente,
                'id_registro' => $firstRegistro?->id_registro,
                'registros_creados' => [
                    'pacientes' => count($pacientes),
                    'registros_ges' => count($registros),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->updateDocumentStatus($documento, 'error', [
                'error' => 'Error durante la importación: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Detecta duplicados potenciales en la base de datos.
     */
    private function detectDuplicates(array $datos, ?User $usuario = null): array
    {
        $duplicates = [];

        // Detectar pacientes duplicados por RUT
        if (! empty($datos['rut']) && Schema::hasTable('pacientes')) {
            $pacienteDuplicado = $this->findPatientByRut($datos['rut']);
            if ($pacienteDuplicado) {
                $duplicates['pacientes_por_rut'] = [[
                    'id_paciente' => $pacienteDuplicado->id_paciente,
                    'nombre' => $pacienteDuplicado->nombre,
                    'apellido_paterno' => $pacienteDuplicado->apellido_paterno,
                    'rut' => $pacienteDuplicado->rut,
                ]];
            }
        }

        // Detectar registros GES duplicados
        if (! empty($datos['id_patologia']) && ! empty($datos['fecha_ingreso']) && Schema::hasTable('registros_ges')) {
            $registrosDuplicados = RegistroGes::query()
                ->when($usuario, fn ($query) => $query->visibleTo($usuario))
                ->where('id_patologia', $datos['id_patologia'])
                ->whereDate('fecha_ingreso', $datos['fecha_ingreso'])
                ->limit(5)
                ->get();

            if ($registrosDuplicados->count() > 0) {
                $duplicates['registros_ges_similares'] = $registrosDuplicados->map(fn (RegistroGes $r) => [
                    'id_registro' => $r->id_registro,
                    'id_paciente' => $r->id_paciente,
                    'id_patologia' => $r->id_patologia,
                    'fecha_ingreso' => $r->fecha_ingreso->toDateString(),
                    'estado' => $r->estado,
                ])->toArray();
            }
        }

        return $duplicates;
    }

    /**
     * Resuelve un paciente existente o lo crea si no existe.
     */
    private function resolveOrCreatePatient(array $datos): ?Paciente
    {
        // Buscar por RUT (clave única)
        if (! empty($datos['rut'])) {
            $rut = $this->analysisService->normalizeRut($datos['rut']);
            $paciente = $this->findPatientByRut($rut);
            if ($paciente) {
                $paciente->rut = $rut;
                $paciente->fill($this->patientAttributes($datos));
                $paciente->save();

                return $paciente;
            }

            // Crear nuevo paciente
            return Paciente::create(array_merge(
                ['rut' => $rut],
                $this->patientAttributes($datos, true),
                ['activo' => true],
            ));
        }

        return null;
    }

    private function findPatientByRut(string $rut): ?Paciente
    {
        $normalizedRut = $this->analysisService->normalizeRut($rut);
        $patient = Paciente::where('rut', $normalizedRut)->first();

        if ($patient) {
            return $patient;
        }

        $body = preg_replace('/[^0-9]/', '', explode('-', $normalizedRut)[0]) ?? '';
        if ($body === '') {
            return null;
        }

        return Paciente::get()->first(fn (Paciente $candidate): bool =>
            (string) preg_replace('/[^0-9]/', '', explode('-', $candidate->rut)[0]) === $body
        );
    }

    private function patientAttributes(array $datos, bool $forCreate = false): array
    {
        $attributes = [];
        foreach (['nombre', 'apellido_paterno', 'apellido_materno', 'fecha_nacimiento', 'sexo'] as $field) {
            if (array_key_exists($field, $datos) && $datos[$field] !== '') {
                $attributes[$field] = $datos[$field];
            }
        }
        if ($forCreate) {
            $attributes['nombre'] ??= 'Sin nombre';
            $attributes['apellido_paterno'] ??= 'Sin apellido';
            $attributes['fecha_nacimiento'] ??= self::FECHA_NACIMIENTO_DESCONOCIDA;
        }

        foreach ([
            'numero_dau',
            'prevision',
            'tipo_fonasa',
            'establecimiento_emision',
            'establecimiento_destino',
            'domicilio',
            'atencion',
            'consultorio',
            'reporte',
            'ges',
            'desc_cie10',
            'forma_pago',
            'servicio_egreso',
            'ingreso',
            'edad',
        ] as $field) {
            if (array_key_exists($field, $datos) && $datos[$field] !== '') {
                $attributes[$field] = $datos[$field];
            }
        }

        return $attributes;
    }

    private function detectDuplicateFiles(DocumentoGeneral $documento, ?string $hash): array
    {
        if (! $hash) {
            return [];
        }

        $duplicates = [];
        foreach (DocumentoGeneral::query()->whereKeyNot($documento->id_documento)->get(['id_documento', 'nombre_original', 'descripcion']) as $other) {
            $metadata = $this->loadMetadata($other);
            if (($metadata['file_hash'] ?? null) === $hash) {
                $duplicates[] = [
                    'id_documento' => $other->id_documento,
                    'nombre' => $other->nombre_original,
                ];
            }
        }

        return $duplicates;
    }

    private function matchPatientFromDocumentName(string $originalName): ?Paciente
    {
        if (! Schema::hasTable('pacientes')) {
            return null;
        }

        $source = $this->normalizePatientText(pathinfo($originalName, PATHINFO_FILENAME));
        if ($source === '') {
            return null;
        }

        foreach (Paciente::query()->where('activo', true)->get(['id_paciente', 'rut', 'nombre', 'apellido_paterno', 'apellido_materno']) as $patient) {
            $tokens = array_filter(array_map(
                fn (?string $value): string => $this->normalizePatientText((string) $value),
                [$patient->nombre, $patient->apellido_paterno, $patient->apellido_materno],
            ));
            $nameTokens = array_filter(explode(' ', $this->normalizePatientText((string) $patient->nombre)), fn (string $token): bool => strlen($token) >= 3);
            $surname = $this->normalizePatientText((string) $patient->apellido_paterno);
            $hasName = collect($nameTokens)->contains(fn (string $token): bool => str_contains($source, $token));
            if ($surname !== '' && $hasName && str_contains($source, $surname)) {
                return $patient;
            }
        }

        return null;
    }

    private function normalizePatientText(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($value))) ?? '';
    }

    /**
     * Crea o actualiza un registro GES.
     */
    private function createOrUpdateRegistro(Paciente $paciente, Patologia $patologia, array $datos): ?RegistroGes
    {
        // Validar que existan los datos mínimos
        if (empty($datos['fecha_ingreso'])) {
            return null;
        }

        // Resolver prioridad
        $prioridad = null;
        if (! empty($datos['id_prioridad'])) {
            $prioridad = Prioridad::find($datos['id_prioridad']);
        }
        if (! $prioridad) {
            $prioridad = Prioridad::first();
        }
        if (! $prioridad) {
            return null;
        }

        // Resolver tipo de registro
        $tipoRegistro = null;
        if (! empty($datos['id_tipo_registro'])) {
            $tipoRegistro = TipoRegistro::find($datos['id_tipo_registro']);
        }
        if (! $tipoRegistro) {
            $tipoRegistro = TipoRegistro::where('activo', true)->first();
        }
        if (! $tipoRegistro) {
            return null;
        }

        return RegistroGes::create([
            'id_paciente' => $paciente->id_paciente,
            'id_patologia' => $patologia->id_patologia,
            'id_prioridad' => $prioridad->id_prioridad,
            'id_tipo_registro' => $tipoRegistro->id_tipo_registro,
            'tipo_tratamiento' => $datos['tipo_tratamiento'] ?? null,
            'fecha_ingreso' => $datos['fecha_ingreso'],
            'fecha_limite' => $datos['fecha_limite'] ?? null,
            'estado' => $datos['estado'] ?? RegistroGes::ESTADO_PENDIENTE,
            'observaciones' => $datos['observaciones'] ?? null,
        ]);
    }

    /**
     * Genera un nombre único para el archivo.
     */
    private function generateFileName($uploadedFile): string
    {
        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME));

        return sprintf(
            '%s-%s.%s',
            $base ?: 'documento',
            now()->format('YmdHisv').'-'.bin2hex(random_bytes(4)),
            $extension ?: 'bin'
        );
    }

    private function defaultCategory(bool $hasRegistro): CategoriaDocumento
    {
        $name = $hasRegistro ? 'Registros GES' : 'Pacientes';

        return CategoriaDocumento::firstOrCreate(
            ['nombre' => $name],
            ['descripcion' => $hasRegistro
                ? 'Documentación asociada a registros GES.'
                : 'Documentación asociada a pacientes.'],
        );
    }

    /**
     * Formatea datos para la previsualización.
     */
    private function formatDataForPreview(array $datos): array
    {
        $formatted = [];
        foreach ($datos as $key => $value) {
            $formatted[] = [
                'campo' => $key,
                'valor' => (string) $value,
                'tipo' => $this->getDataType($value),
            ];
        }

        return $formatted;
    }

    /**
     * Determina el tipo de dato.
     */
    private function getDataType($value): string
    {
        if (is_numeric($value)) {
            return 'numero';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $value)) {
            return 'fecha';
        }

        return 'texto';
    }

    /**
     * Genera advertencias basadas en el análisis.
     */
    private function generateWarnings(array $analysis, array $duplicates, array $duplicateFiles = []): array
    {
        $warnings = [];

        if (! empty($duplicates)) {
            $warnings[] = [
                'tipo' => 'duplicados_detectados',
                'mensaje' => 'Se detectaron posibles registros duplicados en el sistema.',
                'detalles' => array_keys($duplicates),
            ];
        }

        if (! empty($duplicateFiles)) {
            $warnings[] = [
                'tipo' => 'archivo_duplicado',
                'mensaje' => 'Este archivo ya fue cargado anteriormente; se escaneó nuevamente de todas formas.',
                'detalles' => array_column($duplicateFiles, 'nombre'),
            ];
        }

        if (! empty($analysis['patient_match'])) {
            $warnings[] = [
                'tipo' => 'paciente_asociado',
                'mensaje' => 'El protocolo se asociará a la ficha del paciente identificado por el nombre del archivo.',
                'detalles' => [$analysis['patient_match']['nombre']],
            ];
        }

        if (empty($analysis['datos'])) {
            $warnings[] = [
                'tipo' => 'sin_datos',
                'mensaje' => 'No se extrajeron datos del documento.',
            ];
        }

        return $warnings;
    }

    /**
     * Actualiza el estado del documento con metadatos.
     */
    private function updateDocumentStatus(DocumentoGeneral $documento, string $status, array $details): array
    {
        $metadatos = $this->loadMetadata($documento);
        $metadatos['ingestion_status'] = $status;
        $metadatos['status_details'] = $details;
        $metadatos['updated_at'] = now()->toIso8601String();

        $this->saveMetadata($documento, $metadatos);

        return array_merge([
            'id_documento' => $documento->id_documento,
            'status' => $status,
        ], $details);
    }

    private function loadMetadata(DocumentoGeneral $documento): array
    {
        $sidecar = 'documentacion/ingestion/'.$documento->id_documento.'.json';
        if (Storage::disk('local')->exists($sidecar)) {
            $metadata = json_decode(Storage::disk('local')->get($sidecar), true);
            if (is_array($metadata)) {
                return $metadata;
            }
        }

        return $documento->descripcion ? (json_decode($documento->descripcion, true) ?: []) : [];
    }

    private function saveMetadata(DocumentoGeneral $documento, array $metadatos): void
    {
        $sidecar = 'documentacion/ingestion/'.$documento->id_documento.'.json';
        Storage::disk('local')->put($sidecar, json_encode($metadatos, JSON_UNESCAPED_UNICODE));

        $resumen = $metadatos;
        if (isset($resumen['analysis']) && is_array($resumen['analysis'])) {
            unset($resumen['analysis']['filas']);
            unset($resumen['analysis']['datos']);
        }
        if (isset($resumen['duplicates']) && is_array($resumen['duplicates'])) {
            $resumen['duplicates'] = array_keys($resumen['duplicates']);
        }
        if (isset($resumen['status_details']['errores'])) {
            $resumen['status_details']['errores'] = array_keys((array) $resumen['status_details']['errores']);
        }

        $documento->update(['descripcion' => json_encode($resumen, JSON_UNESCAPED_UNICODE)]);
    }
}
