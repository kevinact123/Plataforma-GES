@extends('layouts.app')

@section('title', 'Configuraciones')

@section('content')
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
    <h1 class="h2 mb-0"><i class="bi bi-diagram-3 me-2" aria-hidden="true"></i>Configuraciones</h1>
</div>

<div id="assignments-message" class="alert d-none" role="alert"></div>

<div class="card shadow-sm mb-4" id="assignment-management-section">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Asignación automática</h2></div>
    <div class="card-body">
        <form id="automatic-assignment-form" class="row g-3 align-items-start">
            <div class="col-md-8">
                <label class="form-label" for="automatic-assignment-record">Registro GES</label>
                <select class="form-select" id="automatic-assignment-record" required><option value="">Cargando registros pendientes...</option></select>
                <div class="form-text">Se selecciona el/la digitador/a activo/a con permiso y menor carga; en empate gana el ID menor.</div>
            </div>
            <div class="col-md-4"><label class="form-label d-none d-md-block" aria-hidden="true">&nbsp;</label><button class="btn btn-primary w-100" type="submit"><i class="bi bi-magic me-1" aria-hidden="true"></i>Asignar automáticamente</button></div>
        </form>
        <div id="automatic-assignment-result" class="small mt-3 text-muted"></div>
    </div>
</div>

<div class="card shadow-sm mb-4" id="user-management-section">
    <div class="card-header bg-white">
        <h2 class="h5 mb-0">Nuevo usuario</h2>
    </div>
    <div class="card-body">
        <form id="digitadora-form" class="row g-3">
            <div class="col-md-4"><label class="form-label" for="nombre">Nombre</label><input class="form-control" id="nombre" name="nombre" required maxlength="100"></div>
            <div class="col-md-4"><label class="form-label" for="apellido">Apellido</label><input class="form-control" id="apellido" name="apellido" required maxlength="100"></div>
            <div class="col-md-4"><label class="form-label" for="username">Usuario</label><input class="form-control" id="username" name="username" required maxlength="100" pattern="[A-Za-z0-9_-]+"></div>
            <div class="col-md-4"><label class="form-label" for="correo">Correo institucional</label><input class="form-control" type="email" id="correo" name="correo" required maxlength="255"></div>
            <div class="col-md-4"><label class="form-label" for="password">Contraseña temporal</label><input class="form-control" type="password" id="password" name="password" required minlength="8" maxlength="72"></div>
            <div class="col-md-4"><label class="form-label" for="password_confirmation">Confirmar contraseña</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" maxlength="72"></div>
            <div class="col-md-4"><label class="form-label" for="id_rol">Rol</label><select class="form-select" id="id_rol" name="id_rol" required><option value="">Cargando roles...</option></select></div>
            <div class="col-md-4" id="tipo-digitadora-group">
                <label class="form-label d-block">Tipo de digitador/a</label>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo-dig-no" name="tipo_no" checked><label class="form-check-label" for="tipo-dig-no">No confidencial</label></div>
