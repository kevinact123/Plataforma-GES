@extends('layouts.app')

@section('title', 'Hitos')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 pt-3 pb-3 mb-4 border-bottom">
    <div>
        <div class="text-uppercase small text-muted fw-semibold">Seguimiento clínico</div>
        <h1 class="h2 mb-1"><i class="bi bi-list-check me-2" aria-hidden="true"></i>Hitos</h1>
        <p class="text-muted mb-0">Controla el avance de las tareas asociadas a cada registro GES.</p>
    </div>
    <button class="btn btn-outline-secondary" id="refresh-hitos" type="button"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Actualizar</button>
</div>

<div id="hitos-message" class="alert d-none" role="alert"></div>
<div id="hitos-loading" class="alert alert-info" role="status">Cargando hitos...</div>

<section class="row g-3 mb-4" aria-label="Resumen de hitos">
    <div class="col-6 col-lg-3"><div class="card shadow-sm border-start border-4 border-warning h-100"><div class="card-body"><div class="small text-muted">Pendientes</div><strong class="display-6 text-warning" id="pending-count">-</strong></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm border-start border-4 border-info h-100"><div class="card-body"><div class="small text-muted">En proceso</div><strong class="display-6 text-info" id="process-count">-</strong></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm border-start border-4 border-success h-100"><div class="card-body"><div class="small text-muted">Completados</div><strong class="display-6 text-success" id="complete-count">-</strong></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm border-start border-4 border-primary h-100"><div class="card-body"><div class="small text-muted">Registros con hitos</div><strong class="display-6 text-primary" id="record-count">-</strong></div></div></div>
</section>

<section class="card shadow-sm" aria-labelledby="hitos-list-title">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h5 mb-0" id="hitos-list-title">Hitos por registro GES</h2>
        <select class="form-select form-select-sm w-auto" id="hito-filter" aria-label="Filtrar hitos por estado">
            <option value="todos">Todos los estados</option>
            <option value="pendiente">Pendientes</option>
            <option value="en_proceso">En proceso</option>
            <option value="completado">Completados</option>
        </select>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Registro</th><th>Paciente</th><th>Patología</th><th>Hitos</th><th class="text-end">Acciones</th></tr></thead>
            <tbody id="hitos-list"></tbody>
        </table>
    </div>
    <div class="card-body text-center text-muted d-none" id="hitos-empty">No hay hitos que coincidan con el filtro.</div>
</section>

<div class="modal fade" id="hito-modal" tabindex="-1" aria-labelledby="hito-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><div class="small text-muted" id="hito-modal-subtitle"></div><h2 class="modal-title h5 mb-0" id="hito-modal-title">Hitos del registro</h2></div><button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Cerrar"></button></div>
        <div class="modal-body" id="hito-modal-body"></div>
    </div></div>
</div>
@endsection

