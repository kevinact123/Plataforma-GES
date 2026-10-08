@extends('layouts.app')

@section('title', 'Documentación')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 pt-3 pb-3 mb-4 border-bottom">
    <div>
        <div class="text-uppercase small text-muted fw-semibold">Biblioteca compartida</div>
        <h1 class="h2 mb-1"><i class="bi bi-folder2-open me-2" aria-hidden="true"></i>Documentación</h1>
        <p class="text-muted mb-0">Organiza, revisa y relaciona documentos con pacientes y registros GES.</p>
    </div>
    <button class="btn btn-outline-secondary" id="refresh-documentos" type="button"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Actualizar</button>
</div>

<div id="documentacion-message" class="alert d-none" role="alert"></div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Subir documento</h2></div>
    <div class="card-body">
        <form id="documentacion-form" class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="nombre">Nombre</label>
                <input class="form-control" id="nombre" name="nombre" type="text" maxlength="255" placeholder="Ej: Protocolo de atención GES">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="documento">Archivo</label>
                <input class="form-control" id="documento" name="documento" type="file" required>
                <div class="form-text">Máximo 20 MB. PDF, Office, imágenes o texto.</div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="id_categoria">Categoría</label>
                <select class="form-select" id="id_categoria" name="id_categoria"><option value="">Sin categoría</option></select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="descripcion">Descripción</label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="2" maxlength="5000"></textarea>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="etiquetas">Etiquetas</label>
                <input class="form-control" id="etiquetas" name="etiquetas" type="text" placeholder="protocolo, capacitación, 2026">
                <div class="form-text">Separadas por coma.</div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="id_paciente">Asociar a paciente (opcional)</label>
                <select class="form-select" id="id_paciente" name="id_paciente"><option value="">Sin paciente</option></select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="id_registro">Asociar a registro GES (opcional)</label>
                <select class="form-select" id="id_registro" name="id_registro"><option value="">Sin registro</option></select>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" id="ingest-documento" type="submit"><i class="bi bi-cloud-arrow-up me-1" aria-hidden="true"></i>Subir y escanear</button>
            </div>
        </form>
    </div>
</div>

<section class="card shadow-sm mb-4 d-none" id="ingestion-preview" aria-live="polite">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Resultado del escaneo</h2>
        <span class="badge text-bg-secondary" id="ingestion-status">Pendiente</span>
    </div>
    <div class="card-body">
        <div id="ingestion-summary" class="mb-3"></div>
        <div id="ingestion-warnings" class="mb-3"></div>
        <div class="table-responsive" id="ingestion-table-wrapper" style="max-height: 420px; overflow-y: auto;">
            <table class="table table-sm align-middle mb-3">
                <thead><tr><th>Fila</th><th>Campo</th><th>Valor detectado</th><th>Tipo</th></tr></thead>
                <tbody id="ingestion-data"></tbody>
            </table>
        </div>
        <button class="btn btn-success d-none" id="import-documento" type="button"><i class="bi bi-database-check me-1" aria-hidden="true"></i>Confirmar alimentación</button>
    </div>
</section>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Categorías/carpetas</h2>
        <form id="categoria-form" class="d-flex gap-2">
            <input class="form-control form-control-sm" id="categoria-nombre" type="text" maxlength="100" placeholder="Nueva categoría" required>
            <button class="btn btn-sm btn-outline-primary" type="submit">Crear</button>
        </form>
    </div>
    <div class="card-body py-2"><div id="categorias-list" class="d-flex flex-wrap gap-2 text-muted">Cargando...</div></div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h2 class="h5 mb-3">Buscar y filtrar documentos</h2>
        <form id="filtros-form" class="row g-2">
            <div class="col-12 col-lg-4"><input class="form-control" id="filtro-q" type="search" placeholder="Nombre, descripción o etiqueta"></div>
            <div class="col-6 col-lg-2"><select class="form-select" id="filtro-categoria"><option value="">Todas las categorías</option></select></div>
            <div class="col-6 col-lg-2"><select class="form-select" id="filtro-estado"><option value="">Todos los estados</option><option value="pendiente">Pendiente</option><option value="revisado">Revisado</option><option value="aprobado">Aprobado</option></select></div>
            <div class="col-6 col-lg-2"><select class="form-select" id="filtro-paciente"><option value="">Todos los pacientes</option></select></div>
            <div class="col-6 col-lg-2"><input class="form-control" id="filtro-etiqueta" type="text" maxlength="50" placeholder="Etiqueta"></div>
            <div class="col-6 col-lg-2"><button class="btn btn-outline-primary w-100" type="submit">Buscar</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
            <thead><tr><th>Documento</th><th>Categoría</th><th>Etiquetas</th><th>Asociación</th><th>Responsable</th><th>Estado</th><th>Fecha</th><th class="text-end">Acciones</th></tr></thead>
            <tbody id="documentos-list"><tr><td colspan="8">Cargando...</td></tr></tbody>
        </table>
    </div>
