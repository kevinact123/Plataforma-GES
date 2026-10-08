<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar Sesión - Plataforma GES</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

</head>
<body class="bg-light d-flex align-items-center min-vh-100">

<div class="container p-3 w-100">
    <div class="card login-card shadow-lg mx-auto" style="max-width: 420px;">
        <div class="card-body p-4">
            <form id="login-form">
                <img
                    src="{{ asset('images/logo-hospital-san-carlos.jpg') }}"
                    alt="Hospital de San Carlos"
                    class="img-fluid d-block mx-auto mb-3"
                    style="width: 160px; max-width: 40vw;"
                >
                <div id="login-error" class="alert alert-danger d-none" role="alert"></div>
                <!-- Campo Rut / Usuario -->
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold">RUT o Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Usuario" autocomplete="username" required>
                    </div>
                </div>

                <!-- Campo Contraseña -->
                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" autocomplete="current-password" required>
                    </div>
                </div>

                <!-- Opciones secundarias -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember">
                        <label class="form-check-label small" for="remember">Recordarme</label>
                    </div>
                    <a href="#password-help" class="small text-decoration-none" data-bs-toggle="modal" data-bs-target="#password-help">¿Olvidaste tu clave?</a>
                </div>

                <!-- Botón de Ingreso -->
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" id="login-button">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
                </button>
            </form>
        </div>

        <div class="card-footer bg-light text-center py-3 border-0 rounded-bottom">
            <small class="text-muted">Sistema de Gestión de Garantías Explícitas en Salud</small>
            <div class="small text-muted">developed by Kevin Cuevas</div>
        </div>
    </div>
</div>

<div class="modal fade" id="password-help" tabindex="-1" aria-labelledby="password-help-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="password-help-title">Recuperar contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Solicita al administrador de la Plataforma GES que restablezca tu contraseña. Por seguridad, este sistema no envía claves por correo electrónico.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/auto-dismiss-alerts.js') }}"></script>
<script src="{{ asset('js/account-lock-notice.js') }}"></script>
<script>
    if (sessionStorage.getItem('ges_session_expired_notice') === '1') {
        sessionStorage.removeItem('ges_session_expired_notice');
        const expiredAlert = document.getElementById('login-error');
        expiredAlert.textContent = 'Tu sesión ha expirado por inactividad. Inicia sesión nuevamente.';
        expiredAlert.classList.remove('d-none');
    }

    document.getElementById('login-form').addEventListener('submit', async function (event) {
        event.preventDefault();

        const button = document.getElementById('login-button');
        const error = document.getElementById('login-error');
        error.classList.add('d-none');
        button.disabled = true;

        try {
            const response = await fetch('{{ url('/login') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    username: document.getElementById('username').value,
                    password: document.getElementById('password').value,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                if (response.status === 419) {
                    // La sesión expiró mientras la página estaba abierta: se recarga para obtener un token válido.
                    error.textContent = 'Tu sesión expiró por inactividad. Recargando la página...';
                    error.classList.remove('d-none');
                    setTimeout(() => window.location.reload(), 1500);
                    return;
                }

                if (esBloqueoDeCuenta(response, data)) {
                    mostrarBloqueoCuenta();
                    button.disabled = false;
                    return;
                }

                throw new Error((response.status === 429 ? 'Demasiados intentos. Espera unos minutos e inténtalo nuevamente.' : data.message) || 'No fue posible iniciar sesión.');
            }

            if (data.token) {
                localStorage.setItem('auth_token', data.token);
                localStorage.setItem('auth_user', JSON.stringify(data.user));
                window.location.href = '{{ url('/') }}';
                return;
            }

            localStorage.setItem('otp_correo_enmascarado', data.correo_enmascarado);
            window.location.href = '{{ url('/otp') }}';
        } catch (requestError) {
            error.textContent = requestError.message;
            error.classList.remove('d-none');
            button.disabled = false;
        }
    });
</script>
    
</body>
</html>