@section('scripts')
<script>
    const hitoToken = localStorage.getItem('auth_token');
    const hitoList = document.getElementById('hitos-list');
    const hitoLoading = document.getElementById('hitos-loading');
    const hitoMessage = document.getElementById('hitos-message');
    const hitoModal = new bootstrap.Modal(document.getElementById('hito-modal'));
    const records = [];
    let currentRecord = null;

    function escapeHtml(value) { const element = document.createElement('div'); element.textContent = value ?? '-'; return element.innerHTML; }
    function statusLabel(status) { return { pendiente: 'Pendiente', en_proceso: 'En proceso', completado: 'Completado' }[status] || status; }
    function statusClass(status) { return { pendiente: 'warning', en_proceso: 'info', completado: 'success' }[status] || 'secondary'; }
    function showMessage(text, type = 'success') { hitoMessage.textContent = text; hitoMessage.className = `alert alert-${type}`; }

    async function hitoFetch(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), Authorization: `Bearer ${hitoToken}`, ...options.headers } });
        if (response.status === 401) { localStorage.removeItem('auth_token'); localStorage.removeItem('auth_user'); window.location.href = '{{ route('login') }}'; return null; }
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'No fue posible completar la operación.');
        return data;
    }

    function renderList() {
        const filter = document.getElementById('hito-filter').value;
        const visible = records.filter((record) => filter === 'todos' || record.hitos.some((hito) => hito.estado === filter));
        hitoList.innerHTML = visible.map((record) => {
            const patient = record.paciente ? `${record.paciente.nombre} ${record.paciente.apellido_paterno}` : `ID ${record.id_paciente}`;
            return `<tr><td class="fw-semibold">#${record.id_registro}</td><td>${escapeHtml(patient)}</td><td>${escapeHtml(record.patologia?.nombre)}</td><td><span class="badge text-bg-primary">${record.hitos.length}</span></td><td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-record-id="${record.id_registro}"><i class="bi bi-eye me-1" aria-hidden="true"></i>Ver y gestionar</button></td></tr>`;
        }).join('');
        document.getElementById('hitos-empty').classList.toggle('d-none', visible.length > 0);
    }

    function renderSummary() {
        const all = records.flatMap((record) => record.hitos);
        document.getElementById('pending-count').textContent = all.filter((hito) => hito.estado === 'pendiente').length;
        document.getElementById('process-count').textContent = all.filter((hito) => hito.estado === 'en_proceso').length;
        document.getElementById('complete-count').textContent = all.filter((hito) => hito.estado === 'completado').length;
        document.getElementById('record-count').textContent = records.filter((record) => record.hitos.length).length;
    }

    async function loadHitos() {
        if (!hitoToken) { window.location.href = '{{ route('login') }}'; return; }
        hitoLoading.classList.remove('d-none'); hitoMessage.classList.add('d-none');
        try {
            const response = await hitoFetch('{{ url('/api/registros-ges') }}?per_page=100');
            if (!response) return;
            records.length = 0;
            for (const record of response.data || []) {
                const hitoResponse = await hitoFetch(`{{ url('/api/registros-ges') }}/${record.id_registro}/hitos`);
                records.push({ ...record, hitos: hitoResponse?.data || [] });
            }
            renderSummary(); renderList();
        } catch (error) { showMessage(error.message, 'danger'); } finally { hitoLoading.classList.add('d-none'); }
    }

    function renderModal() {
        const record = currentRecord;
        document.getElementById('hito-modal-title').textContent = `Hitos del registro #${record.id_registro}`;
        document.getElementById('hito-modal-subtitle').textContent = record.paciente ? `${record.paciente.nombre} ${record.paciente.apellido_paterno}` : `Paciente #${record.id_paciente}`;
        const items = record.hitos.length ? `<div class="list-group mb-4">${record.hitos.map((hito) => `<div class="list-group-item"><div class="d-flex justify-content-between align-items-start gap-3"><div><strong>${escapeHtml(hito.nombre)}</strong><div class="small text-muted">Responsable: ${escapeHtml(hito.usuario?.nombre ? `${hito.usuario.nombre} ${hito.usuario.apellido}` : 'No informado')}</div></div><span class="badge text-bg-${statusClass(hito.estado)}">${statusLabel(hito.estado)}</span></div><div class="small mt-2">${escapeHtml(hito.observacion || 'Sin observaciones')}</div><div class="mt-2">${hito.estado !== 'completado' ? `<button class="btn btn-sm btn-outline-info me-1" type="button" data-hito-action="${hito.estado === 'pendiente' ? 'iniciar' : 'completar'}" data-hito-id="${hito.id_hito}">${hito.estado === 'pendiente' ? 'Iniciar' : 'Completar'}</button>` : ''}</div></div>`).join('')}</div>` : '<p class="text-muted">Este registro no tiene hitos.</p>';
        document.getElementById('hito-modal-body').innerHTML = `${items}<form id="new-hito-form" class="border-top pt-3"><h3 class="h6">Agregar hito</h3><div class="row g-2"><div class="col-md-8"><label class="form-label" for="new-hito-name">Nombre</label><input class="form-control" id="new-hito-name" name="nombre" maxlength="200" required></div><div class="col-md-4"><label class="form-label" for="new-hito-observation">Observación</label><input class="form-control" id="new-hito-observation" name="observacion" maxlength="1000"></div><div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Agregar hito</button></div></div></form>`;
        document.getElementById('new-hito-form').addEventListener('submit', createHito);
    }

    async function refreshRecord() {
        const response = await hitoFetch(`{{ url('/api/registros-ges') }}/${currentRecord.id_registro}/hitos`);
        currentRecord.hitos = response?.data || [];
        const index = records.findIndex((record) => record.id_registro === currentRecord.id_registro);
        if (index >= 0) records[index].hitos = currentRecord.hitos;
        renderSummary(); renderList(); renderModal();
    }

    async function createHito(event) {
        event.preventDefault(); const form = new FormData(event.target);
        try { await hitoFetch(`{{ url('/api/registros-ges') }}/${currentRecord.id_registro}/hitos`, { method: 'POST', body: JSON.stringify({ nombre: form.get('nombre'), observacion: form.get('observacion') }) }); showMessage('Hito creado correctamente.'); await refreshRecord(); } catch (error) { showMessage(error.message, 'danger'); }
    }

    async function updateHito(hitoId, action) {
        const observation = window.prompt(action === 'iniciar' ? 'Observación de inicio:' : 'Observación de finalización:', '');
        if (observation === null) return;
        try { await hitoFetch(`{{ url('/api/hitos') }}/${hitoId}/${action}`, { method: 'POST', body: JSON.stringify({ observacion: observation }) }); showMessage(`Hito ${action === 'iniciar' ? 'iniciado' : 'completado'} correctamente.`); await refreshRecord(); } catch (error) { showMessage(error.message, 'danger'); }
    }

    document.getElementById('refresh-hitos').addEventListener('click', loadHitos);
    document.getElementById('hito-filter').addEventListener('change', renderList);
    hitoList.addEventListener('click', (event) => { const button = event.target.closest('[data-record-id]'); if (!button) return; currentRecord = records.find((record) => record.id_registro === Number(button.dataset.recordId)); renderModal(); hitoModal.show(); });
    document.getElementById('hito-modal-body').addEventListener('click', (event) => { const button = event.target.closest('[data-hito-action]'); if (button) updateHito(Number(button.dataset.hitoId), button.dataset.hitoAction); });
    loadHitos();
</script>
@endsection
