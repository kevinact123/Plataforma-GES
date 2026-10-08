<?php

namespace App\Http\Controllers;

use App\Models\CategoriaDocumento;
use App\Models\DocumentoGeneral;
use App\Models\Paciente;
use App\Models\RegistroGes;
use App\Services\AsignacionService;
use App\Services\DocumentAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentoGeneralController extends Controller
{
    public function __construct(
        private readonly AsignacionService $asignacionService,
        private readonly DocumentAnalysisService $documentAnalysisService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'id_categoria' => ['nullable', 'integer', 'exists:documentacion_categorias,id_categoria'],
            'estado' => ['nullable', Rule::in(DocumentoGeneral::ESTADOS)],
            'etiqueta' => ['nullable', 'string', 'max:50'],
            'id_paciente' => ['nullable', 'integer', 'exists:pacientes,id_paciente'],
            'id_registro' => ['nullable', 'integer', 'exists:registros_ges,id_registro'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $query = DocumentoGeneral::query()
            ->with([
                'usuario:id_usuario,nombre,apellido',
                'categoria:id_categoria,nombre',
                'paciente:id_paciente,rut,nombre,apellido_paterno,apellido_materno',
                'registroGes:id_registro,id_paciente,id_patologia',
                'usuarioAsignado:id_usuario,nombre,apellido',
                'revisor:id_usuario,nombre,apellido',
            ])
            ->where(function ($documentQuery) use ($user): void {
                $documentQuery
                    ->whereNull('id_registro')
                    ->orWhereHas('registroGes', fn ($recordQuery) => $recordQuery->visibleTo($user));
            })
            ->where(function ($documentQuery) use ($user): void {
                $documentQuery
                    ->whereNull('id_paciente')
                    ->orWhereHas('paciente', fn ($patientQuery) => $patientQuery
                        ->where('activo', true)
                        ->where(function ($query) use ($user): void {
                            $query->whereDoesntHave('registrosGes')
                                ->orWhereHas('registrosGes', fn ($recordQuery) => $recordQuery->visibleTo($user));
                        }));
            });

        $query->when($validated['q'] ?? $validated['search'] ?? null, function ($documentQuery, string $search): void {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $documentQuery->where(function ($searchQuery) use ($term): void {
                $searchQuery->where('nombre', 'like', $term)
                    ->orWhere('nombre_original', 'like', $term)
                    ->orWhere('descripcion', 'like', $term)
                    ->orWhere('etiquetas', 'like', $term)
                    ->orWhereHas('categoria', fn ($categoryQuery) => $categoryQuery->where('nombre', 'like', $term));
            });
        });
        $query->when(isset($validated['id_categoria']), fn ($q) => $q->where('id_categoria', $validated['id_categoria']));
        $query->when(isset($validated['estado']), fn ($q) => $q->where('estado', $validated['estado']));
        $query->when(isset($validated['id_paciente']), fn ($q) => $q->where('id_paciente', $validated['id_paciente']));
        $query->when(isset($validated['id_registro']), fn ($q) => $q->where('id_registro', $validated['id_registro']));
        $query->when($validated['etiqueta'] ?? null, fn ($q, $tag) => $q->where('etiquetas', 'like', '%"'.addcslashes($tag, '"%_\\').'"%'));

        $documents = $query->latest('fecha_creacion')->paginate($validated['per_page'] ?? 50);

        return response()->json([
            'data' => $documents->items(),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ],
        ]);
    }

    public function asignarAutomaticamente(Request $request, DocumentoGeneral $documento): JsonResponse
    {
        $this->authorizeDocument($request, $documento);
        $validated = $request->validate([
            'id_registro' => ['nullable', 'integer', 'exists:registros_ges,id_registro'],
        ]);

        $asignado = $this->asignacionService->asignarAutomaticamenteDocumento(
            $request->user(),
            $documento->id_documento,
            $validated['id_registro'] ?? null,
        );

        return response()->json([
            'data' => $asignado->load([
                'usuarioAsignado:id_usuario,nombre,apellido',
                'registroGes:id_registro,id_paciente,id_patologia',
            ]),
            'message' => 'Documento asignado automáticamente correctamente.',
        ]);
    }

    public function categories(): JsonResponse
    {
        CategoriaDocumento::firstOrCreate(
            ['nombre' => 'Pacientes'],
            ['descripcion' => 'Documentación asociada a pacientes.'],
        );
        CategoriaDocumento::firstOrCreate(
            ['nombre' => 'Registros GES'],
            ['descripcion' => 'Documentación asociada a registros GES.'],
        );

        return response()->json([
            'data' => CategoriaDocumento::query()
                ->withCount('documentos')
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'documento' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,gif,txt'],
        ]);

        $archivo = $validated['documento'];

        return response()->json([
            'data' => $this->documentAnalysisService->analyze(
                $archivo->getRealPath(),
                $archivo->getClientOriginalName(),
                $archivo->getMimeType(),
            ),
            'message' => 'Documento analizado sin modificar la base de datos.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateDocument($request, true);
        $this->authorizeAssociations($request, $validated);

        $archivo = $validated['documento'];
        $nombreArchivo = $this->generarNombreArchivo($archivo);
        $ruta = $archivo->storeAs('documentacion', $nombreArchivo, 'local');

        $documento = DocumentoGeneral::create([
            'id_usuario' => $request->user()->getKey(),
            'id_categoria' => $validated['id_categoria'] ?? null,
            'nombre' => $validated['nombre'] ?? $archivo->getClientOriginalName(),
            'descripcion' => $validated['descripcion'] ?? null,
            'etiquetas' => $validated['etiquetas'] ?? [],
            'estado' => $validated['estado'] ?? DocumentoGeneral::ESTADO_PENDIENTE,
            'id_paciente' => $validated['id_paciente'] ?? null,
            'id_registro' => $validated['id_registro'] ?? null,
            'nombre_original' => $archivo->getClientOriginalName(),
            'nombre_archivo' => $nombreArchivo,
            'ruta_archivo' => $ruta,
            'mime_type' => $archivo->getMimeType(),
            'tamanio' => $archivo->getSize(),
        ]);
        if ($documento->estado !== DocumentoGeneral::ESTADO_PENDIENTE) {
            $documento->update([
                'id_usuario_revisor' => $request->user()->getKey(),
                'fecha_revision' => now(),
            ]);
        }

        return response()->json([
            'data' => $documento->load('usuario:id_usuario,nombre,apellido', 'categoria:id_categoria,nombre', 'usuarioAsignado:id_usuario,nombre,apellido'),
            'message' => 'Documento subido correctamente.',
        ], 201);
    }

    public function update(Request $request, DocumentoGeneral $documento): JsonResponse
    {
        $this->authorizeDocument($request, $documento);
        $validated = $this->validateDocument($request, false);
        $this->authorizeAssociations($request, $validated, $documento);

        $oldPath = null;
        if (isset($validated['documento'])) {
            $archivo = $validated['documento'];
            $nombreArchivo = $this->generarNombreArchivo($archivo);
            $ruta = $archivo->storeAs('documentacion', $nombreArchivo, 'local');
            $oldPath = $documento->ruta_archivo;
            $documento->fill([
                'nombre_original' => $archivo->getClientOriginalName(),
                'nombre_archivo' => $nombreArchivo,
                'ruta_archivo' => $ruta,
                'mime_type' => $archivo->getMimeType(),
                'tamanio' => $archivo->getSize(),
            ]);
        }

        $documento->fill(collect($validated)->except('documento')->all());
        if (array_key_exists('estado', $validated) && $documento->isDirty('estado')) {
            if ($documento->estado === DocumentoGeneral::ESTADO_PENDIENTE) {
                $documento->id_usuario_revisor = null;
                $documento->fecha_revision = null;
            } else {
                $documento->id_usuario_revisor = $request->user()->getKey();
                $documento->fecha_revision = now();
            }
        }
        if ((array_key_exists('id_paciente', $validated) || array_key_exists('id_registro', $validated))
            && Schema::hasColumn('documentos_generales', 'id_usuario_asignado')
            && ($documento->isDirty('id_paciente') || $documento->isDirty('id_registro'))) {
            $documento->id_usuario_asignado = null;
            $documento->asignado_por = null;
            $documento->fecha_asignacion = null;
            $documento->estado_asignacion = DocumentoGeneral::ESTADO_ASIGNACION_SIN_ASIGNAR;
        }
        $documento->save();

        if ($oldPath && $oldPath !== $documento->ruta_archivo) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json([
            'data' => $documento->fresh()->load('usuario:id_usuario,nombre,apellido', 'categoria:id_categoria,nombre', 'usuarioAsignado:id_usuario,nombre,apellido'),
            'message' => 'Documento actualizado correctamente.',
        ]);
    }

    public function download(Request $request, DocumentoGeneral $documento): BinaryFileResponse|JsonResponse
    {
        $this->authorizeDocument($request, $documento);
        if (! Storage::disk('local')->exists($documento->ruta_archivo)) {
            return response()->json(['message' => 'El archivo ya no existe en almacenamiento.'], 404);
        }

        return response()->download(
            Storage::disk('local')->path($documento->ruta_archivo),
            $documento->nombre_original,
            ['Content-Type' => $documento->mime_type ?? 'application/octet-stream'],
        );
    }

    public function destroy(Request $request, DocumentoGeneral $documento): JsonResponse
    {
        $this->authorizeDocument($request, $documento);
        Storage::disk('local')->delete($documento->ruta_archivo);
        Storage::disk('local')->delete('documentacion/ingestion/'.$documento->id_documento.'.json');
        $documento->delete();

        return response()->json(['message' => 'Documento eliminado correctamente.']);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100', 'unique:documentacion_categorias,nombre'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'id_categoria_padre' => ['nullable', 'integer', 'exists:documentacion_categorias,id_categoria'],
        ]);

        $category = CategoriaDocumento::create($validated);

        return response()->json(['data' => $category, 'message' => 'Categoría creada correctamente.'], 201);
    }

    public function updateCategory(Request $request, CategoriaDocumento $categoria): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('documentacion_categorias', 'nombre')->ignore($categoria->getKey(), 'id_categoria')],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'id_categoria_padre' => ['nullable', 'integer', 'exists:documentacion_categorias,id_categoria', Rule::notIn([$categoria->getKey()])],
        ]);

        $categoria->update($validated);

        return response()->json(['data' => $categoria, 'message' => 'Categoría actualizada correctamente.']);
    }

    public function destroyCategory(CategoriaDocumento $categoria): JsonResponse
    {
        if ($categoria->documentos()->exists() || $categoria->hijas()->exists()) {
            return response()->json(['message' => 'No se puede eliminar una categoría con documentos o subcategorías.'], 422);
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada correctamente.']);
    }

    private function validateDocument(Request $request, bool $fileRequired): array
    {
        $tags = $request->input('etiquetas');
        if (is_string($tags)) {
            $tags = array_values(array_filter(array_map('trim', explode(',', $tags))));
            $request->merge(['etiquetas' => $tags]);
        }

        return $request->validate([
            'documento' => [$fileRequired ? 'required' : 'nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,gif,txt'],
            'nombre' => ['sometimes', 'nullable', 'string', 'max:255'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'etiquetas' => ['sometimes', 'nullable', 'array', 'max:20'],
            'etiquetas.*' => ['string', 'max:50'],
            'estado' => ['sometimes', Rule::in(DocumentoGeneral::ESTADOS)],
            'id_categoria' => ['sometimes', 'nullable', 'integer', 'exists:documentacion_categorias,id_categoria'],
            'id_paciente' => ['sometimes', 'nullable', 'integer', 'exists:pacientes,id_paciente'],
            'id_registro' => ['sometimes', 'nullable', 'integer', 'exists:registros_ges,id_registro'],
        ]);
    }

    private function authorizeAssociations(Request $request, array $validated, ?DocumentoGeneral $documento = null): void
    {
        if (! $request->user()?->activo) {
            abort(403, 'Tu usuario no está habilitado para gestionar documentación.');
        }

        $pacienteId = array_key_exists('id_paciente', $validated) ? $validated['id_paciente'] : $documento?->id_paciente;
        $registroId = array_key_exists('id_registro', $validated) ? $validated['id_registro'] : $documento?->id_registro;
        $paciente = $pacienteId ? Paciente::find($pacienteId) : null;
        $registro = $registroId ? RegistroGes::find($registroId) : null;

        if ($paciente && $request->user()->cannot('view', $paciente)) {
            abort(403, 'No tienes permiso para asociar documentos a este paciente.');
        }

        if ($registro && $request->user()->cannot('view', $registro)) {
            abort(403, 'No tienes permiso para asociar documentos a este registro GES.');
        }

        if ($paciente && $registro && (int) $registro->id_paciente !== (int) $paciente->id_paciente) {
            abort(422, 'El paciente no corresponde al registro GES seleccionado.');
        }
    }

    private function authorizeDocument(Request $request, DocumentoGeneral $documento): void
    {
        $user = $request->user();
        if (! $user || ! $user->activo) {
            abort(403, 'Tu usuario no está habilitado para consultar documentación.');
        }

        if ($documento->id_registro && (! $documento->registroGes || $user->cannot('view', $documento->registroGes))) {
            abort(403, 'No tienes permiso para consultar este documento.');
        }

        if ($documento->id_paciente && (! $documento->paciente || $user->cannot('view', $documento->paciente))) {
            abort(403, 'No tienes permiso para consultar este documento.');
        }
    }

    private function generarNombreArchivo($archivo): string
    {
        $extension = strtolower((string) $archivo->getClientOriginalExtension());
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME));

        return sprintf('%s-%s.%s', $base ?: 'documento', now()->format('YmdHisv').'-'.bin2hex(random_bytes(4)), $extension ?: 'bin');
    }
}
