@extends('layouts.app')

@section('title', 'Pacientes')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pt-3 pb-3 mb-4 border-bottom">
    <div>
        <div class="text-uppercase small text-muted fw-semibold">Gestión clínica</div>
        <h1 class="h2 mb-1"><i class="bi bi-people me-2" aria-hidden="true"></i>Pacientes</h1>
        <p class="text-muted mb-0">Consulta pacientes y revisa sus registros GES autorizados.</p>
    </div>
    <span class="badge text-bg-light border px-3 py-2" id="patient-count">- pacientes</span>
</div>

<div id="patients-error" class="alert alert-danger d-none" role="alert"></div>
<div id="patients-loading" class="alert alert-info" role="status">Cargando pacientes...</div>

<section class="card shadow-sm mb-4" aria-labelledby="new-patient-title">
    <div class="card-body">
        <h2 class="h5 mb-3" id="new-patient-title">Agregar paciente</h2>
        <form class="row g-3" id="new-patient-form">
            <div class="col-12 col-md-4"><label class="form-label" for="new-patient-rut">RUT</label><input class="form-control" id="new-patient-rut" name="rut" maxlength="20" required></div>
            <div class="col-12 col-md-4"><label class="form-label" for="new-patient-name">Nombre</label><input class="form-control" id="new-patient-name" name="nombre" maxlength="100" required></div>
            <div class="col-12 col-md-4"><label class="form-label" for="new-patient-lastname">Apellido paterno</label><input class="form-control" id="new-patient-lastname" name="apellido_paterno" maxlength="100" required></div>
            <div class="col-12 col-md-4"><label class="form-label" for="new-patient-mother-lastname">Apellido materno</label><input class="form-control" id="new-patient-mother-lastname" name="apellido_materno" maxlength="100"></div>
            <div class="col-12 col-md-4"><label class="form-label" for="new-patient-birthdate">Fecha de nacimiento</label><input class="form-control" id="new-patient-birthdate" name="fecha_nacimiento" type="date"></div>
            <div class="col-12 col-md-4"><label class="form-label" for="new-patient-sex">Sexo</label><select class="form-select" id="new-patient-sex" name="sexo"><option value="">Seleccionar</option><option value="F">Femenino</option><option value="M">Masculino</option><option value="O">Otro</option></select></div>
            <div class="col-12"><button class="btn btn-primary" id="save-patient" type="submit"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Guardar paciente</button></div>
        </form>
    </div>
</section>

<section class="card shadow-sm mb-4" aria-labelledby="patient-search-title">
    <div class="card-body">
        <h2 class="h5 mb-3" id="patient-search-title">Buscar paciente</h2>
        <form class="row g-2 align-items-end" id="patient-search-form">
            <div class="col-12 col-md-6 col-lg-4">
                <label class="form-label" for="patient-rut">RUT</label>
                <input class="form-control" id="patient-rut" name="rut" type="search" placeholder="Ej. 12.345.678-9" autocomplete="off">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-secondary" id="clear-search" type="button"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Limpiar</button>
            </div>
        </form>
    </div>
</section>

<section class="card shadow-sm" aria-labelledby="patient-list-title">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0" id="patient-list-title">Listado de pacientes</h2>
        <span class="small text-muted" id="patient-page"></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">RUT</th>
                    <th scope="col">Paciente</th>
                    <th scope="col">Fecha de nacimiento</th>
                    <th scope="col">Registros GES</th>
                    <th scope="col" class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody id="patients-table"></tbody>
        </table>
    </div>
    <div class="card-body text-center text-muted d-none" id="patients-empty">No se encontraron pacientes con registros visibles.</div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <button class="btn btn-sm btn-outline-secondary" id="previous-page" type="button" disabled><i class="bi bi-chevron-left" aria-hidden="true"></i> Anterior</button>
        <button class="btn btn-sm btn-outline-secondary" id="next-page" type="button" disabled>Siguiente <i class="bi bi-chevron-right" aria-hidden="true"></i></button>
    </div>
</section>

