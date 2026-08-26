@extends('layouts.app')

@section('title', 'Menú principal')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 pt-3 pb-3 mb-4 border-bottom">
    <div>
        <div class="text-uppercase small text-muted fw-semibold">Resumen operativo</div>
        <h1 class="h2 mb-1"><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>Menú principal</h1>
        <p class="text-muted mb-0">Estado actual de la gestión GES y carga de trabajo.</p>
    </div>
    <button class="btn btn-outline-secondary" id="refresh-dashboard" type="button"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Actualizar</button>
</div>

<div id="dashboard-error" class="alert alert-danger d-none" role="alert"></div>
<div id="dashboard-loading" class="alert alert-info" role="status">Cargando indicadores...</div>

<section class="row g-3 mb-4" id="summary-cards" aria-label="Indicadores principales"></section>

<section class="row g-3 mb-4">
    <div class="col-12 col-xl-4"><div class="card shadow-sm h-100"><div class="card-header bg-white"><h2 class="h6 mb-0">Estado de registros GES</h2></div><div class="card-body"><canvas id="status-chart" height="220"></canvas></div></div></div>
    <div class="col-12 col-xl-4"><div class="card shadow-sm h-100"><div class="card-header bg-white"><h2 class="h6 mb-0">Registros por prioridad</h2></div><div class="card-body"><canvas id="priority-chart" height="220"></canvas></div></div></div>
    <div class="col-12 col-xl-4"><div class="card shadow-sm h-100"><div class="card-header bg-white"><h2 class="h6 mb-0">Registros por tipo</h2></div><div class="card-body"><canvas id="type-chart" height="220"></canvas></div></div></div>
</section>

<section class="row g-3 mb-4">
    <div class="col-12 col-lg-6"><div class="card shadow-sm h-100"><div class="card-header bg-white"><h2 class="h6 mb-0">Registros por patología</h2></div><div class="card-body"><canvas id="pathology-chart" height="260"></canvas></div></div></div>
    <div class="col-12 col-lg-6"><div class="card shadow-sm h-100"><div class="card-header bg-white"><h2 class="h6 mb-0">Complejidad promedio</h2></div><div class="card-body d-flex align-items-center justify-content-center"><div class="display-3 fw-semibold text-primary" id="complexity-value">-</div><span class="text-muted ms-2">puntos</span></div></div></div>
</section>

<section class="row g-3 mb-4">
    <div class="col-12 col-lg-6"><div class="card shadow-sm h-100"><div class="card-header bg-white"><h2 class="h6 mb-0">Carga activa por operador</h2></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Operador</th><th>Registros activos</th><th>Carga</th></tr></thead><tbody id="operator-load"></tbody></table></div></div></div>
    <div class="col-12 col-lg-6"><div class="card shadow-sm h-100"><div class="card-header bg-white"><h2 class="h6 mb-0">Registros por operador</h2></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Operador</th><th class="text-end">Total</th></tr></thead><tbody id="operator-records"></tbody></table></div></div></div>
</section>

