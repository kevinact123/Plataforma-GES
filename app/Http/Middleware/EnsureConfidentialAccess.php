<?php

namespace App\Http\Middleware;

use App\Models\Asignacion;
use App\Models\DocumentoGeneral;
use App\Models\Hito;
use App\Models\Patologia;
use App\Models\RegistroGes;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Barrera central: un usuario sin acceso a información confidencial
 * (Digitadora NO_CONFIDENCIAL) recibe 403 si la ruta o el cuerpo de la petición
 * referencian cualquier recurso ligado a una patología confidencial.
 */
class EnsureConfidentialAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->puedeAccederAConfidenciales()) {
            return $next($request);
        }

        if ($this->referenciaConfidencial($request)) {
            return response()->json([
                'message' => 'No tienes permiso para acceder a información confidencial.',
            ], 403);
        }

        return $next($request);
    }

    private function referenciaConfidencial(Request $request): bool
    {
        $route = $request->route();
        $enDocumentacion = str_contains($request->path(), 'documentacion');

        foreach ((array) ($route?->parameters() ?? []) as $name => $value) {
            $id = is_object($value) ? $value->getKey() : $value;

            $confidencial = match ($name) {
                'registro' => $this->registroConfidencial($id),
                'patologia' => $this->patologiaConfidencial($id),
                'paciente' => $this->pacienteSoloConfidencial($id),
                'idAsignacion' => $this->asignacionConfidencial($id),
                'idHito' => $this->hitoConfidencial($id),
                'documento' => $enDocumentacion && $this->documentoConfidencial($id),
                default => false,
            };

            if ($confidencial) {
                return true;
            }
        }

        return $this->registroConfidencial($request->input('id_registro'))
            || $this->patologiaConfidencial($request->input('id_patologia'))
            || $this->patologiaConfidencial($request->input('id_patologia_asociada'))
            || $this->pacienteSoloConfidencial($request->input('id_paciente'));
    }

    private function patologiaConfidencial(mixed $id): bool
    {
        return is_numeric($id)
            && Patologia::query()->whereKey((int) $id)->where('confidencial', true)->exists();
    }

    private function registroConfidencial(mixed $id): bool
    {
        if (! is_numeric($id)) {
            return false;
        }

        return RegistroGes::query()
            ->whereKey((int) $id)
            ->whereHas('patologia', fn ($q) => $q->where('confidencial', true))
            ->exists();
    }

    /** Un paciente se bloquea solo si todos sus registros son confidenciales. */
    private function pacienteSoloConfidencial(mixed $id): bool
    {
        if (! is_numeric($id)) {
            return false;
        }

        $registros = RegistroGes::query()->where('id_paciente', (int) $id);

        return (clone $registros)->exists()
            && ! (clone $registros)
                ->whereDoesntHave('patologia', fn ($q) => $q->where('confidencial', true))
                ->exists();
    }

    private function asignacionConfidencial(mixed $id): bool
    {
        if (! is_numeric($id)) {
            return false;
        }

        $asignacion = Asignacion::query()->find((int) $id);

        return $asignacion !== null && $this->registroConfidencial($asignacion->id_registro);
    }

    private function hitoConfidencial(mixed $id): bool
    {
        if (! is_numeric($id)) {
            return false;
        }

        $hito = Hito::query()->find((int) $id);

        return $hito !== null && $this->registroConfidencial($hito->id_registro);
    }

    private function documentoConfidencial(mixed $id): bool
    {
        if (! is_numeric($id)) {
            return false;
        }

        $doc = DocumentoGeneral::query()->find((int) $id);
        if ($doc === null) {
            return false;
        }

        return $this->registroConfidencial($doc->id_registro)
            || ($doc->id_registro === null && $this->pacienteSoloConfidencial($doc->id_paciente));
    }
}