<div class="form-check"><input class="form-check-input" type="checkbox" id="tipo-dig-si" name="tipo_si"><label class="form-check-label" for="tipo-dig-si">Confidencial</label></div>
            </div>
            <div class="col-12 small text-muted" id="tipo-digitadora-help">Solo No confidencial: accede únicamente a las patologías no confidenciales. Confidencial (sola o junto con No confidencial): accede a las patologías confidenciales (VIH/SIDA, Agresión Sexual Aguda) además de las no confidenciales, y recibe Ver, Editar y Asignar sobre las confidenciales. El/la digitador/a solo No confidencial nunca accede a las confidenciales, aunque se le marquen permisos.</div>
            <div class="col-12" id="pathology-permissions-group">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <h3 class="h6 mb-0">Permisos por patología</h3>
                    <div class="input-group" style="max-width: 320px;">
                        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input class="form-control" id="pathology-search" type="search" placeholder="Buscar patología..." aria-label="Buscar patología">
                    </div>
                </div>
                <div id="pathology-permissions" class="row g-2 mt-2 pathology-permissions-wrapper"><div class="text-muted">Cargando patologías...</div></div>
            </div>
            <div class="col-12"><button class="btn btn-primary" type="submit" id="create-digitadora"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Crear usuario</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4" id="pathology-management-section">
    <div class="card-header bg-white">
        <h2 class="h5 mb-0">Nueva patología</h2>
    </div>
    <div class="card-body">
        <div id="new-patologia-form" class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label" for="patologia_numero_ges">N° GES</label><input class="form-control" id="patologia_numero_ges" name="numero_ges" type="number" min="1" required></div>
            <div class="col-md-4"><label class="form-label" for="patologia_nombre">Nombre</label><input class="form-control" id="patologia_nombre" name="nombre" maxlength="255" required></div>
            <div class="col-md-4"><label class="form-label" for="patologia_descripcion">Descripción</label><input class="form-control" id="patologia_descripcion" name="descripcion" maxlength="1000"></div>
            <div class="col-md-2 d-flex align-items-center">
                <div class="form-check mt-4">
                    <input class="form-check-input" id="patologia_confidencial" name="confidencial" type="checkbox">
                    <label class="form-check-label" for="patologia_confidencial">Confidencial</label>
                </div>
            </div>
            <div class="col-12"><button class="btn btn-outline-primary btn-sm" type="button" id="submit-new-patologia"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar patología</button></div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4" id="complexity-management-section">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Registrar complejidad por tipo</h2></div>
    <div class="card-body">
        <form id="config-complexity-form" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="config-complexity-type">Tipo de registro</label>
                <select class="form-select" id="config-complexity-type" name="id_tipo_registro" required></select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="config-complexity-score">Puntaje</label>
                <select class="form-select" id="config-complexity-score" name="puntaje" required>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="config-complexity-observation">Observación</label>
                <input class="form-control" id="config-complexity-observation" name="observacion" maxlength="500" placeholder="Opcional">
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1" aria-hidden="true"></i>Guardar complejidad</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm" id="digitadoras-list-section">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Usuarios/as registrados/as</h2></div>
    <div class="table-responsive"><table class="table table-striped align-middle mb-0"><thead><tr><th>Nombre</th><th>Usuario</th><th>Correo</th><th>Rol / tipo</th><th>Estado</th><th>Permisos explícitos</th><th>Acciones</th></tr></thead><tbody id="digitadoras-list"><tr><td colspan="7">Cargando...</td></tr></tbody></table></div>
</div>

<div class="modal fade" id="digitadora-permissions-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="digitadora-permissions-title">Editar digitador/a</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="digitadora-permissions-form" class="row g-3">
                    <div class="col-md-4"><label class="form-label" for="edit-nombre">Nombre</label><input class="form-control" id="edit-nombre" name="nombre" required maxlength="100"></div>
                    <div class="col-md-4"><label class="form-label" for="edit-apellido">Apellido</label><input class="form-control" id="edit-apellido" name="apellido" required maxlength="100"></div>
                    <div class="col-md-4"><label class="form-label" for="edit-username">Usuario</label><input class="form-control" id="edit-username" name="username" required maxlength="100" pattern="[A-Za-z0-9_\-]+"></div>
                    <div class="col-md-4"><label class="form-label" for="edit-correo">Correo</label><input class="form-control" type="email" id="edit-correo" name="correo" required maxlength="255"></div>
                    <div class="col-md-4"><label class="form-label" for="edit-password">Nueva contraseña</label><input class="form-control" type="password" id="edit-password" name="password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Dejar vacío para no cambiar"></div>
                    <div class="col-md-4"><label class="form-label" for="edit-password-confirmation">Confirmar contraseña</label><input class="form-control" type="password" id="edit-password-confirmation" name="password_confirmation" minlength="8" maxlength="72" autocomplete="new-password"></div>
                    <div class="col-md-5">
                        <label class="form-label d-block">Tipo de digitador/a</label>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="edit-tipo-dig-no" name="tipo_no" checked><label class="form-check-label" for="edit-tipo-dig-no">No confidencial</label></div>
