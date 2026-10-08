<div class="col-12 col-md-3 col-lg-2 px-0 sidebar-column d-flex">
    <nav class="sidebar collapse d-md-block" id="sidebarMenu" aria-label="Navegación lateral">
        <div class="position-sticky pt-3">
            <ul class="nav flex-column">
                <li class="nav-item" data-permission="ver_dashboard"><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>Menú principal</a></li>
                <li class="nav-item" data-permission="ver_registros"><a class="nav-link {{ request()->routeIs('pacientes') ? 'active' : '' }}" href="{{ route('pacientes') }}"><i class="bi bi-people me-2" aria-hidden="true"></i>Pacientes</a></li>
                <li class="nav-item" data-permission="ver_registros"><a class="nav-link {{ request()->routeIs('registros') ? 'active' : '' }}" href="{{ route('registros') }}"><i class="bi bi-file-earmark-medical me-2" aria-hidden="true"></i>Registros GES</a></li>
                <li class="nav-item" data-permission="ver_registros"><a class="nav-link {{ request()->routeIs('documentacion') ? 'active' : '' }}" href="{{ route('documentacion') }}"><i class="bi bi-folder2-open me-2" aria-hidden="true"></i>Documentación</a></li>
                <li class="nav-item d-none" id="assignments-nav-item"><a class="nav-link {{ request()->routeIs('asignaciones') ? 'active' : '' }}" href="{{ route('asignaciones') }}"><i class="bi bi-diagram-3 me-2" aria-hidden="true"></i>Configuraciones</a></li>
                <li class="nav-item" data-permission="ver_registros"><a class="nav-link {{ request()->routeIs('hitos') ? 'active' : '' }}" href="{{ route('hitos') }}"><i class="bi bi-list-check me-2" aria-hidden="true"></i>Hitos</a></li>
            </ul>
        </div>
    </nav>
</div>