</div>

<dialog id="editar-documento-dialog" class="border-0 rounded shadow p-0" style="max-width: 680px; width: 95%;">
    <form id="editar-documento-form" class="p-4">
        <h2 class="h5">Editar documento</h2>
        <input type="hidden" id="editar-id">
        <div class="mb-3"><label class="form-label" for="editar-nombre">Nombre</label><input class="form-control" id="editar-nombre" name="nombre" maxlength="255" required></div>
        <div class="mb-3"><label class="form-label" for="editar-descripcion">Descripción</label><textarea class="form-control" id="editar-descripcion" name="descripcion" rows="3" maxlength="5000"></textarea></div>
        <div class="mb-3"><label class="form-label" for="editar-etiquetas">Etiquetas</label><input class="form-control" id="editar-etiquetas" name="etiquetas" maxlength="1000"></div>
        <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label" for="editar-categoria">Categoría</label><select class="form-select" id="editar-categoria" name="id_categoria"></select></div>
            <div class="col-md-6"><label class="form-label" for="editar-estado">Estado</label><select class="form-select" id="editar-estado" name="estado"><option value="pendiente">Pendiente</option><option value="revisado">Revisado</option><option value="aprobado">Aprobado</option></select></div>
            <div class="col-md-6"><label class="form-label" for="editar-paciente">Paciente</label><select class="form-select" id="editar-paciente" name="id_paciente"></select></div>
            <div class="col-md-6"><label class="form-label" for="editar-registro">Registro GES</label><select class="form-select" id="editar-registro" name="id_registro"></select></div>
        </div>
        <div class="mb-3"><label class="form-label" for="editar-archivo">Reemplazar archivo <span class="text-muted">(opcional)</span></label><input class="form-control" id="editar-archivo" name="documento" type="file"></div>
        <div class="d-flex justify-content-end gap-2"><button class="btn btn-secondary" id="cerrar-edicion" type="button">Cancelar</button><button class="btn btn-primary" type="submit">Guardar cambios</button></div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
    const documentacionToken = localStorage.getItem('auth_token');
    const documentacionMessage = document.getElementById('documentacion-message');
    const documentosList = document.getElementById('documentos-list');
    const categorias = [];
    let pacientes = [];
    let registros = [];
    let documentos = [];
    let ingestionDocumentId = null;

    function showDocumentacionMessage(text, type = 'success') {
        documentacionMessage.textContent = text;
        documentacionMessage.className = `alert alert-${type}`;
    }
    function escapeDocumentacion(value) {
        const element = document.createElement('div');
        element.textContent = value ?? '-';
        return element.innerHTML;
    }
    function formatSize(bytes) { return bytes ? `${(bytes / 1024 / 1024).toFixed(2)} MB` : '-'; }
    function renderIngestionPreview(preview) {
        const panel = document.getElementById('ingestion-preview');
        const importButton = document.getElementById('import-documento');
        const status = document.getElementById('ingestion-status');
        const dataRows = document.getElementById('ingestion-data');
        const warnings = document.getElementById('ingestion-warnings');
        ingestionDocumentId = preview.id_documento;
        panel.classList.remove('d-none');
        status.textContent = preview.importado ? 'Importado automáticamente' : (preview.puede_importar ? 'Listo para confirmar' : 'Requiere revisión');
        status.className = `badge text-bg-${preview.importado || preview.puede_importar ? 'success' : 'warning'}`;
        const patientMatch = preview.paciente_detectado ? ` · Paciente asociado: ${escapeDocumentacion(preview.paciente_detectado.nombre)} (${escapeDocumentacion(preview.paciente_detectado.rut)})` : '';
        document.getElementById('ingestion-summary').innerHTML = `<strong>${escapeDocumentacion(preview.nombre)}</strong> · ${escapeDocumentacion(preview.tipo || 'desconocido')} · ${escapeDocumentacion(preview.extension || '')} · ${escapeDocumentacion(preview.cantidad_filas || 0)} filas detectadas${patientMatch}`;
        const rows = preview.filas_previsualizacion || [(preview.datos_previsualizacion || [])];
        dataRows.innerHTML = rows.flatMap((row, rowIndex) => row.map(item => `<tr><td>${rowIndex + 2}</td><td>${escapeDocumentacion(item.campo)}</td><td>${escapeDocumentacion(item.valor)}</td><td>${escapeDocumentacion(item.tipo)}</td></tr>`)).join('') || '<tr><td colspan="4" class="text-muted">No se detectaron datos importables.</td></tr>';
        const errors = Array.isArray(preview.errores) ? preview.errores : Object.values(preview.errores || {}).flat();
        const warningsList = Array.isArray(preview.advertencias) ? preview.advertencias : Object.values(preview.advertencias || {});
        const messages = [...errors.flatMap(error => typeof error === 'string' ? [error] : Object.values(error || {}).flat()), ...warningsList.flatMap(item => item.detalles?.length ? [`${item.mensaje} (${item.detalles.join(', ')})`] : [item.mensaje])];
        warnings.innerHTML = messages.map(message => `<div class="alert alert-warning py-2 mb-2">${escapeDocumentacion(message)}</div>`).join('');
        importButton.classList.toggle('d-none', !preview.puede_importar);
    }
    function optionLabel(item, type) {
        if (type === 'paciente') return `${item.nombre} ${item.apellido_paterno || ''}`.trim() + ` · ${item.rut || ''}`;
        return `#${item.id_registro} · ${item.paciente ? `${item.paciente.nombre} ${item.paciente.apellido_paterno}` : 'Registro GES'}`;
    }
    function fillSelect(id, items, emptyLabel, labelFn, selected = '') {
        const select = document.getElementById(id);
        select.innerHTML = `<option value="">${emptyLabel}</option>` + items.map(item => `<option value="${item.id_categoria || item.id_paciente || item.id_registro}" ${String(selected) === String(item.id_categoria || item.id_paciente || item.id_registro) ? 'selected' : ''}>${escapeDocumentacion(labelFn(item))}</option>`).join('');
    }
    async function documentacionFetch(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { Accept: 'application/json', Authorization: `Bearer ${documentacionToken}`, ...(options.headers || {}) } });
        if (response.status === 401) { localStorage.removeItem('auth_token'); localStorage.removeItem('auth_user'); window.location.href = '{{ route('login') }}'; return null; }
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'No fue posible completar la operación.');
        return data;
    }
    async function loadCatalogos() {
        const [categoryResponse, catalogResponse, recordResponse] = await Promise.all([
            documentacionFetch('{{ url('/api/documentacion/categorias') }}'),
            documentacionFetch('{{ url('/api/registros-ges/catalogos') }}'),
            documentacionFetch('{{ url('/api/registros-ges?per_page=100') }}')
        ]);
        categorias.splice(0, categorias.length, ...(categoryResponse.data || []));
        pacientes = catalogResponse.pacientes?.data || catalogResponse.pacientes || [];
        registros = recordResponse.data || [];
        ['id_categoria', 'editar-categoria', 'filtro-categoria'].forEach(id => fillSelect(id, categorias, id === 'filtro-categoria' ? 'Todas las categorías' : 'Sin categoría', item => item.nombre));
        ['id_paciente', 'editar-paciente', 'filtro-paciente'].forEach(id => fillSelect(id, pacientes, id === 'filtro-paciente' ? 'Todos los pacientes' : 'Sin paciente', item => optionLabel(item, 'paciente')));
        ['id_registro', 'editar-registro'].forEach(id => fillSelect(id, registros, 'Sin registro', item => optionLabel(item, 'registro')));
        document.getElementById('categorias-list').innerHTML = categorias.length ? categorias.map(c => `<span class="badge text-bg-light border">${escapeDocumentacion(c.nombre)} <small>(${c.documentos_count || 0})</small></span>`).join('') : 'Aún no hay categorías.';
    }
    function renderDocumentos() {
        documentosList.innerHTML = documentos.length ? documentos.map(doc => {
            const association = doc.paciente ? `Paciente: ${doc.paciente.nombre} ${doc.paciente.apellido_paterno}` : (doc.registro_ges ? `Registro #${doc.registro_ges.id_registro}` : 'Biblioteca general');
            const assignee = doc.usuario_asignado?.nombre || (doc.id_usuario_asignado ? `ID ${doc.id_usuario_asignado}` : 'Sin asignar');
            const tags = (doc.etiquetas || []).map(tag => `<span class="badge text-bg-light border me-1">${escapeDocumentacion(tag)}</span>`).join('');
            const stateClass = doc.estado === 'aprobado' ? 'success' : (doc.estado === 'revisado' ? 'info' : 'warning');
            const assignmentButton = doc.estado_asignacion === 'activa' ? '' : `<button class="btn btn-sm btn-outline-success me-1" data-auto-assign="${doc.id_documento}">Asignar</button>`;
            return `<tr><td><strong>${escapeDocumentacion(doc.nombre || doc.nombre_original)}</strong><br><small class="text-muted">${escapeDocumentacion(doc.nombre_original)} · ${formatSize(doc.tamanio)}</small></td><td>${escapeDocumentacion(doc.categoria?.nombre || 'Sin categoría')}</td><td>${tags || '-'}</td><td>${escapeDocumentacion(association)}</td><td>${escapeDocumentacion(assignee)}</td><td><span class="badge text-bg-${stateClass}">${escapeDocumentacion(doc.estado || 'pendiente')}</span></td><td>${escapeDocumentacion(doc.fecha_creacion ? new Date(doc.fecha_creacion).toLocaleString('es-CL') : '-')}</td><td class="text-end text-nowrap">${assignmentButton}<button class="btn btn-sm btn-outline-primary me-1" data-download="${doc.id_documento}" data-name="${escapeDocumentacion(doc.nombre_original)}">Descargar</button><button class="btn btn-sm btn-outline-secondary me-1" data-edit="${doc.id_documento}">Editar</button><button class="btn btn-sm btn-outline-danger" data-delete="${doc.id_documento}">Eliminar</button></td></tr>`;
        }).join('') : '<tr><td colspan="8" class="text-muted">No se encontraron documentos.</td></tr>';
    }
    async function loadDocumentos() {
        if (!documentacionToken) { window.location.href = '{{ route('login') }}'; return; }
        const params = new URLSearchParams();
        const filters = { q: document.getElementById('filtro-q').value, id_categoria: document.getElementById('filtro-categoria').value, estado: document.getElementById('filtro-estado').value, id_paciente: document.getElementById('filtro-paciente').value, etiqueta: document.getElementById('filtro-etiqueta').value };
        Object.entries(filters).forEach(([key, value]) => { if (value) params.set(key, value); });
        try { const response = await documentacionFetch(`{{ url('/api/documentacion') }}?${params}`); documentos = response.data || []; renderDocumentos(); } catch (error) { showDocumentacionMessage(error.message, 'danger'); }
    }
    document.getElementById('documentacion-form').addEventListener('submit', async event => {
        event.preventDefault();
        const button = document.getElementById('ingest-documento');
        button.disabled = true;
        try {
            const response = await documentacionFetch('{{ url('/api/documentacion/ingerir') }}', { method: 'POST', body: new FormData(event.target) });
            renderIngestionPreview(response.data);
            const autoError = !response.data.importado && response.data.resultado_importacion?.error;
            showDocumentacionMessage(response.message, response.data.importado ? 'success' : (autoError ? 'danger' : 'info'));
            await loadDocumentos();
        } catch (error) { showDocumentacionMessage(error.message, 'danger'); } finally { button.disabled = false; }
    });
    document.getElementById('import-documento').addEventListener('click', async event => {
        if (!ingestionDocumentId) return;
        const importButton = event.currentTarget;
        importButton.disabled = true;
        try {
            const response = await documentacionFetch(`{{ url('/api/documentacion') }}/${ingestionDocumentId}/importar`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({}) });
            const imported = response.data || {};
            showDocumentacionMessage(response.message || `Se alimentaron ${imported.pacientes_procesados || 0} pacientes y ${imported.registros_procesados || 0} registros GES.`);
            importButton.classList.add('d-none');
            await loadDocumentos();
        } catch (error) { showDocumentacionMessage(error.message, 'danger'); importButton.disabled = false; }
    });
    document.getElementById('filtros-form').addEventListener('submit', event => { event.preventDefault(); loadDocumentos(); });
    document.getElementById('refresh-documentos').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        if (button.disabled) return;
        const label = button.innerHTML;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Actualizando...';
        try { await loadCatalogos(); await loadDocumentos(); } catch (error) { showDocumentacionMessage(error.message, 'danger'); } finally { button.disabled = false; button.removeAttribute('aria-busy'); button.innerHTML = label; }
    });
    document.getElementById('categoria-form').addEventListener('submit', async event => {
        event.preventDefault();
        try { await documentacionFetch('{{ url('/api/documentacion/categorias') }}', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({nombre: document.getElementById('categoria-nombre').value}) }); event.target.reset(); await loadCatalogos(); showDocumentacionMessage('Categoría creada correctamente.'); } catch (error) { showDocumentacionMessage(error.message, 'danger'); }
    });
    const editDialog = document.getElementById('editar-documento-dialog');
    documentosList.addEventListener('click', async event => {
        const download = event.target.closest('[data-download]'), remove = event.target.closest('[data-delete]'), edit = event.target.closest('[data-edit]'), autoAssign = event.target.closest('[data-auto-assign]');
        try {
            if (autoAssign) {
                const document = documentos.find(item => String(item.id_documento) === autoAssign.dataset.autoAssign);
                let recordId = document?.id_registro || null;
                if (!recordId && document?.id_paciente) {
                    const relatedRecords = registros.filter(record => String(record.id_paciente) === String(document.id_paciente));
                    if (relatedRecords.length === 1) recordId = relatedRecords[0].id_registro;
                    if (relatedRecords.length > 1) {
                        const selection = prompt(`Este paciente tiene ${relatedRecords.length} registros. Indica el ID del registro GES: ${relatedRecords.map(record => `#${record.id_registro}`).join(', ')}`);
                        if (!selection || !relatedRecords.some(record => String(record.id_registro) === String(selection))) return;
                        recordId = Number(selection);
                    }
                }
                await documentacionFetch(`{{ url('/api/documentacion') }}/${autoAssign.dataset.autoAssign}/asignacion-automatica`, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(recordId ? {id_registro: Number(recordId)} : {})});
                await loadDocumentos();
                showDocumentacionMessage('Documento asignado automáticamente.');
                return;
            }
            if (download) { const response = await fetch(`{{ url('/api/documentacion') }}/${download.dataset.download}/download`, {headers: {Authorization: `Bearer ${documentacionToken}`}}); if (!response.ok) throw new Error('No se pudo descargar el documento.'); const link = document.createElement('a'); link.href = URL.createObjectURL(await response.blob()); link.download = download.dataset.name; link.click(); }
            if (remove && confirm('¿Eliminar este documento?')) { await documentacionFetch(`{{ url('/api/documentacion') }}/${remove.dataset.delete}`, {method: 'DELETE'}); await loadDocumentos(); showDocumentacionMessage('Documento eliminado correctamente.'); }
            if (edit) {
                const doc = documentos.find(item => String(item.id_documento) === edit.dataset.edit); if (!doc) return;
                document.getElementById('editar-id').value = doc.id_documento; document.getElementById('editar-nombre').value = doc.nombre || doc.nombre_original; document.getElementById('editar-descripcion').value = doc.descripcion || ''; document.getElementById('editar-etiquetas').value = (doc.etiquetas || []).join(', '); document.getElementById('editar-categoria').value = doc.id_categoria || ''; document.getElementById('editar-estado').value = doc.estado || 'pendiente'; document.getElementById('editar-paciente').value = doc.id_paciente || ''; document.getElementById('editar-registro').value = doc.id_registro || ''; editDialog.showModal();
            }
        } catch (error) { showDocumentacionMessage(error.message, 'danger'); }
    });
    document.getElementById('cerrar-edicion').addEventListener('click', () => editDialog.close());
    document.getElementById('editar-documento-form').addEventListener('submit', async event => {
        event.preventDefault(); const formData = new FormData(event.target); formData.append('_method', 'PUT');
        try { await documentacionFetch(`{{ url('/api/documentacion') }}/${document.getElementById('editar-id').value}`, {method: 'POST', body: formData}); editDialog.close(); await loadDocumentos(); showDocumentacionMessage('Documento actualizado correctamente.'); } catch (error) { showDocumentacionMessage(error.message, 'danger'); }
    });
    (async () => { try { await loadCatalogos(); await loadDocumentos(); } catch (error) { showDocumentacionMessage(error.message, 'danger'); } })();
</script>
@endsection
