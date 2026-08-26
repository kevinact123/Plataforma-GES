<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Plataforma GES</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

</head>
<body class="bg-light">

<div class="container p-3">
    <div class="card login-card shadow-lg mx-auto">
        <div class="login-header text-white text-center py-4 px-3">
            <i class="bi bi-hospital fs-1 mb-2 d-block"></i>
            <h4 class="fw-bold mb-0">Plataforma GES</h4>
            <small class="text-white-50">Acceso a Digitadoras y Personal Sanitario</small>
        </div>

        <div class="card-body p-4">
            <form id="login-form">
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
                    <a href="#" class="small text-decoration-none">¿Olvidaste tu clave?</a>
                </div>

                <!-- Botón de Ingreso -->
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" id="login-button">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
                </button>
            </form>
        </div>

        <div class="card-footer bg-light text-center py-3 border-0 rounded-bottom">
            <small class="text-muted">Sistema de Gestión de Garantías Explícitas en Salud</small>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('login-form').addEventListener('submit', async function (event) {
        event.preventDefault();

        const button = document.getElementById('login-button');
        const error = document.getElementById('login-error');
        error.classList.add('d-none');
        button.disabled = true;

        try {
            const response = await fetch('{{ url('/api/login') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    username: document.getElementById('username').value,
                    password: document.getElementById('password').value,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'No fue posible iniciar sesión.');
            }

            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('auth_user', JSON.stringify(data.user));
            window.location.href = '{{ url('/') }}';
        } catch (requestError) {
            error.textContent = requestError.message;
            error.classList.remove('d-none');
            button.disabled = false;
        }
    });
</script>
    
</body>
</html>