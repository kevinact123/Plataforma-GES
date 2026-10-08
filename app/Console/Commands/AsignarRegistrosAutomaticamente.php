<?php

namespace App\Console\Commands;

use App\Models\RegistroGes;
use App\Models\User;
use App\Services\AsignacionService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class AsignarRegistrosAutomaticamente extends Command
{
    protected $signature = 'asignaciones:automaticas';

    protected $description = 'Asigna automáticamente los registros GES pendientes (se ejecuta cada 5 minutos).';

    public function handle(AsignacionService $service): int
    {
        $actor = User::query()
            ->where('activo', true)
            ->whereHas('rol', fn ($query) => $query->whereRaw('LOWER(nombre) = ?', ['administrador']))
            ->orderBy('id_usuario')
            ->first();

        if (! $actor) {
            $this->error('No existe un Administrador activo para ejecutar la asignación automática.');

            return self::FAILURE;
        }

        $asignados = 0;
        $omitidos = 0;

        $service->iniciarLoteNotificaciones();

        RegistroGes::query()
            ->unassigned()
            ->visibleTo($actor)
            ->orderBy('fecha_ingreso')
            ->pluck('id_registro')
            ->each(function (int $idRegistro) use ($service, $actor, &$asignados, &$omitidos): void {
                try {
                    $service->asignarAutomaticamenteRegistro($actor, $idRegistro);
                    $asignados++;
                } catch (ValidationException) {
                    $omitidos++;
                }
            });

        $service->enviarLoteNotificaciones();

        $this->info("Asignación automática: {$asignados} asignados, {$omitidos} sin digitadora disponible.");

        return self::SUCCESS;
    }
}
