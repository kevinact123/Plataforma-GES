<?php

namespace App\Services;

use App\Models\HistorialRegistro;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

class RegistroGesAuditService
{
    public function registrar(
        ?int $idRegistro,
        ?int $idUsuario,
        string $accion,
        ?string $campoModificado = null,
        mixed $valorAnterior = null,
        mixed $valorNuevo = null,
        ?string $ip = null,
    ): void {
        if (!Schema::hasTable('historial_registros')) {
            return;
        }

        HistorialRegistro::create([
            'id_registro' => $idRegistro,
            'id_usuario' => $idUsuario ?? (request()->user()?->id_usuario ?? 0),
            'accion' => $accion,
            'campo_modificado' => $campoModificado,
            'valor_anterior' => $this->normalizarValor($valorAnterior),
            'valor_nuevo' => $this->normalizarValor($valorNuevo),
            'fecha' => now(),
            'ip' => $ip ?? Request::ip(),
        ]);
    }

    private function normalizarValor(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }

        if (is_array($valor)) {
            return json_encode($valor, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        return (string) $valor;
    }
}