<section class="card shadow-sm mb-4"><div class="card-header bg-white"><h2 class="h6 mb-0">Seguimiento de hitos</h2></div><div class="card-body row text-center" id="milestones"></div></section>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const dashboardToken = localStorage.getItem('auth_token');
    const dashboardLoading = document.getElementById('dashboard-loading');
    const dashboardError = document.getElementById('dashboard-error');
    const refreshButton = document.getElementById('refresh-dashboard');
    let dashboardCharts = [];
    let dashboardRequestInProgress = false;

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value ?? '-';
        return element.innerHTML;
    }

    async function dashboardFetch(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json', Authorization: `Bearer ${dashboardToken}` } });
        if (response.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.location.href = '{{ route('login') }}';
            return null;
        }
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'No fue posible cargar el menú principal.');
        return data;
    }

    function drawChart(elementId, type, labels, values, colors) {
        const canvas = document.getElementById(elementId);
        Chart.getChart(canvas)?.destroy();
        const chart = new Chart(canvas, {
            type,
            data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: type === 'bar' ? 'bottom' : 'right' } }, scales: type === 'bar' ? { y: { beginAtZero: true, ticks: { precision: 0 } } } : {} },
        });
        dashboardCharts.push(chart);
    }

    function renderSummary(summary) {
        const cards = [
            ['total_pacientes', 'Pacientes', 'people', 'primary'],
            ['total_registros', 'Registros GES', 'file-earmark-medical', 'dark'],
            ['registros_pendientes', 'Pendientes', 'hourglass-split', 'warning'],
            ['registros_en_proceso', 'En proceso', 'arrow-repeat', 'info'],
            ['registros_completados', 'Completados', 'check2-circle', 'success'],
            ['registros_sin_asignar', 'Sin asignar', 'person-exclamation', 'danger'],
        ];
        document.getElementById('summary-cards').innerHTML = cards.map(([key, label, icon, color]) => `<div class="col-6 col-xl-2"><div class="card shadow-sm h-100 border-start border-4 border-${color}"><div class="card-body"><div class="small text-muted">${label}</div><div class="d-flex justify-content-between align-items-end"><strong class="fs-2">${escapeHtml(summary[key])}</strong><i class="bi bi-${icon} fs-3 text-${color}" aria-hidden="true"></i></div></div></div></div>`).join('');
    }

    function renderTables(load, records) {
        document.getElementById('operator-load').innerHTML = load.length ? load.map((operator) => `<tr><td>${escapeHtml(operator.nombre)}</td><td>${operator.total_activas}</td><td><span class="badge text-bg-light border">${operator.carga_ponderada}</span></td></tr>`).join('') : '<tr><td colspan="3" class="text-muted">Sin carga activa.</td></tr>';
        document.getElementById('operator-records').innerHTML = records.length ? records.map((operator) => `<tr><td>${escapeHtml(operator.nombre)}</td><td class="text-end fw-semibold">${operator.total_registros}</td></tr>`).join('') : '<tr><td colspan="2" class="text-muted">Sin registros asignados.</td></tr>';
    }

    function renderMilestones(milestones) {
        document.getElementById('milestones').innerHTML = [['pendientes', 'Pendientes', 'warning'], ['completados', 'Completados', 'success']].map(([key, label, color]) => `<div class="col-6"><div class="small text-muted">${label}</div><strong class="display-6 text-${color}">${escapeHtml(milestones[key])}</strong></div>`).join('');
    }

    async function loadDashboard() {
        if (dashboardRequestInProgress) return;
        if (!dashboardToken) { window.location.href = '{{ route('login') }}'; return; }
        dashboardRequestInProgress = true;
        refreshButton.disabled = true;
        refreshButton.setAttribute('aria-busy', 'true');
        refreshButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Actualizando...';
        dashboardLoading.classList.remove('d-none');
        dashboardError.classList.add('d-none');
        dashboardCharts.forEach((chart) => chart.destroy());
        dashboardCharts = [];
        try {
            const [summary, distributions, load, records, milestones, complexity] = await Promise.all([
                dashboardFetch('{{ url('/api/dashboard/resumen') }}'),
                dashboardFetch('{{ url('/api/dashboard/distribuciones') }}'),
                dashboardFetch('{{ url('/api/dashboard/carga-operadores') }}'),
                dashboardFetch('{{ url('/api/dashboard/registros-por-operador') }}'),
                dashboardFetch('{{ url('/api/dashboard/hitos') }}'),
                dashboardFetch('{{ url('/api/dashboard/complejidad-promedio') }}'),
            ]);
            if (!summary) return;
            renderSummary(summary);
            renderTables(load?.data || [], records?.data || []);
            renderMilestones(milestones || {});
            document.getElementById('complexity-value').textContent = complexity?.promedio ?? '0.0';
            const palette = ['#176b87', '#d99a2b', '#5a9367', '#b64b4b', '#6c757d', '#9b6b9e'];
            drawChart('status-chart', 'doughnut', ['Pendientes', 'En proceso', 'Completados', 'Sin asignar'], [summary.registros_pendientes, summary.registros_en_proceso, summary.registros_completados, summary.registros_sin_asignar], palette);
            drawChart('priority-chart', 'bar', (distributions?.prioridades || []).map((item) => item.label), (distributions?.prioridades || []).map((item) => item.total), palette);
            drawChart('type-chart', 'doughnut', (distributions?.tipos_registro || []).map((item) => item.label), (distributions?.tipos_registro || []).map((item) => item.total), palette);
            drawChart('pathology-chart', 'bar', (distributions?.patologias || []).map((item) => item.label), (distributions?.patologias || []).map((item) => item.total), palette);
        } catch (requestError) {
            dashboardError.textContent = requestError.message;
            dashboardError.classList.remove('d-none');
        } finally {
            dashboardLoading.classList.add('d-none');
            dashboardRequestInProgress = false;
            refreshButton.disabled = false;
            refreshButton.removeAttribute('aria-busy');
            refreshButton.innerHTML = '<i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Actualizar';
        }
    }

    document.getElementById('refresh-dashboard').addEventListener('click', loadDashboard);
    loadDashboard();
</script>
@endsection
