<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm" aria-label="Navegación principal">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="{{ url('/') }}">
            <i class="bi bi-hospital me-2" aria-hidden="true"></i>Plataforma GES
        </a>
        <button class="btn btn-outline-light d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Abrir navegación lateral">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Abrir menú de usuario">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                <li class="nav-item me-lg-2">
                    <button class="btn btn-outline-light btn-sm" type="button" id="theme-toggle" aria-label="Cambiar a modo oscuro" title="Modo oscuro">
                        <i class="bi bi-moon-stars-fill" id="theme-toggle-icon" aria-hidden="true"></i>
                    </button>
                </li>
                <li class="nav-item dropdown me-lg-2" id="notif-wrapper">
                    <button class="btn btn-outline-light btn-sm position-relative" type="button" id="notif-button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notificaciones" title="Notificaciones">
                        <i class="bi bi-bell-fill" aria-hidden="true"></i>
                        <span id="notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow p-0" style="width: 22rem; max-width: 92vw;">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <strong>Notificaciones</strong>
                            <button type="button" class="btn btn-link btn-sm p-0" id="notif-read-all">Marcar todas como leídas</button>
                        </div>
                        <div id="notif-list" style="max-height: 20rem; overflow-y: auto;">
                            <div class="text-muted small p-3">Sin notificaciones.</div>
                        </div>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white fw-semibold" href="#" id="user-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                        <span id="current-user-label">Usuario / Digitador/a</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="user-menu">
                        <li><h6 class="dropdown-header" id="current-user-detail">Sesión activa</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button class="dropdown-item text-danger" type="button" id="logout-button">
                                <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Cerrar Sesión
                            </button>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<script>
(function () {
    const root = document.documentElement;
    const button = document.getElementById('theme-toggle');
    const icon = document.getElementById('theme-toggle-icon');

    function syncCharts(dark) {
        if (!window.Chart) return;
        Chart.defaults.color = dark ? '#dee2e6' : '#666';
        Chart.defaults.borderColor = dark ? 'rgba(255,255,255,.15)' : 'rgba(0,0,0,.1)';
        Object.values(Chart.instances || {}).forEach(chart => chart.update());
    }

    function apply(dark) {
        root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        icon.className = dark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        const label = dark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
        button.setAttribute('aria-label', label);
        button.title = label;
        syncCharts(dark);
    }

    button.addEventListener('click', () => {
        const dark = root.getAttribute('data-bs-theme') !== 'dark';
        localStorage.setItem('theme', dark ? 'dark' : 'light');
        apply(dark);
    });

    apply(root.getAttribute('data-bs-theme') === 'dark');
    document.addEventListener('DOMContentLoaded', () => syncCharts(root.getAttribute('data-bs-theme') === 'dark'));
})();

(function () {
    const badge = document.getElementById('notif-badge');
    const list = document.getElementById('notif-list');
    const readAll = document.getElementById('notif-read-all');
    const token = () => localStorage.getItem('auth_token');
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    async function call(path, method = 'GET') {
        if (!token()) return null;
        const res = await fetch('/api' + path, { method, headers: { Accept: 'application/json', Authorization: 'Bearer ' + token() } });
        return res.ok ? res.json() : null;
    }

    function render(payload) {
        const total = payload.no_leidas || 0;
        badge.textContent = total > 99 ? '99+' : total;
        badge.classList.toggle('d-none', total === 0);
        if (!payload.data.length) {
            list.innerHTML = '<div class="text-muted small p-3">Sin notificaciones.</div>';
            return;
        }
        list.innerHTML = payload.data.map(n => {
            const items = (n.registros || []).slice(0, 5).map(r =>
                `<li>#${esc(r.id_registro)}${r.paciente ? ' · ' + esc(r.paciente) : ''}${r.patologia ? ' · ' + esc(r.patologia) : ''}</li>`).join('');
            const extra = (n.registros || []).length > 5 ? `<li>... y ${n.registros.length - 5} más</li>` : '';
            return `<a href="/registros-ges" class="dropdown-item text-wrap border-bottom py-2 ${n.leida ? '' : 'bg-primary-subtle'}" data-notif="${esc(n.id)}">
                <div class="fw-semibold small">${esc(n.titulo)}</div>
                <ul class="small mb-1 ps-3">${items}${extra}</ul>
                <div class="text-muted" style="font-size:.7rem;">${n.fecha ? new Date(n.fecha).toLocaleString() : ''}</div></a>`;
        }).join('');
    }

    async function refresh() {
        const payload = await call('/notificaciones');
        if (payload) render(payload);
    }

    list.addEventListener('click', (e) => {
        const item = e.target.closest('[data-notif]');
        if (item) call('/notificaciones/' + item.dataset.notif + '/leer', 'POST');
    });
    readAll.addEventListener('click', async () => { await call('/notificaciones/leer-todas', 'POST'); refresh(); });

    document.addEventListener('DOMContentLoaded', () => { refresh(); setInterval(refresh, 30000); });
})();
</script>