<div class="form-check"><input class="form-check-input" type="checkbox" id="edit-tipo-dig-si" name="tipo_si"><label class="form-check-label" for="edit-tipo-dig-si">Confidencial</label></div>
                    </div>
                    <div class="col-12 small text-muted">Solo el/la digitador/a CONFIDENCIAL puede acceder a las patologías confidenciales. Si cambias a NO_CONFIDENCIAL, se eliminan sus permisos sobre ellas.</div>
                    <div class="col-12">
                        <div id="digitadora-permissions-container" class="row g-2"></div>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script>
    const adminToken = localStorage.getItem('auth_token');
    const settingsUser = JSON.parse(localStorage.getItem('auth_user') || 'null');
    const permissions = new Set(settingsUser?.permissions || []);
    document.getElementById('assignment-management-section')?.classList.toggle('d-none', !permissions.has('asignar_pacientes'));
    document.getElementById('user-management-section')?.classList.toggle('d-none', !permissions.has('administrar_usuarios'));
    document.getElementById('pathology-management-section')?.classList.toggle('d-none', !permissions.has('administrar_patologias'));
    document.getElementById('complexity-management-section')?.classList.toggle('d-none', !permissions.has('editar_registros'));
    document.getElementById('digitadoras-list-section')?.classList.toggle('d-none', !permissions.has('administrar_usuarios'));
    const pathologyPermissionsStyles = document.createElement('style');
    pathologyPermissionsStyles.textContent = `
        .pathology-permissions-wrapper { max-height: 440px; overflow-y: auto; }
        .pathology-permission-card { min-height: 88px; }
        .pathology-permission-card .form-check-label { font-size: 0.85rem; }
    `;
    document.head.appendChild(pathologyPermissionsStyles);
    const message = document.getElementById('assignments-message');
    const pathologyPermissions = document.getElementById('pathology-permissions');
    const digitadorasList = document.getElementById('digitadoras-list');
    let allPathologies = [];
    const selectedPathologies = new Set();
    let defaultSelectionApplied = false;

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
        allPathologies = pathologies;
        const searchTerm = (document.getElementById('pathology-search')?.value || '').trim().toLowerCase();
        const filteredPathologies = !searchTerm ? pathologies : pathologies.filter((pathology) => `${pathology.numero_ges} ${pathology.nombre}`.toLowerCase().includes(searchTerm));

        pathologyPermissions.innerHTML = filteredPathologies.length ? `
            <div class="col-12">
                <div class="border rounded bg-light p-2 pathology-permissions-scroll">
                    <div class="row g-2">
                        ${filteredPathologies.map((pathology) => {
                            const isConfidencial = Boolean(pathology.confidencial);
                            const confidentialBadge = isConfidencial ? '<span class="badge text-bg-warning">Confidencial</span>' : '<span class="badge text-bg-light border">Normal</span>';
                            return `
                                <div class="col-xl-6">
                                    <div class="border rounded p-2 ${isConfidencial ? 'border-warning bg-warning-subtle' : 'border-light bg-white'} pathology-permission-card">
                                        <div class="d-flex justify-content-between align-items-center gap-2">
                                            <strong class="small me-2">${escapeHtml(pathology.numero_ges)} - ${escapeHtml(pathology.nombre)}</strong>
                                            ${confidentialBadge}
                                        </div>
                                        <div class="mt-2 d-flex flex-wrap gap-3">
                                            <label class="form-check form-check-inline m-0"><input class="form-check-input" type="checkbox" data-pathology="${pathology.id_patologia}" ${selectedPathologies.has(Number(pathology.id_patologia)) ? 'checked' : ''}> <span class="form-check-label">Seleccionar</span></label>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            </div>
        ` : '<div class="col-12 text-muted">No se encontraron patologías con ese criterio.</div>';
    }

    function renderDigitadoras(users) {
        digitadorasList.innerHTML = users.length ? users.map((user) => {
            const active = Boolean(user.activo);
            const blocked = Boolean(user.bloqueado);
            const isDigitadora = user.rol?.toLowerCase() === 'digitadora';
            const tipo = isDigitadora
                ? (user.tipo_digitadora === 'CONFIDENCIAL' ? 'Digitadora · Confidencial' : 'Digitadora · No confidencial')
                : user.rol;
            const actions = isDigitadora
                ? `<button class="btn btn-sm btn-outline-primary me-1" type="button" data-digitadora-edit="${user.id_usuario}">Editar</button><button class="btn btn-sm btn-outline-${blocked ? 'warning' : (active ? 'danger' : 'success')}" type="button" data-digitadora-state="${user.id_usuario}" data-active="${active}" ${blocked ? 'title="Cuenta bloqueada: clic para reactivar"' : ''}>${blocked ? '<i class="bi bi-lock-fill me-1"></i>Bloqueada' : (active ? 'Desactivar' : 'Reactivar')}</button><button class="btn btn-sm btn-outline-danger ms-1" type="button" data-digitadora-delete="${user.id_usuario}">Eliminar</button>`
                : '<span class="text-muted">—</span>';
            return `<tr><td>${escapeHtml(user.nombre)}</td><td>${escapeHtml(user.username)}</td><td>${escapeHtml(user.correo || '-')}</td><td>${escapeHtml(tipo)}</td><td><span class="badge text-bg-${blocked ? 'warning' : (active ? 'success' : 'secondary')}">${blocked ? 'Bloqueada' : (active ? 'Activa' : 'Inactiva')}</span></td><td>${isDigitadora ? user.permisos.length : '—'}</td><td>${actions}</td></tr>`;
        }).join('') : '<tr><td colspan="7">No hay usuarios registrados.</td></tr>';
    }

    function renderDigitadoraPermissions(user, pathologies) {
        const permissionsMap = new Map((user.permisos || []).map((permiso) => [permiso.id_patologia, permiso]));
        document.getElementById('edit-tipo-dig-si').checked = user.tipo_digitadora === 'CONFIDENCIAL';
        document.getElementById('edit-tipo-dig-no').checked = user.tipo_digitadora !== 'CONFIDENCIAL';
        document.getElementById('digitadora-permissions-title').textContent = `Editar a ${user.nombre}`;
        document.getElementById('edit-nombre').value = user.nombre_pila ?? '';
        document.getElementById('edit-apellido').value = user.apellido ?? '';
        document.getElementById('edit-username').value = user.username ?? '';
        document.getElementById('edit-correo').value = user.correo ?? '';
        document.getElementById('edit-password').value = '';
        document.getElementById('edit-password-confirmation').value = '';
        document.getElementById('digitadora-permissions-container').innerHTML = pathologies.map((pathology) => {
            const permiso = permissionsMap.get(pathology.id_patologia) || {};
            const selected = Boolean(permiso.puede_ver || permiso.puede_editar || permiso.puede_asignar);
            const isConfidencial = Boolean(pathology.confidencial);
            const confidentialBadge = isConfidencial ? '<span class="badge text-bg-warning">Confidencial</span>' : '<span class="badge text-bg-light border">Normal</span>';
            return `<div class="col-lg-6"><div class="border rounded p-3 ${isConfidencial ? 'border-warning bg-warning-subtle' : 'border-light'}"><div class="d-flex justify-content-between align-items-center gap-2"><strong>${escapeHtml(pathology.numero_ges)} - ${escapeHtml(pathology.nombre)}</strong>${confidentialBadge}</div><div class="mt-2 d-flex gap-3 flex-wrap"><label><input type="checkbox" name="permisos[${pathology.id_patologia}]" ${selected ? 'checked' : ''}> Seleccionar</label></div></div></div>`;
        }).join('');
        document.getElementById('digitadora-permissions-form').dataset.userId = user.id_usuario;
    }

    let digitadoraRoleId = null;

    function renderRoles(roles) {
        const select = document.getElementById('id_rol');
        const current = select.value;
        digitadoraRoleId = String((roles.find((rol) => rol.es_digitadora) || {}).id_rol ?? '');
        select.innerHTML = '<option value="">Selecciona un rol</option>' + roles.map((rol) => `<option value="${rol.id_rol}">${escapeHtml(rol.nombre === 'Digitadora' ? 'Digitador/a' : rol.nombre)}</option>`).join('');
        select.value = current;
        toggleDigitadoraFields();
    }

    function toggleDigitadoraFields() {
        const isDigitadora = digitadoraRoleId !== null && document.getElementById('id_rol').value === digitadoraRoleId;
        ['tipo-digitadora-group', 'tipo-digitadora-help', 'pathology-permissions-group'].forEach((id) => document.getElementById(id).classList.toggle('d-none', !isDigitadora));
    }

    document.getElementById('id_rol').addEventListener('change', toggleDigitadoraFields);

    pathologyPermissions.addEventListener('change', (event) => {
        const id = Number(event.target.dataset?.pathology);
        if (!id) return;
        event.target.checked ? selectedPathologies.add(id) : selectedPathologies.delete(id);
    });

    function applyTipoToPathologies(tipoCheckbox, confidencial, selectFn) {
        allPathologies.filter((pathology) => Boolean(pathology.confidencial) === confidencial)
            .forEach((pathology) => selectFn(Number(pathology.id_patologia), tipoCheckbox.checked));
    }

    document.getElementById('tipo-dig-no').addEventListener('change', (event) => {
        applyTipoToPathologies(event.target, false, (id, on) => on ? selectedPathologies.add(id) : selectedPathologies.delete(id));
        renderPathologies(allPathologies);
    });
    document.getElementById('tipo-dig-si').addEventListener('change', (event) => {
        applyTipoToPathologies(event.target, true, (id, on) => on ? selectedPathologies.add(id) : selectedPathologies.delete(id));
        renderPathologies(allPathologies);
    });

    document.getElementById('edit-tipo-dig-no').addEventListener('change', (event) => setEditSelection(false, event.target.checked));
    document.getElementById('edit-tipo-dig-si').addEventListener('change', (event) => setEditSelection(true, event.target.checked));

    function setEditSelection(confidencial, checked) {
        const confidentialIds = new Set(allPathologies.filter((pathology) => Boolean(pathology.confidencial) === confidencial).map((pathology) => String(pathology.id_patologia)));
        document.querySelectorAll('#digitadora-permissions-container input[type="checkbox"]').forEach((input) => {
            const id = input.name.match(/^permisos\[(\d+)\]$/)?.[1];
            if (id && confidentialIds.has(id)) input.checked = checked;
        });
    }

    async function loadUsers() {
        if (!permissions.has('administrar_usuarios')) return;
        if (!adminToken) { window.location.href = '{{ route('login') }}'; return; }
        try {
            const data = await apiRequest('{{ url('/api/admin/digitadoras') }}');
            renderRoles(data.roles || []);
            if (!defaultSelectionApplied) {
                allPathologies = data.patologias;
                ['tipo-dig-no', 'tipo-dig-si'].forEach((id, index) => applyTipoToPathologies(document.getElementById(id), index === 1, (pid, on) => on && selectedPathologies.add(pid)));
                defaultSelectionApplied = true;
            }
            renderPathologies(data.patologias);
            renderDigitadoras(data.data);
        } catch (error) { showMessage(error.message, 'danger'); }
    }

    document.getElementById('pathology-search').addEventListener('input', (event) => {
        renderPathologies(allPathologies, event.target.value);
    });

    document.getElementById('submit-new-patologia').addEventListener('click', async () => {
        const form = document.getElementById('new-patologia-form');
        const numeroGesInput = document.getElementById('patologia_numero_ges');
        const nombreInput = document.getElementById('patologia_nombre');
        const descripcionInput = document.getElementById('patologia_descripcion');
        const confidencialInput = document.getElementById('patologia_confidencial');

        if (!numeroGesInput.value || !nombreInput.value.trim()) {
            showMessage('Debes completar el número GES y el nombre de la patología.', 'danger');
            return;
        }

        try {
            await apiRequest('{{ url('/api/patologias') }}', {
                method: 'POST',
                body: JSON.stringify({
                    numero_ges: Number(numeroGesInput.value),
                    nombre: nombreInput.value.trim(),
                    descripcion: descripcionInput.value.trim(),
                    confidencial: Boolean(confidencialInput.checked),
                }),
            });
            form.querySelectorAll('input').forEach((input) => input.value = '');
            if (confidencialInput) confidencialInput.checked = false;
            showMessage('Patología creada correctamente.');
            loadUsers();
        } catch (error) {
            showMessage(error.message, 'danger');
        }
    });

    document.getElementById('digitadora-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(event.target);
        const isDigitadora = form.get('id_rol') === digitadoraRoleId;
        if (isDigitadora && !form.get('tipo_si') && !form.get('tipo_no')) { showMessage('Selecciona al menos un tipo de digitador/a.', 'danger'); return; }
        const tipoDigitadora = isDigitadora ? (form.get('tipo_si') ? 'CONFIDENCIAL' : 'NO_CONFIDENCIAL') : null;
        const permissions = [...selectedPathologies].map((id) => {
            return { id_patologia: id, puede_ver: true, puede_editar: true, puede_asignar: true };
        }).filter((permission) => isDigitadora && (permission.puede_ver || permission.puede_editar || permission.puede_asignar));

        try {
            await apiRequest('{{ url('/api/admin/digitadoras') }}', { method: 'POST', body: JSON.stringify({ id_rol: Number(form.get('id_rol')), nombre: form.get('nombre'), apellido: form.get('apellido'), username: form.get('username'), correo: form.get('correo'), password: form.get('password'), password_confirmation: form.get('password_confirmation'), tipo_digitadora: tipoDigitadora, permisos: permissions }) });
            event.target.reset();
            selectedPathologies.clear();
            renderPathologies(allPathologies);
            toggleDigitadoraFields();
            showMessage('Usuario creado correctamente.');
            loadUsers();
        } catch (error) { showMessage(error.message, 'danger'); }
    });

    document.getElementById('digitadoras-list').addEventListener('click', async (event) => {
        const stateButton = event.target.closest('[data-digitadora-state]');
        if (stateButton) {
            const active = stateButton.dataset.active === 'true';
            const action = active ? 'desactivar' : 'reactivar';
            if (!confirm(`¿Deseas ${action} esta digitadora? Sus registros, documentos y asignaciones se conservarán.`)) return;
            try {
                await apiRequest(`{{ url('/api/admin/digitadoras') }}/${stateButton.dataset.digitadoraState}/estado`, {
                    method: 'PATCH',
                    body: JSON.stringify({ activo: !active }),
                });
                showMessage(`Digitadora ${active ? 'desactivada' : 'reactivada'} correctamente.`);
                await loadUsers();
            } catch (error) {
                showMessage(error.message, 'danger');
            }
            return;
        }

        const deleteButton = event.target.closest('[data-digitadora-delete]');
        if (deleteButton) {
            if (!confirm('¿Eliminar a este/a digitador/a? Dejará de aparecer y de poder ingresar, pero sus registros, asignaciones y acciones realizadas se conservarán.')) return;
            try {
                const result = await apiRequest(`{{ url('/api/admin/digitadoras') }}/${deleteButton.dataset.digitadoraDelete}`, { method: 'DELETE' });
                showMessage(result.message || 'Digitador/a eliminado/a correctamente.');
                await loadUsers();
            } catch (error) {
                showMessage(error.message, 'danger');
            }
            return;
        }
        const button = event.target.closest('[data-digitadora-edit]');
        if (!button) return;

        try {
            const data = await apiRequest('{{ url('/api/admin/digitadoras') }}');
            const user = data.data.find((digitadora) => digitadora.id_usuario === Number(button.dataset.digitadoraEdit));
            if (!user) return;
            renderDigitadoraPermissions(user, data.patologias);
            new bootstrap.Modal(document.getElementById('digitadora-permissions-modal')).show();
        } catch (error) {
            showMessage(error.message, 'danger');
        }
    });

    document.getElementById('digitadora-permissions-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.target;
        const userId = Number(form.dataset.userId);
        const permissions = {};

        Array.from(form.querySelectorAll('input[type="checkbox"]')).forEach((input) => {
            const matches = input.name.match(/^permisos\[(\d+)\]$/);
            if (!matches) return;
            const [, idPatologia] = matches;
            permissions[idPatologia] = { id_patologia: Number(idPatologia), puede_ver: input.checked, puede_editar: input.checked, puede_asignar: input.checked };
        });

        const filteredPermissions = Object.values(permissions).filter((permission) => permission.puede_ver || permission.puede_editar || permission.puede_asignar);

        if (!form.querySelector('[name="tipo_si"]').checked && !form.querySelector('[name="tipo_no"]').checked) { showMessage('Selecciona al menos un tipo de digitador/a.', 'danger'); return; }

        try {
            const tipoDigitadora = form.querySelector('[name="tipo_si"]').checked ? 'CONFIDENCIAL' : 'NO_CONFIDENCIAL';
            await apiRequest(`{{ url('/api/admin/digitadoras') }}/${userId}`, {
                method: 'PUT',
                body: JSON.stringify({ nombre: form.querySelector('[name="nombre"]').value, apellido: form.querySelector('[name="apellido"]').value, username: form.querySelector('[name="username"]').value, correo: form.querySelector('[name="correo"]').value, password: form.querySelector('[name="password"]').value || null, password_confirmation: form.querySelector('[name="password_confirmation"]').value || null, tipo_digitadora: tipoDigitadora, permisos: filteredPermissions })
            });
            showMessage('Digitador/a actualizado/a correctamente.');
            bootstrap.Modal.getInstance(document.getElementById('digitadora-permissions-modal')).hide();
            loadUsers();
        } catch (error) {
            showMessage(error.message, 'danger');
        }
    });

    async function loadConfigComplexityTypes() {
        try {
            const catalog = await apiRequest('{{ url('/api/registros-ges/catalogos') }}');
            const select = document.getElementById('config-complexity-type');
            if (!select) return;
            const types = catalog?.tipos_registro || [];
            select.innerHTML = types.length ? types.map((item) => `<option value="${item.id_tipo_registro}">${escapeHtml(item.nombre)}</option>`).join('') : '<option value="">Sin tipos disponibles</option>';
        } catch (error) {
            const select = document.getElementById('config-complexity-type');
            if (select) select.innerHTML = '<option value="">No se pudo cargar</option>';
        }
    }

    async function loadAutomaticAssignmentRecords() {
            const select = document.getElementById('automatic-assignment-record');
            try {
                const response = await apiRequest('{{ url('/api/registros-ges/sin-asignar?per_page=100') }}');
                const records = response.data || [];
                select.innerHTML = records.length
                    ? '<option value="">Selecciona un registro</option>' + records.map((record) => `<option value="${record.id_registro}">#${record.id_registro} · ${escapeHtml(record.patologia?.nombre || 'Sin patología')} · ${escapeHtml(record.paciente?.nombre || '')}</option>`).join('')
                    : '<option value="">No hay registros pendientes</option>';
            } catch (error) {
                select.innerHTML = '<option value="">No se pudo cargar</option>';
            }
        }

        document.getElementById('automatic-assignment-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const recordId = document.getElementById('automatic-assignment-record').value;
            if (!recordId) return;
            try {
                const response = await apiRequest(`{{ url('/api/asignaciones/automaticas/registro') }}/${recordId}`, {method: 'POST', body: JSON.stringify({})});
                const assignment = response.data || response;
                document.getElementById('automatic-assignment-result').innerHTML = `Asignado a <strong>${escapeHtml(assignment.usuario?.nombre || assignment.id_usuario)}</strong>.`;
                showMessage('Registro asignado automáticamente.');
                loadAutomaticAssignmentRecords();
            } catch (error) { showMessage(error.message, 'danger'); }
        });

    async function saveConfigComplexity(event) {
        event.preventDefault();
        const form = event.target;
        const payload = {
            id_tipo_registro: Number(form.id_tipo_registro.value),
            puntaje: Number(form.puntaje.value),
            observacion: form.observacion.value.trim(),
        };

        try {
            const response = await fetch('{{ url('/api/complejidad') }}', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: Bearer  },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'No fue posible guardar la complejidad.');
            }
            form.reset();
            showMessage(data.message || 'Complejidad guardada correctamente.');
        } catch (error) {
            showMessage(error.message, 'danger');
        }
    }

    document.getElementById('config-complexity-form')?.addEventListener('submit', saveConfigComplexity);
    if (permissions.has('editar_registros')) loadConfigComplexityTypes();
    loadUsers();
    if (permissions.has('asignar_pacientes')) loadAutomaticAssignmentRecords();
</script>
@endsection
