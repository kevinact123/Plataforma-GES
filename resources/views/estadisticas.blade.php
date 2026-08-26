@extends('layouts.app')

@section('title', 'Estadísticas')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 pt-3 pb-3 mb-4 border-bottom">
    <div>
        <div class="text-uppercase small text-muted fw-semibold">Análisis GES</div>
        <h1 class="h2 mb-1"><i class="bi bi-bar-chart me-2" aria-hidden="true"></i>Estadísticas</h1>
        <p class="text-muted mb-0">Indicadores calculados sobre la información a la que tienes acceso.</p>
    </div>
    <button class="btn btn-outline-secondary" id="refresh-statistics" type="button"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Actualizar</button>
</div>

<div id="statistics-error" class="alert alert-danger d-none" role="alert"></div>
<div id="statistics-loading" class="alert alert-info" role="status">Cargando estadísticas...</div>

<section class="row g-3 mb-4" aria-label="Indicadores estadísticos">
    <div class="col-6 col-lg-3"><div class="card shadow-sm border-start border-4 border-primary h-100"><div class="card-body"><div class="small text-muted">Registros analizados</div><strong class="display-6 text-primary" id="total-records">-</strong></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm border-start border-4 border-info h-100"><div class="card-body"><div class="small text-muted">Evaluaciones</div><strong class="display-6 text-info" id="total-evaluations">-</strong></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm border-start border-4 border-warning h-100"><div class="card-body"><div class="small text-muted">Complejidad promedio</div><strong class="display-6 text-warning" id="average-complexity">-</strong></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm border-start border-4 border-success h-100"><div class="card-body"><div class="small text-muted">Tipos evaluados</div><strong class="display-6 text-success" id="evaluated-types">-</strong></div></div></div>
</section>

<section class="row g-3 mb-4">
    <div class="col-12 col-xl-4"><div class="card shadow-sm h-100"><div class="card-header bg-white"><h2 class="h6 mb-0">Registros por prioridad</h2></div><div class="card-body chart-container"><canvas id="priority-chart"></canvas></div></div></div>
    <div class="col-12 col-xl-4"><div class="card shadow-sm h-100"><div class="card-header bg-white"><h2 class="h6 mb-0">Complejidad por tipo</h2></div><div class="card-body chart-container"><canvas id="type-chart"></canvas></div></div></div>
    <div class="col-12 col-xl-4"><div class="card shadow-sm h-100"><div class="card-header bg-white"><h2 class="h6 mb-0">Complejidad por patología</h2></div><div class="card-body chart-container"><canvas id="pathology-chart"></canvas></div></div></div>
</section>

<section class="row g-3 mb-4">
    <div class="col-12 col-lg-7"><div class="card shadow-sm h-100"><div class="card-header bg-white"><h2 class="h6 mb-0">Rendimiento por operador</h2></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Operador</th><th>Promedio</th><th>Evaluaciones</th><th>Carga actual</th></tr></thead><tbody id="operator-table"></tbody></table></div></div></div>
    <div class="col-12 col-lg-5"><div class="card shadow-sm h-100"><div class="card-header bg-white"><h2 class="h6 mb-0">Detalle por tipo de registro</h2></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Tipo</th><th>Promedio</th><th>Total</th></tr></thead><tbody id="type-table"></tbody></table></div></div></div>
</section>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const statisticsToken = localStorage.getItem('auth_token');
    const statisticsLoading = document.getElementById('statistics-loading');
    const statisticsError = document.getElementById('statistics-error');
    const statisticsButton = document.getElementById('refresh-statistics');
    let statisticsCharts = [];
    let statisticsLoadingInProgress = false;

    function escapeHtml(value) { const element = document.createElement('div'); element.textContent = value ?? '-'; return element.innerHTML; }
    async function statisticsFetch(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json', Authorization: `Bearer ${statisticsToken}` } });
        if (response.status === 401) { localStorage.removeItem('auth_token'); localStorage.removeItem('auth_user'); window.location.href = '{{ route('login') }}'; return null; }
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'No fue posible cargar las estadísticas.');
        return data;
    }
    function drawStatisticsChart(id, type, labels, values, colors) {
        const canvas = document.getElementById(id); Chart.getChart(canvas)?.destroy();
        statisticsCharts.push(new Chart(canvas, { type, data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: type === 'bar' ? 'bottom' : 'right' } }, scales: type === 'bar' ? { y: { beginAtZero: true } } : {} } }));
    }
    async function loadStatistics() {
        if (statisticsLoadingInProgress) return;
        if (!statisticsToken) { window.location.href = '{{ route('login') }}'; return; }
        statisticsLoadingInProgress = true; statisticsButton.disabled = true; statisticsButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Actualizando...'; statisticsLoading.classList.remove('d-none'); statisticsError.classList.add('d-none');
        statisticsCharts.forEach((chart) => chart.destroy()); statisticsCharts = [];
        try {
            const [distributions, types, operators, pathologies, records] = await Promise.all([
                statisticsFetch('{{ url('/api/dashboard/distribuciones') }}'), statisticsFetch('{{ url('/api/complejidad/promedio-por-tipo') }}'), statisticsFetch('{{ url('/api/complejidad/operadores') }}'), statisticsFetch('{{ url('/api/complejidad/patologias') }}'), statisticsFetch('{{ url('/api/complejidad') }}'),
            ]);
            const typeData = types?.data || [], operatorData = operators?.data || [], pathologyData = pathologies?.data || [], recordData = records?.data || [];
            document.getElementById('total-records').textContent = (distributions?.patologias || []).reduce((total, item) => total + item.total, 0);
            document.getElementById('total-evaluations').textContent = recordData.length;
            document.getElementById('average-complexity').textContent = recordData.length ? (recordData.reduce((total, item) => total + Number(item.puntaje || 0), 0) / recordData.length).toFixed(2) : '0.00';
            document.getElementById('evaluated-types').textContent = typeData.length;
            document.getElementById('operator-table').innerHTML = operatorData.length ? operatorData.map((item) => `<tr><td>${escapeHtml(item.nombre)}</td><td>${item.promedio}</td><td>${item.total_evaluaciones}</td><td>${item.carga_actual}</td></tr>`).join('') : '<tr><td colspan="4" class="text-muted">Sin evaluaciones.</td></tr>';
            document.getElementById('type-table').innerHTML = typeData.length ? typeData.map((item) => `<tr><td>${escapeHtml(item.nombre)}</td><td>${item.promedio}</td><td>${item.total_evaluaciones}</td></tr>`).join('') : '<tr><td colspan="3" class="text-muted">Sin evaluaciones.</td></tr>';
            const colors = ['#176b87', '#d99a2b', '#5a9367', '#b64b4b', '#6c757d', '#9b6b9e'];
            drawStatisticsChart('priority-chart', 'doughnut', (distributions?.prioridades || []).map((item) => item.label), (distributions?.prioridades || []).map((item) => item.total), colors);
            drawStatisticsChart('type-chart', 'bar', typeData.map((item) => item.nombre), typeData.map((item) => item.promedio), colors);
            drawStatisticsChart('pathology-chart', 'bar', pathologyData.map((item) => item.nombre), pathologyData.map((item) => item.promedio), colors);
        } catch (error) { statisticsError.textContent = error.message; statisticsError.classList.remove('d-none'); } finally { statisticsLoading.classList.add('d-none'); statisticsLoadingInProgress = false; statisticsButton.disabled = false; statisticsButton.innerHTML = '<i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Actualizar'; }
    }
    statisticsButton.addEventListener('click', loadStatistics); loadStatistics();
</script>
@endsection
