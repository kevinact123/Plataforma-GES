<footer class="border-top bg-light py-3 mt-auto">
    <div class="container-fluid text-center text-muted small">
        Plataforma GES
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/auto-dismiss-alerts.js') }}"></script>
<script>
    document.getElementById('logout-button')?.addEventListener('click', async function () {
        if (this.disabled) return;

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Cerrando sesión...';
        const token = localStorage.getItem('auth_token');

        try {
            if (token) {
                await fetch('{{ url('/api/logout') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${token}`,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
            }
        } finally {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.location.href = '{{ route('login') }}';
        }
    });

    // --- Cierre automático de sesión por inactividad (14 minutos = 840000 ms) ---
    (function () {
        const INACTIVITY_LIMIT_MS = 840000;
        let inactivityTimer = null;

        async function cerrarSesionPorInactividad() {
            const storedToken = localStorage.getItem('auth_token');
            if (!storedToken) return;

            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');

            try {
                await fetch('{{ url('/api/logout') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${storedToken}`,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
            } catch (error) {
                // Si la petición falla, igualmente se redirige al login.
            }

            sessionStorage.setItem('ges_session_expired_notice', '1');
            window.location.href = '{{ route('login') }}';
        }

        function reiniciarTemporizadorInactividad() {
            if (!localStorage.getItem('auth_token')) return;
            if (inactivityTimer) clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(cerrarSesionPorInactividad, INACTIVITY_LIMIT_MS);
        }

        ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach((eventName) => {
            document.addEventListener(eventName, reiniciarTemporizadorInactividad, { passive: true });
        });

        reiniciarTemporizadorInactividad();

        // Autoridad definitiva: si el servidor responde 401 (token expirado por
        // inactividad según Sanctum/last_used_at), se cierra la sesión aunque
        // el temporizador del navegador haya sido manipulado o detenido.
        const fetchOriginal = window.fetch;
        window.fetch = function (...args) {
            return fetchOriginal.apply(this, args).then((response) => {
                const requestUrl = typeof args[0] === 'string' ? args[0] : (args[0]?.url || '');
                if (response.status === 401 && requestUrl.includes('/api/') && localStorage.getItem('auth_token')) {
                    localStorage.removeItem('auth_token');
                    localStorage.removeItem('auth_user');
                    sessionStorage.setItem('ges_session_expired_notice', '1');
                    window.location.href = '{{ route('login') }}';
                }
                return response;
            });
        };
    })();

    // --- Notificación visual de inactividad (capa informativa independiente) ---
    // No reutiliza ni modifica el temporizador de cierre existente; solo observa
    // los mismos eventos de actividad para mostrar avisos previos a los 14 min.
    (function () {
        const TOTAL_LIMIT_MS = 840000; // debe reflejar el mismo límite del cierre automático (14 min)
        const HITOS_AVISO_MS = [300000, 600000, 780000]; // 5, 10 y 13 minutos
        let ultimaActividad = Date.now();
        let avisosMostrados = new Set();
        let cajaAviso = null;

        function obtenerCajaAviso() {
            if (cajaAviso) return cajaAviso;
            cajaAviso = document.createElement('div');
            cajaAviso.id = 'inactivity-warning-alert';
            cajaAviso.className = 'alert alert-warning shadow-sm d-none';
            cajaAviso.setAttribute('role', 'alert');
            cajaAviso.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:1080;max-width:420px;';
            document.body.appendChild(cajaAviso);
            return cajaAviso;
        }

        function ocultarAviso() {
            obtenerCajaAviso().classList.add('d-none');
        }

        function mostrarAviso(mensaje) {
            const caja = obtenerCajaAviso();
            caja.textContent = `⚠️ ${mensaje}`;
            caja.classList.remove('d-none');
        }

        function registrarActividadVisual() {
            ultimaActividad = Date.now();
            avisosMostrados.clear();
            ocultarAviso();
        }

        ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach((eventName) => {
            document.addEventListener(eventName, registrarActividadVisual, { passive: true });
        });

        setInterval(function () {
            if (!localStorage.getItem('auth_token')) return;

            const inactivoMs = Date.now() - ultimaActividad;
            if (inactivoMs >= TOTAL_LIMIT_MS) return;

            const minutosRestantes = Math.max(1, Math.ceil((TOTAL_LIMIT_MS - inactivoMs) / 60000));

            if (inactivoMs >= HITOS_AVISO_MS[2] && !avisosMostrados.has(2)) {
                avisosMostrados.add(2);
                mostrarAviso(`Tu sesión se cerrará en ${minutosRestantes} minuto si no realizas ninguna actividad.`);
            } else if (inactivoMs >= HITOS_AVISO_MS[1] && !avisosMostrados.has(1)) {
                avisosMostrados.add(1);
                mostrarAviso(`Has estado inactivo durante 10 minutos. Tu sesión se cerrará en ${minutosRestantes} minutos si no realizas ninguna actividad.`);
            } else if (inactivoMs >= HITOS_AVISO_MS[0] && !avisosMostrados.has(0)) {
                avisosMostrados.add(0);
                mostrarAviso(`Has estado inactivo durante 5 minutos. Tu sesión se cerrará en ${minutosRestantes} minutos si no realizas ninguna actividad.`);
            } else if (avisosMostrados.size > 0) {
                mostrarAviso(`Tu sesión se cerrará en ${minutosRestantes} minuto${minutosRestantes === 1 ? '' : 's'} si no realizas ninguna actividad.`);
            }
        }, 1000);
    })();

    const currentUser = JSON.parse(localStorage.getItem('auth_user') || 'null');
    const currentUserLabel = document.getElementById('current-user-label');
    const currentUserDetail = document.getElementById('current-user-detail');

    if (currentUser && currentUserLabel) {
        const role = currentUser.es_admin ? 'Administrador' : ((currentUser.rol === 'Digitadora' ? 'Digitador/a' : currentUser.rol) || 'Digitador/a');
        currentUserLabel.textContent = `${currentUser.nombre} / ${role}`;
        if (currentUserDetail) currentUserDetail.textContent = `${currentUser.nombre} ${currentUser.apellido || ''} · ${role}`;
    }

    function applyMenuPermissions(permissions) {
        if (!Array.isArray(permissions)) return;
        const granted = new Set(permissions);
        document.querySelectorAll('[data-permission]').forEach((item) => {
            item.classList.toggle('d-none', !granted.has(item.dataset.permission));
        });
        const canAssign = permissions.some((p) => ['administrar_usuarios', 'asignar_pacientes', 'reasignar_pacientes'].includes(p));
        document.getElementById('assignments-nav-item')?.classList.toggle('d-none', !canAssign);
    }

    applyMenuPermissions(currentUser?.permissions);

    // Las sesiones anteriores no guardaban permisos: se recargan desde la API.
    if (currentUser && !Array.isArray(currentUser.permissions) && localStorage.getItem('auth_token')) {
        fetch('/api/me', { headers: { Accept: 'application/json', Authorization: `Bearer ${localStorage.getItem('auth_token')}` } })
            .then((response) => response.ok ? response.json() : null)
            .then((data) => {
                const user = data?.user || data?.data || data;
                if (!user || !Array.isArray(user.permissions)) return;
                localStorage.setItem('auth_user', JSON.stringify({ ...currentUser, ...user }));
                applyMenuPermissions(user.permissions);
            })
            .catch(() => {});
    }
</script>