<div class="modal fade" id="patient-modal" tabindex="-1" aria-labelledby="patient-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="small text-muted" id="patient-modal-rut"></div>
                    <h2 class="modal-title h4 mb-0" id="patient-modal-title">Detalle del paciente</h2>
                </div>
                <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="patient-modal-body">
                <div class="text-center text-muted py-4">Cargando detalle...</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const token = localStorage.getItem('auth_token');
    const table = document.getElementById('patients-table');
    const loading = document.getElementById('patients-loading');
    const errorBox = document.getElementById('patients-error');
    const empty = document.getElementById('patients-empty');
    const count = document.getElementById('patient-count');
    const pageLabel = document.getElementById('patient-page');
    const modal = new bootstrap.Modal(document.getElementById('patient-modal'));
    let currentPage = 1;
    let lastPage = 1;

    function escapeHtml(value) {
        return String(value ?? '-').replace(/[&<>'"]/g, (character) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[character]));
    }

    function formatDate(value) {
        if (!value) return '-';
        return new Intl.DateTimeFormat('es-CL').format(new Date(`${value}T00:00:00`));
    }

    function authHeaders() {
        return { Accept: 'application/json', Authorization: `Bearer ${token}` };
    }

    async function apiFetch(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { ...authHeaders(), ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...options.headers } });
        if (response.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.location.href = '{{ route('login') }}';
            return null;
        }
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'No fue posible cargar la información.');
        return payload;
    }

    function renderRows(patients) {
        table.innerHTML = patients.map((patient) => {
            const fullName = [patient.nombre, patient.apellido_paterno, patient.apellido_materno].filter(Boolean).join(' ');
            const records = patient.registros_ges || [];
            return `<tr>
                <td class="fw-semibold">${escapeHtml(patient.rut)}</td>
                <td>${escapeHtml(fullName)}</td>
                <td>${escapeHtml(formatDate(patient.fecha_nacimiento))}</td>
                <td><span class="badge text-bg-primary">${records.length}</span></td>
                <td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-patient-id="${patient.id_paciente}"><i class="bi bi-eye me-1" aria-hidden="true"></i>Ver ficha</button></td>
            </tr>`;
        }).join('');
        empty.classList.toggle('d-none', patients.length > 0);
    }

    async function loadPatients(page = 1) {
        loading.classList.remove('d-none');
        errorBox.classList.add('d-none');
        const rut = document.getElementById('patient-rut').value.trim();
        const params = new URLSearchParams({ page, per_page: 15 });
        if (rut) params.set('rut', rut);

        try {
            const payload = await apiFetch(`/api/pacientes?${params}`);
            if (!payload) return;
            const patients = payload.data || [];
            currentPage = payload.meta?.current_page || page;
            lastPage = payload.meta?.last_page || 1;
            renderRows(patients);
            count.textContent = `${payload.meta?.total ?? patients.length} pacientes`;
            pageLabel.textContent = lastPage > 1 ? `Página ${currentPage} de ${lastPage}` : `${patients.length} resultado(s)`;
            document.getElementById('previous-page').disabled = currentPage <= 1;
            document.getElementById('next-page').disabled = currentPage >= lastPage;
        } catch (requestError) {
            table.innerHTML = '';
            errorBox.textContent = requestError.message;
            errorBox.classList.remove('d-none');
        } finally {
            loading.classList.add('d-none');
        }
    }

    async function showPatient(patientId) {
        const body = document.getElementById('patient-modal-body');
        body.innerHTML = '<div class="text-center text-muted py-4">Cargando detalle...</div>';
        modal.show();
        try {
            const payload = await apiFetch(`/api/pacientes/${patientId}`);
            if (!payload) return;
            const patient = payload.data;
            const records = patient.registros_ges || [];
            const fullName = [patient.nombre, patient.apellido_paterno, patient.apellido_materno].filter(Boolean).join(' ');
            document.getElementById('patient-modal-title').textContent = fullName;
            document.getElementById('patient-modal-rut').textContent = `RUT ${patient.rut}`;
            body.innerHTML = `<div class="row g-3 mb-4">
                <div class="col-sm-4"><div class="small text-muted">Fecha de nacimiento</div><div class="fw-semibold">${escapeHtml(formatDate(patient.fecha_nacimiento))}</div></div>
                <div class="col-sm-4"><div class="small text-muted">Sexo</div><div class="fw-semibold">${escapeHtml(patient.sexo)}</div></div>
                <div class="col-sm-4"><div class="small text-muted">Estado</div><div class="fw-semibold">${patient.activo ? 'Activo' : 'Inactivo'}</div></div>
            </div>
            <h3 class="h5 border-bottom pb-2">Registros GES visibles</h3>
            ${records.length ? `<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Fecha ingreso</th><th>Patología</th><th>Estado</th><th>Tratamiento</th></tr></thead><tbody>${records.map((record) => `<tr><td>${escapeHtml(formatDate(record.fecha_ingreso))}</td><td>${escapeHtml(record.patologia?.nombre)}</td><td><span class="badge text-bg-light border">${escapeHtml(record.estado)}</span></td><td>${escapeHtml(record.tipo_tratamiento)}</td></tr>`).join('')}</tbody></table></div>` : '<p class="text-muted mb-0">Este paciente no tiene registros GES visibles.</p>'}`;
        } catch (requestError) {
            body.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(requestError.message)}</div>`;
        }
    }

    document.getElementById('patient-search-form').addEventListener('submit', (event) => { event.preventDefault(); loadPatients(1); });
    document.getElementById('clear-search').addEventListener('click', () => { document.getElementById('patient-rut').value = ''; loadPatients(1); });
    document.getElementById('previous-page').addEventListener('click', () => loadPatients(currentPage - 1));
    document.getElementById('next-page').addEventListener('click', () => loadPatients(currentPage + 1));
    table.addEventListener('click', (event) => {
        const button = event.target.closest('[data-patient-id]');
        if (button) showPatient(button.dataset.patientId);
    });

    document.getElementById('new-patient-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = document.getElementById('save-patient');
        button.disabled = true;
        const payload = Object.fromEntries(new FormData(event.target).entries());
        Object.keys(payload).forEach((key) => { if (payload[key] === '') delete payload[key]; });
        try {
            await apiFetch('/api/pacientes', { method: 'POST', body: JSON.stringify(payload) });
            event.target.reset();
            errorBox.className = 'alert alert-success';
            errorBox.textContent = 'Paciente creado correctamente.';
            errorBox.classList.remove('d-none');
            loadPatients(1);
        } catch (requestError) {
            errorBox.textContent = requestError.message;
            errorBox.className = 'alert alert-danger';
            errorBox.classList.remove('d-none');
        } finally { button.disabled = false; }
    });

    if (!token) window.location.href = '{{ route('login') }}';
    else loadPatients();
</script>
@endsection
