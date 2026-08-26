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
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white fw-semibold" href="#" id="user-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                        <span id="current-user-label">Usuario / Digitadora</span>
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