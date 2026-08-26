@extends('layouts.app')

@section('title', 'Asignaciones')

@section('content')
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
    <h1 class="h2 mb-0"><i class="bi bi-diagram-3 me-2" aria-hidden="true"></i>Asignaciones</h1>
</div>

<div id="assignments-message" class="alert d-none" role="alert"></div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h2 class="h5 mb-0">Nueva digitadora</h2>
    </div>
    <div class="card-body">
        <form id="digitadora-form" class="row g-3">
            <div class="col-md-6"><label class="form-label" for="nombre">Nombre</label><input class="form-control" id="nombre" name="nombre" required maxlength="100"></div>
            <div class="col-md-6"><label class="form-label" for="apellido">Apellido</label><input class="form-control" id="apellido" name="apellido" required maxlength="100"></div>
            <div class="col-md-4"><label class="form-label" for="username">Usuario</label><input class="form-control" id="username" name="username" required maxlength="100" pattern="[A-Za-z0-9_-]+"></div>
            <div class="col-md-4"><label class="form-label" for="password">Contraseña temporal</label><input class="form-control" type="password" id="password" name="password" required minlength="8" maxlength="72"></div>
            <div class="col-md-4"><label class="form-label" for="password_confirmation">Confirmar contraseña</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" maxlength="72"></div>
            <div class="col-12"><h3 class="h6">Permisos por patología</h3><div id="pathology-permissions" class="row g-2"><div class="text-muted">Cargando patologías...</div></div></div>
            <div class="col-12"><button class="btn btn-primary" type="submit" id="create-digitadora"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Crear digitadora</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Digitadoras registradas</h2></div>
    <div class="table-responsive"><table class="table table-striped align-middle mb-0"><thead><tr><th>Nombre</th><th>Usuario</th><th>Estado</th><th>Permisos</th></tr></thead><tbody id="digitadoras-list"><tr><td colspan="4">Cargando...</td></tr></tbody></table></div>
</div>
@endsection

@section('scripts')
<script>
    const adminToken = localStorage.getItem('auth_token');
    const message = document.getElementById('assignments-message');
    const pathologyPermissions = document.getElementById('pathology-permissions');
    const digitadorasList = document.getElementById('digitadoras-list');

    function showMessage(text, type = 'success') {
        message.textContent = text;
        message.className = `alert alert-${type}`;
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value ?? '';
        return element.innerHTML;
    }

    async function apiRequest(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), Authorization: `Bearer ${adminToken}`, ...options.headers },
        });
        const data = await response.json();
        if (response.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.location.href = '{{ route('login') }}';
        }
        if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'No fue posible completar la operación.');
        return data;
    }

    function renderPathologies(pathologies) {
        pathologyPermissions.innerHTML = pathologies.map((pathology) => `<div class="col-lg-6"><div class="border rounded p-3"><strong>${escapeHtml(pathology.numero_ges)} - ${escapeHtml(pathology.nombre)}</strong><div class="mt-2 d-flex gap-3 flex-wrap"><label><input type="checkbox" data-pathology="${pathology.id_patologia}" data-permission="puede_ver"> Ver</label><label><input type="checkbox" data-pathology="${pathology.id_patologia}" data-permission="puede_editar"> Editar</label><label><input type="checkbox" data-pathology="${pathology.id_patologia}" data-permission="puede_asignar"> Asignar</label></div></div></div>`).join('');
    }

    function renderDigitadoras(users) {
        digitadorasList.innerHTML = users.length ? users.map((user) => `<tr><td>${escapeHtml(user.nombre)}</td><td>${escapeHtml(user.username)}</td><td><span class="badge text-bg-success">Activa</span></td><td>${user.permisos.length}</td></tr>`).join('') : '<tr><td colspan="4">No hay digitadoras registradas.</td></tr>';
    }

    async function loadUsers() {
        if (!adminToken) { window.location.href = '{{ route('login') }}'; return; }
        try {
            const data = await apiRequest('{{ url('/api/admin/digitadoras') }}');
            renderPathologies(data.patologias);
            renderDigitadoras(data.data);
        } catch (error) { showMessage(error.message, 'danger'); }
    }

    document.getElementById('digitadora-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(event.target);
        const permissions = [...new Set([...document.querySelectorAll('[data-pathology]')].map((input) => input.dataset.pathology))].map((id) => {
            const fields = document.querySelectorAll(`[data-pathology="${id}"]`);
            return { id_patologia: Number(id), puede_ver: fields[0].checked, puede_editar: fields[1].checked, puede_asignar: fields[2].checked };
        }).filter((permission) => permission.puede_ver || permission.puede_editar || permission.puede_asignar);

        try {
            await apiRequest('{{ url('/api/admin/digitadoras') }}', { method: 'POST', body: JSON.stringify({ nombre: form.get('nombre'), apellido: form.get('apellido'), username: form.get('username'), password: form.get('password'), password_confirmation: form.get('password_confirmation'), permisos: permissions }) });
            event.target.reset();
            showMessage('Digitadora creada correctamente.');
            loadUsers();
        } catch (error) { showMessage(error.message, 'danger'); }
    });

    loadUsers();
</script>
@endsection