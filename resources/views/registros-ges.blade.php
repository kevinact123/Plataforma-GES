@extends('layouts.app')

@section('title', 'Registros GES')

@section('content')
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
    <h1 class="h2 mb-0"><i class="bi bi-file-earmark-medical me-2" aria-hidden="true"></i>Registros GES</h1>
</div>

<div id="registro-message" class="alert d-none" role="alert"></div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h2 class="h5 mb-0">Crear registro GES</h2>
    </div>
    <div class="card-body">
        <form id="registro-form" class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="id_paciente">Paciente</label>
                <select class="form-select" id="id_paciente" name="id_paciente" required><option value="">Selecciona un paciente</option></select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="id_patologia">Patología</label>
                <select class="form-select" id="id_patologia" name="id_patologia" required><option value="">Selecciona una patología</option></select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="id_prioridad">Prioridad</label>
                <select class="form-select" id="id_prioridad" name="id_prioridad" required><option value="">Selecciona una prioridad</option></select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="id_tipo_registro">Tipo de registro</label>
                <select class="form-select" id="id_tipo_registro" name="id_tipo_registro" required><option value="">Selecciona un tipo</option></select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="tipo_tratamiento">Tipo de tratamiento</label>
                <input class="form-control" id="tipo_tratamiento" name="tipo_tratamiento" maxlength="255">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="estado">Estado</label>
                <select class="form-select" id="estado" name="estado">
                    <option value="Pendiente">Pendiente</option>
                    <option value="Asignado">Asignado</option>
                    <option value="Completado">Completado</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="fecha_ingreso">Fecha de ingreso</label>
                <input class="form-control" id="fecha_ingreso" name="fecha_ingreso" type="date">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="fecha_limite">Fecha límite</label>
                <input class="form-control" id="fecha_limite" name="fecha_limite" type="date">
            </div>
            <div class="col-12">
                <label class="form-label" for="observaciones">Observaciones</label>
                <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" id="create-registro" type="submit" disabled><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Crear registro</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Registros</h2>
        <button class="btn btn-sm btn-outline-secondary" id="refresh-registros" type="button">Actualizar</button>
    </div>
    <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Paciente</th>
                    <th>Patología</th>
                    <th>Estado</th>
                    <th>Fecha ingreso</th>
                    <th>Documentación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="registros-list">
                <tr><td colspan="7">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const token = localStorage.getItem('auth_token');
    const messageBox = document.getElementById('registro-message');
    const registrosList = document.getElementById('registros-list');

    function showMessage(text, type = 'success') {
        messageBox.textContent = text;
        messageBox.className = `alert alert-${type}`;
        messageBox.classList.remove('d-none');
    }

    async function apiRequest(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
                Authorization: `Bearer ${token}`,
                ...options.headers,
            },
        });

        const data = await response.json().catch(() => ({}));
        if (response.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.location.href = '{{ route('login') }}';
            return null;
        }
        if (!response.ok) {
            throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'No fue posible completar la operación.');
        }
        return data;
    }

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = value ?? '';
        return node.innerHTML;
    }

    function fillSelect(id, items, labelBuilder) {
        const select = document.getElementById(id);
        select.innerHTML += items.map((item) => `<option value="${item[id]}">${escapeHtml(labelBuilder(item))}</option>`).join('');
    }

    async function loadCatalogos() {
        const response = await apiRequest('{{ url('/api/registros-ges/catalogos') }}');
        const pacientes = response.pacientes?.data || response.pacientes || [];
        const patologias = response.patologias?.data || response.patologias || [];
        const prioridades = response.prioridades?.data || response.prioridades || [];
        const tipos = response.tipos_registro?.data || response.tipos_registro || [];
        fillSelect('id_paciente', pacientes, (item) => `${item.nombre} ${item.apellido_paterno} · ${item.rut}`);
        fillSelect('id_patologia', patologias, (item) => `${item.numero_ges} - ${item.nombre}`);
        fillSelect('id_prioridad', prioridades, (item) => `${item.nombre} (nivel ${item.nivel})`);
        fillSelect('id_tipo_registro', tipos, (item) => item.nombre);
        document.getElementById('create-registro').disabled = false;
    }

    function renderRegistros(registros) {
        if (!registros.length) {
            registrosList.innerHTML = '<tr><td colspan="7">No hay registros GES disponibles.</td></tr>';
            return;
        }

        registrosList.innerHTML = registros.map((registro) => `
            <tr>
                <td>${escapeHtml(registro.id_registro)}</td>
                <td>${escapeHtml(registro.paciente ? `${registro.paciente.nombre} ${registro.paciente.apellido_paterno}` : `ID ${registro.id_paciente}`)}</td>
                <td>${escapeHtml(registro.patologia ? `${registro.patologia.numero_ges} - ${registro.patologia.nombre}` : `ID ${registro.id_patologia}`)}</td>
                <td><span class="badge text-bg-light">${escapeHtml(registro.estado || 'Pendiente')}</span></td>
                <td>${escapeHtml(registro.fecha_ingreso || '-')}</td>
                <td>${escapeHtml((registro.documentos && registro.documentos.length) || 0)}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" type="button" data-action="view" data-id="${registro.id_registro}">Ver</button>
                        <button class="btn btn-outline-success" type="button" data-action="upload" data-id="${registro.id_registro}">Subir</button>
                        <button class="btn btn-outline-danger" type="button" data-action="delete" data-id="${registro.id_registro}">Eliminar</button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    async function loadRegistros() {
        if (!token) {
            window.location.href = '{{ route('login') }}';
            return;
        }

        try {
            const response = await apiRequest('{{ url('/api/registros-ges') }}');
            const items = response.data || [];
            renderRegistros(items);
        } catch (error) {
            showMessage(error.message, 'danger');
        }
    }

    document.getElementById('registro-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.target;
        const payload = Object.fromEntries(new FormData(form).entries());

        Object.keys(payload).forEach((key) => {
            if (payload[key] === '' || payload[key] === null) {
                delete payload[key];
            }
            if (['id_paciente', 'id_patologia', 'id_prioridad', 'id_tipo_registro'].includes(key)) {
                payload[key] = Number(payload[key]);
            }
        });

        try {
            await apiRequest('{{ url('/api/registros-ges') }}', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            form.reset();
            showMessage('Registro GES creado correctamente.', 'success');
            loadRegistros();
        } catch (error) {
            showMessage(error.message, 'danger');
        }
    });

    document.getElementById('refresh-registros').addEventListener('click', loadRegistros);

    document.getElementById('registros-list').addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;

        const id = Number(button.dataset.id);
        const action = button.dataset.action;

        if (action === 'delete') {
            if (!confirm('¿Deseas eliminar este registro GES?')) return;
            try {
                await apiRequest(`{{ url('/api/registros-ges') }}/${id}`, { method: 'DELETE' });
                showMessage('Registro eliminado correctamente.', 'success');
                loadRegistros();
            } catch (error) {
                showMessage(error.message, 'danger');
            }
            return;
        }

        try {
            const data = await apiRequest(`{{ url('/api/registros-ges') }}/${id}`);
            const registro = data.data || data;
            const documentos = await apiRequest(`{{ url('/api/registros-ges') }}/${id}/documentos`);
            const docs = documentos.data || [];
            const anterioresResponse = await apiRequest(`{{ url('/api/registros-ges') }}/${id}/anteriores`);
            const anteriores = anterioresResponse?.data?.data ?? anterioresResponse?.data ?? [];

            const html = `
                <div class="modal fade" id="registroModal" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Registro GES #${registro.id_registro}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                      </div>
                      <div class="modal-body">
                        <dl class="row mb-4">
                          <dt class="col-sm-3">Paciente</dt><dd class="col-sm-9">${escapeHtml(registro.paciente ? `${registro.paciente.nombre} ${registro.paciente.apellido_paterno}` : `ID ${registro.id_paciente}`)}</dd>
                          <dt class="col-sm-3">Patología</dt><dd class="col-sm-9">${escapeHtml(registro.patologia ? `${registro.patologia.numero_ges} - ${registro.patologia.nombre}` : `ID ${registro.id_patologia}`)}</dd>
                          <dt class="col-sm-3">Estado</dt><dd class="col-sm-9">${escapeHtml(registro.estado || 'Pendiente')}</dd>
                          <dt class="col-sm-3">Fecha ingreso</dt><dd class="col-sm-9">${escapeHtml(registro.fecha_ingreso || '-')}</dd>
                          <dt class="col-sm-3">Fecha límite</dt><dd class="col-sm-9">${escapeHtml(registro.fecha_limite || '-')}</dd>
                          <dt class="col-sm-3">Observaciones</dt><dd class="col-sm-9">${escapeHtml(registro.observaciones || '-')}</dd>
                        </dl>

                        ${anteriores.length ? `
                            <div class="mb-4">
                                <h6>Registros anteriores</h6>
                                <ul class="list-group">
                                    ${anteriores.map((prev) => `
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong>#${escapeHtml(prev.id_registro)}</strong>
                                                    <span class="ms-2 badge text-bg-light">${escapeHtml(prev.estado || 'Pendiente')}</span>
                                                </div>
                                                <small class="text-muted">${escapeHtml(prev.fecha_ingreso || '-')}</small>
                                            </div>
                                            <div class="small text-muted mt-1">${escapeHtml(prev.observaciones || 'Sin observaciones')}</div>
                                        </li>
                                    `).join('')}
                                </ul>
                            </div>
                        ` : ''}
                        <div class="mb-4">
                            <h6>Documentación adjunta</h6>
                            ${docs.length ? `<ul class="list-group">${docs.map((doc) => `
                                <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                    <div>
                                        <strong>${escapeHtml(doc.nombre_original)}</strong><br>
                                        <small class="text-muted">${escapeHtml(doc.observaciones || 'Sin observaciones')}</small>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" type="button" data-doc-download="${doc.id_documento}" data-registro-id="${id}" data-file-name="${escapeHtml(doc.nombre_original)}">Descargar</button>
                                        <button class="btn btn-outline-danger" type="button" data-doc-delete="${doc.id_documento}" data-registro-id="${id}">Eliminar</button>
                                    </div>
                                </li>
                            `).join('')}</ul>` : '<p class="text-muted">No hay documentación adjunta.</p>'}
                        </div>

                        <form id="upload-doc-form" class="row g-2" data-registro-id="${id}">
                            <div class="col-md-8">
                                <input class="form-control" type="file" name="documento" required>
                            </div>
                            <div class="col-md-4">
                                <input class="form-control" type="text" name="observaciones" placeholder="Observaciones">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-success" type="submit">Subir documentación</button>
                            </div>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', html);
            const modal = new bootstrap.Modal(document.getElementById('registroModal'));
            modal.show();

            document.getElementById('upload-doc-form').addEventListener('submit', async (uploadEvent) => {
                uploadEvent.preventDefault();
                const formData = new FormData(uploadEvent.target);
                const registroId = uploadEvent.target.dataset.registroId;
                const file = formData.get('documento');
                if (!file || file.size === 0) {
                    showMessage('Debes seleccionar un documento antes de subirlo.', 'warning');
                    return;
                }

                try {
                    await apiRequest(`{{ url('/api/registros-ges') }}/${registroId}/documentos`, {
                        method: 'POST',
                        body: formData,
                    });
                    showMessage('Documento subido correctamente.', 'success');
                    modal.hide();
                    loadRegistros();
                } catch (error) {
                    showMessage(error.message, 'danger');
                }
            });

            document.body.addEventListener('click', async (clickEvent) => {
                const downloadButton = clickEvent.target.closest('[data-doc-download]');
                if (downloadButton) {
                    try {
                        const response = await fetch(`{{ url('/api/registros-ges') }}/${downloadButton.dataset.registroId}/documentos/${downloadButton.dataset.docDownload}/download`, { headers: { Authorization: `Bearer ${token}` } });
                        if (!response.ok) throw new Error('No fue posible descargar el documento.');
                        const blob = await response.blob();
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = downloadButton.dataset.fileName || 'documento';
                        link.click();
                        URL.revokeObjectURL(link.href);
                    } catch (error) {
                        showMessage(error.message, 'danger');
                    }
                    return;
                }

                const docButton = clickEvent.target.closest('[data-doc-delete]');
                if (!docButton) return;
                const docId = Number(docButton.dataset.docDelete);
                const registroId = Number(docButton.dataset.registroId);
                if (!confirm('¿Deseas eliminar este documento?')) return;
                try {
                    await apiRequest(`{{ url('/api/registros-ges') }}/${registroId}/documentos/${docId}`, { method: 'DELETE' });
                    showMessage('Documento eliminado correctamente.', 'success');
                    modal.hide();
                    loadRegistros();
                } catch (error) {
                    showMessage(error.message, 'danger');
                }
            }, { once: true });
        } catch (error) {
            showMessage(error.message, 'danger');
        }
    });

    async function initializeRegistros() {
        try {
            await loadCatalogos();
            await loadRegistros();
        } catch (error) {
            showMessage(error.message, 'danger');
        }
    }

    initializeRegistros();
</script>
@endsection
