@extends('layouts.app')

@section('title', 'Registros GES')

@section('content')
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
    <h1 class="h2 mb-0"><i class="bi bi-file-earmark-medical me-2" aria-hidden="true"></i>Registros GES</h1>
</div>

<div id="registro-message" class="alert d-none" role="alert"></div>

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
                    <th>Tipo de registro</th>
                    <th>Estado</th>
                    <th>Fecha ingreso</th>
                    <th>Documentación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="registros-list">
                <tr><td colspan="8">Cargando...</td></tr>
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

    async function descargarDocumento(registroId, documentoId, nombreArchivo) {
        const response = await fetch(`{{ url('/api/registros-ges') }}/${registroId}/documentos/${documentoId}/download`, {
            headers: {
                Accept: '*/*',
                Authorization: `Bearer ${token}`,
            },
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message || 'No se pudo descargar el documento.');
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = nombreArchivo || 'documento';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    }

    async function verDocumento(registroId, documentoId) {
        const response = await fetch(`{{ url('/api/registros-ges') }}/${registroId}/documentos/${documentoId}/download`, {
            headers: {
                Accept: '*/*',
                Authorization: `Bearer ${token}`,
            },
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message || 'No se pudo abrir el documento.');
        }

        const blob = await response.blob();
        const blobUrl = window.URL.createObjectURL(blob);
        window.open(blobUrl, '_blank', 'noopener,noreferrer');
    }

    @include('partials.ges-select-filter')

    function renderRegistros(registros) {
        const registrosPermitidos = registros || [];

        if (!registrosPermitidos.length) {
            registrosList.innerHTML = '<tr><td colspan="8">No hay registros GES disponibles para tu perfil.</td></tr>';
            return;
        }

        registrosList.innerHTML = registrosPermitidos.map((registro) => `
            <tr>
                <td>${escapeHtml(registro.id_registro)}</td>
                <td>${escapeHtml(registro.paciente ? `${registro.paciente.nombre} ${registro.paciente.apellido_paterno}` : `ID ${registro.id_paciente}`)}</td>
                <td>${escapeHtml(registro.patologia ? `${registro.patologia.numero_ges} - ${registro.patologia.nombre}` : `ID ${registro.id_patologia}`)}${registro.patologias_asociadas?.length ? `<div class="small text-muted">${registro.patologias_asociadas.length} asociada(s)</div>` : ''}</td>
                <td>${escapeHtml(registro.tipo_registro?.nombre || `ID ${registro.id_tipo_registro}`)}</td>
                <td><span class="badge text-bg-light">${escapeHtml(registro.estado || 'Pendiente')}</span></td>
                <td>${escapeHtml(registro.fecha_ingreso || '-')}</td>
                <td>${escapeHtml((registro.documentos && registro.documentos.length) || 0)}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" style="min-width: 3.4rem; padding: .1rem .3rem; font-size: .68rem;" type="button" data-action="view" data-id="${registro.id_registro}">Ver</button>
                        ${registro.puede_eliminar ? `<button class="btn btn-outline-danger" style="min-width: 3.4rem; padding: .1rem .3rem; font-size: .68rem;" type="button" data-action="delete" data-id="${registro.id_registro}">Eliminar</button>` : ''}
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

    document.getElementById('refresh-registros').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        if (button.disabled) return;
        const label = button.innerHTML;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Actualizando...';
        try { await loadRegistros(); } finally { button.disabled = false; button.removeAttribute('aria-busy'); button.innerHTML = label; }
    });

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

            if (registro?.puede_ver === false) {
                showMessage('No tienes permiso para ver este registro.', 'warning');
                return;
            }

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
                        ${registro.puede_editar ? `
                            <div class="d-flex justify-content-end mb-3">
                              <button class="btn btn-warning btn-sm" type="button" id="edit-registro-button">Editar</button>
                            </div>
                        ` : ''}

                        <div id="registro-view-content">
                          <dl class="row mb-4">
                            <dt class="col-sm-3">Paciente</dt><dd class="col-sm-9">${escapeHtml(registro.paciente ? `${registro.paciente.nombre} ${registro.paciente.apellido_paterno}` : `ID ${registro.id_paciente}`)}</dd>
                            <dt class="col-sm-3">Patología</dt><dd class="col-sm-9">${escapeHtml(registro.patologia ? `${registro.patologia.numero_ges} - ${registro.patologia.nombre}` : `ID ${registro.id_patologia}`)}</dd>
                            <dt class="col-sm-3">Estado</dt><dd class="col-sm-9">${escapeHtml(registro.estado || 'Pendiente')}</dd>
                            <dt class="col-sm-3">Fecha ingreso</dt><dd class="col-sm-9">${escapeHtml(registro.fecha_ingreso || '-')}</dd>
                            <dt class="col-sm-3">Fecha límite</dt><dd class="col-sm-9">${escapeHtml(registro.fecha_limite || '-')}</dd>
                            <dt class="col-sm-3">Observaciones</dt><dd class="col-sm-9">${escapeHtml(registro.observaciones || '-')}</dd>
                          </dl>
                          <h6>Enfermedades o complicaciones asociadas</h6>
                          <div id="asociaciones-list" class="mb-3">
                             ${registro.patologias_asociadas?.length ? registro.patologias_asociadas.map((asociacion) => `
                               <div class="border rounded p-2 mb-2 d-flex justify-content-between align-items-start gap-2">
                                 <div><strong>${escapeHtml(asociacion.patologia?.nombre || `ID ${asociacion.id_patologia}`)}</strong>
                                 <div class="small text-muted">${escapeHtml(asociacion.tipo || 'complicacion')}${asociacion.observacion ? ` · ${escapeHtml(asociacion.observacion)}` : ''}</div></div>
                                 ${registro.puede_editar ? `<div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary" type="button" data-asociacion-edit="${asociacion.id_registro_patologia}" data-registro-id="${id}">Editar</button><button class="btn btn-outline-danger" type="button" data-asociacion-delete="${asociacion.id_registro_patologia}" data-registro-id="${id}">Quitar</button></div>` : ''}
                               </div>
                             `).join('') : '<div class="text-muted small">No hay patologías asociadas.</div>'}
                          </div>
                          ${registro.puede_editar ? `
                             <form id="add-asociacion-form" class="row g-2 mb-3">
                               <div class="col-md-5"><select class="form-select" id="asociacion-patologia" required><option value="">Agregar patología</option></select></div>
                               <div class="col-md-3"><input class="form-control" id="asociacion-tipo" value="complicacion" maxlength="50" placeholder="Tipo"></div>
                               <div class="col-md-4"><div class="input-group"><input class="form-control" id="asociacion-observacion" maxlength="500" placeholder="Observación"><button class="btn btn-outline-primary" type="submit">Agregar</button></div></div>
                             </form>
                          ` : ''}
                        </div>

                        <div class="mb-4">
                          <h6>Documentos adjuntos</h6>
                          ${docs.length ? `
                            <div class="list-group">
                              ${docs.map((doc) => {
                                const tamanoKb = doc.tamanio ? Math.max(1, Math.round(doc.tamanio / 1024)) : 0;
                                return `
                                  <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                      <div>
                                        <div class="fw-semibold">${escapeHtml(doc.nombre_original || 'Documento')}</div>
                                        <small class="text-muted">${escapeHtml(doc.mime_type || 'Archivo')} · ${tamanoKb} KB</small>
                                        ${doc.observaciones ? `<div class="small text-muted mt-1">${escapeHtml(doc.observaciones)}</div>` : ''}
                                      </div>
                                      <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-outline-primary" type="button" data-doc-view="${doc.id_documento}" data-registro-id="${registro.id_registro}" aria-label="Ver documento">Ver</button>
                                        <button class="btn btn-outline-success" type="button" data-doc-download="${doc.id_documento}" data-registro-id="${registro.id_registro}" data-doc-name="${escapeHtml(doc.nombre_original || 'documento')}" aria-label="Descargar documento">Descargar</button>
                                      </div>
                                    </div>
                                  </div>
                                `;
                              }).join('')}
                            </div>
                          ` : '<div class="text-muted">No hay documentos adjuntos para este registro.</div>'}
                        </div>

                        <form id="edit-registro-form" class="row g-3 d-none" data-registro-id="${id}">
                          <div class="col-md-4">
                            <label class="form-label" for="edit_id_paciente">Paciente</label>
                            <select class="form-select" id="edit_id_paciente" name="id_paciente" required></select>
                          </div>
                          <div class="col-md-4">
                            <label class="form-label" for="edit_id_patologia">Patología</label>
                            <select class="form-select" id="edit_id_patologia" name="id_patologia" required></select>
                          </div>
                          <div class="col-md-4">
                            <label class="form-label" for="edit_id_prioridad">Prioridad</label>
                            <select class="form-select" id="edit_id_prioridad" name="id_prioridad" required></select>
                          </div>
                          <div class="col-md-4">
                            <label class="form-label" for="edit_id_tipo_registro">Tipo de registro</label>
                            <select class="form-select" id="edit_id_tipo_registro" name="id_tipo_registro" required></select>
                          </div>
                          <div class="col-md-4">
                            <label class="form-label" for="edit_tipo_tratamiento">Tipo de tratamiento</label>
                            <input class="form-control" id="edit_tipo_tratamiento" name="tipo_tratamiento" value="${escapeHtml(registro.tipo_tratamiento || '')}">
                          </div>
                          <div class="col-md-4">
                            <label class="form-label" for="edit_estado">Estado</label>
                            <select class="form-select" id="edit_estado" name="estado">
                              <option value="Pendiente" ${registro.estado === 'Pendiente' ? 'selected' : ''}>Pendiente</option>
                              <option value="Asignado" ${registro.estado === 'Asignado' ? 'selected' : ''}>Asignado</option>
                              <option value="Completado" ${registro.estado === 'Completado' ? 'selected' : ''}>Completado</option>
                            </select>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label" for="edit_fecha_ingreso">Fecha ingreso</label>
                            <input class="form-control" id="edit_fecha_ingreso" name="fecha_ingreso" type="date" value="${escapeHtml(registro.fecha_ingreso || '')}">
                          </div>
                          <div class="col-md-6">
                            <label class="form-label" for="edit_fecha_limite">Fecha límite</label>
                            <input class="form-control" id="edit_fecha_limite" name="fecha_limite" type="date" value="${escapeHtml(registro.fecha_limite || '')}">
                          </div>
                          <div class="col-12">
                            <label class="form-label" for="edit_observaciones">Observaciones</label>
                            <textarea class="form-control" id="edit_observaciones" name="observaciones" rows="3">${escapeHtml(registro.observaciones || '')}</textarea>
                          </div>
                          <div class="col-12">
                            <div class="border rounded p-3 bg-light-subtle">
                              <label class="form-label fw-semibold" for="edit_documento">Subir archivo faltante</label>
                              <input class="form-control" id="edit_documento" name="documento" type="file">
                              <div class="form-text">Puedes adjuntar un documento que falte al registro.</div>
                            </div>
                          </div>
                          <div class="col-12 d-flex justify-content-end gap-2">
                            <button class="btn btn-outline-secondary" type="button" id="cancel-edit-registro">Cancelar</button>
                            <button class="btn btn-primary" type="submit">Guardar cambios</button>
                          </div>
                        </form>

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
                      </div>
                    </div>
                  </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', html);
            const modal = new bootstrap.Modal(document.getElementById('registroModal'));
            modal.show();

            const catalog = await apiRequest('{{ url('/api/registros-ges/catalogos') }}');
            const patologiasCatalogo = catalog.patologias?.data || catalog.patologias || [];
            const populateEditSelect = (selectId, items, selectedId) => {
                const select = document.getElementById(selectId);
                select.innerHTML = items.map((item) => `<option value="${item.id_paciente ?? item.id_patologia ?? item.id_prioridad ?? item.id_tipo_registro}" ${selectedId === (item.id_paciente ?? item.id_patologia ?? item.id_prioridad ?? item.id_tipo_registro) ? 'selected' : ''}>${escapeHtml(item.nombre ? `${item.nombre} ${item.apellido_paterno || ''}`.trim() : `${item.numero_ges ? `${item.numero_ges} - ` : ''}${item.nombre || ''}`)}</option>`).join('');
            };

            populateEditSelect('edit_id_paciente', catalog.pacientes?.data || catalog.pacientes || [], registro.id_paciente);
            attachSelectFilter(document.getElementById('edit_id_paciente'), 'Buscar paciente por nombre o RUT...');
            populateEditSelect('edit_id_patologia', catalog.patologias?.data || catalog.patologias || [], registro.id_patologia);
            populateEditSelect('edit_id_prioridad', catalog.prioridades?.data || catalog.prioridades || [], registro.id_prioridad);
            populateEditSelect('edit_id_tipo_registro', catalog.tipos_registro?.data || catalog.tipos_registro || [], registro.id_tipo_registro);
            if (registro.puede_editar) {
                const asociacionSelect = document.getElementById('asociacion-patologia');
                asociacionSelect.innerHTML += patologiasCatalogo
                    .filter((item) => Number(item.id_patologia) !== Number(registro.id_patologia) && !(registro.patologias_asociadas || []).some((a) => Number(a.id_patologia) === Number(item.id_patologia)))
                    .map((item) => `<option value="${item.id_patologia}">${escapeHtml(`${item.numero_ges} - ${item.nombre}`)}</option>`).join('');
            }

            document.getElementById('registroModal').addEventListener('click', async (event) => {
                const viewButton = event.target.closest('[data-doc-view]');
                const downloadButton = event.target.closest('[data-doc-download]');

                if (viewButton) {
                    try {
                        await verDocumento(Number(viewButton.dataset.registroId), Number(viewButton.dataset.docView));
                    } catch (error) {
                        showMessage(error.message, 'danger');
                    }
                    return;
                }

                if (downloadButton) {
                    try {
                        await descargarDocumento(Number(downloadButton.dataset.registroId), Number(downloadButton.dataset.docDownload), downloadButton.dataset.docName || 'documento');
                    } catch (error) {
                        showMessage(error.message, 'danger');
                    }
                }

                const deleteAssociation = event.target.closest('[data-asociacion-delete]');
                if (deleteAssociation) {
                    if (!confirm('¿Deseas quitar esta patología asociada?')) return;
                    try {
                        await apiRequest(`{{ url('/api/registros-ges') }}/${id}/patologias-asociadas/${deleteAssociation.dataset.asociacionDelete}`, { method: 'DELETE' });
                        showMessage('Patología asociada eliminada correctamente.', 'success');
                        modal.hide();
                        loadRegistros();
                    } catch (error) { showMessage(error.message, 'danger'); }
                    return;
                }

                const editAssociation = event.target.closest('[data-asociacion-edit]');
                if (editAssociation) {
                    const association = (registro.patologias_asociadas || []).find((item) => String(item.id_registro_patologia) === String(editAssociation.dataset.asociacionEdit));
                    if (!association) return;
                    const tipo = prompt('Tipo de asociación:', association.tipo || 'complicacion');
                    if (tipo === null) return;
                    const observacion = prompt('Observación:', association.observacion || '');
                    if (observacion === null) return;
                    try {
                        await apiRequest(`{{ url('/api/registros-ges') }}/${id}/patologias-asociadas/${association.id_registro_patologia}`, { method: 'PUT', body: JSON.stringify({ tipo, observacion }) });
                        showMessage('Patología asociada actualizada correctamente.', 'success');
                        modal.hide();
                        loadRegistros();
                    } catch (error) { showMessage(error.message, 'danger'); }
                    return;
                }
            });

            if (registro.puede_editar) {
                document.getElementById('add-asociacion-form').addEventListener('submit', async (associationEvent) => {
                    associationEvent.preventDefault();
                    try {
                        await apiRequest(`{{ url('/api/registros-ges') }}/${id}/patologias-asociadas`, {
                            method: 'POST',
                            body: JSON.stringify({
                                id_patologia: Number(document.getElementById('asociacion-patologia').value),
                                tipo: document.getElementById('asociacion-tipo').value,
                                observacion: document.getElementById('asociacion-observacion').value,
                            }),
                        });
                        showMessage('Patología asociada correctamente.', 'success');
                        modal.hide();
                        loadRegistros();
                    } catch (error) { showMessage(error.message, 'danger'); }
                });
            }

            document.getElementById('edit-registro-button').addEventListener('click', () => {
                document.getElementById('registro-view-content').classList.add('d-none');
                document.getElementById('edit-registro-form').classList.remove('d-none');
            });

            document.getElementById('cancel-edit-registro').addEventListener('click', () => {
                document.getElementById('edit-registro-form').classList.add('d-none');
                document.getElementById('registro-view-content').classList.remove('d-none');
            });

            document.getElementById('edit-registro-form').addEventListener('submit', async (submitEvent) => {
                submitEvent.preventDefault();
                const form = submitEvent.target;
                const formData = new FormData(form);
                const file = formData.get('documento');
                const payload = Object.fromEntries(formData.entries());

                Object.keys(payload).forEach((key) => {
                    if (payload[key] === '' || payload[key] === null) {
                        delete payload[key];
                    }
                    if (['id_paciente', 'id_patologia', 'id_prioridad', 'id_tipo_registro'].includes(key)) {
                        payload[key] = Number(payload[key]);
                    }
                });

                try {
                    await apiRequest(`{{ url('/api/registros-ges') }}/${id}`, {
                        method: 'PUT',
                        body: JSON.stringify(payload),
                    });

                    if (file && file.size > 0) {
                        const docFormData = new FormData();
                        docFormData.append('documento', file);
                        if (payload.observaciones) {
                            docFormData.append('observaciones', payload.observaciones);
                        }
                        await apiRequest(`{{ url('/api/registros-ges') }}/${id}/documentos`, {
                            method: 'POST',
                            body: docFormData,
                        });
                    }

                    showMessage('Registro actualizado correctamente.', 'success');
                    modal.hide();
                    loadRegistros();
                } catch (error) {
                    showMessage(error.message, 'danger');
                }
            });
        } catch (error) {
            showMessage(error.message, 'danger');
        }
    });

    async function initializeRegistros() {
        try {
            await loadRegistros();
        } catch (error) {
            showMessage(error.message, 'danger');
        }
    }

    initializeRegistros();
</script>
@endsection
