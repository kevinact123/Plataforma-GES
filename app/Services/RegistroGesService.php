<?php

namespace App\Services;

use App\Models\RegistroGes;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class RegistroGesService
{
	public function listar(User $user, array $filters = []): LengthAwarePaginator
	{
		$query = $this->queryVisible($user);

		$this->aplicarFiltros($query, $filters);

		return $query
			->orderByDesc('fecha_ingreso')
			->paginate((int) ($filters['per_page'] ?? 20));
	}

	public function pendientes(User $user, array $filters = []): LengthAwarePaginator
	{
		$filters['estado'] = RegistroGes::ESTADO_PENDIENTE;

		return $this->listar($user, $filters);
	}

	public function asignados(User $user, array $filters = []): LengthAwarePaginator
	{
		$query = $this->queryVisible($user)->assigned();

		$this->aplicarFiltros($query, $filters);

		return $query
			->orderByDesc('fecha_ingreso')
			->paginate((int) ($filters['per_page'] ?? 20));
	}

	public function sinAsignar(User $user, array $filters = []): LengthAwarePaginator
	{
		$query = $this->queryVisible($user)->unassigned();

		$this->aplicarFiltros($query, $filters);

		return $query
			->orderByDesc('fecha_ingreso')
			->paginate((int) ($filters['per_page'] ?? 20));
	}

	public function buscarVisible(User $user, int $idRegistro): ?RegistroGes
	{
		return $this->queryVisible($user)->whereKey($idRegistro)->first();
	}

	private function queryVisible(User $user): Builder
	{
		return RegistroGes::query()
			->visibleTo($user)
			->with([
				'paciente',
				'patologia',
				'prioridad',
				'tipoRegistro',
				'asignaciones',
				'documentos',
			]);
	}

	private function aplicarFiltros(Builder $query, array $filters): void
	{
		if (isset($filters['id_prioridad'])) {
			$query->byPriority((int) $filters['id_prioridad']);
		}

		if (isset($filters['id_patologia'])) {
			$query->byPathology((int) $filters['id_patologia']);
		}

		if (isset($filters['id_tipo_registro'])) {
			$query->byRecordType((int) $filters['id_tipo_registro']);
		}

		if (isset($filters['estado'])) {
			$query->byStatus($filters['estado']);
		}
	}
}
