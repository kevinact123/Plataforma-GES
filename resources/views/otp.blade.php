<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verificación OTP - Plataforma GES</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

</head>
<body class="bg-light">

<div class="container p-3">
    <div class="card login-card shadow-lg mx-auto" style="max-width: 420px;">
        <div class="login-header text-white text-center py-4 px-3 position-relative">
            <a href="{{ url('/login') }}" id="otp-cerrar" class="btn-close position-absolute top-0 end-0 m-3" style="z-index: 2;" aria-label="Volver al inicio de sesión" title="Volver al inicio de sesión"></a>
            <i class="bi bi-shield-lock fs-1 mb-2 d-block"></i>
            <h4 class="fw-bold mb-0">Verificación OTP</h4>
            <small class="text-white-50">Plataforma GES</small>
        </div>

        <div class="card-body p-4">
            <p class="text-center mb-1">Se envió un código a tu correo institucional.</p>
            <p class="text-center text-muted mb-4" id="otp-correo-enmascarado"></p>

            <div id="otp-error" class="alert alert-danger d-none" role="alert"></div>

            <form id="otp-form">
                <div class="mb-3">
                    <input
                        type="text"
                        class="form-control form-control-lg text-center fw-bold"
                        id="otp-codigo"
                        name="codigo"
                        placeholder="______"
                        maxlength="6"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        autocomplete="one-time-code"
                        style="letter-spacing: 0.5rem;"
                        required
                    >
                </div>

                <p class="text-center small text-muted mb-4" id="otp-contador-texto">
                    Código válido durante: <span id="otp-contador">45</span> segundos
                </p>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3" id="otp-button">
                    Verificar
                </button>

                <div class="text-center">
                    <button type="button" class="btn btn-link text-decoration-none small" id="otp-reenviar" disabled>
                        Reenviar código
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/auto-dismiss-alerts.js') }}"></script>
<script src="{{ asset('js/account-lock-notice.js') }}"></script>
<script>
    const correoEnmascarado = localStorage.getItem('otp_correo_enmascarado');

    if (!correoEnmascarado) {
        window.location.href = '{{ url('/login') }}';
    }

    if (correoEnmascarado) {
        document.getElementById('otp-correo-enmascarado').textContent = correoEnmascarado;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    document.getElementById('otp-cerrar').addEventListener('click', function () {
        localStorage.removeItem('otp_correo_enmascarado');
    });

    const contadorTextoEl = document.getElementById('otp-contador-texto');
    const reenviarBtn = document.getElementById('otp-reenviar');
    let segundosRestantes = 45;
    let intervalo = null;

    function iniciarContador() {
        segundosRestantes = 45;
        actualizarTextoContador();
        reenviarBtn.disabled = true;

        clearInterval(intervalo);
        intervalo = setInterval(() => {
            segundosRestantes -= 1;

            if (segundosRestantes <= 0) {
                clearInterval(intervalo);
                segundosRestantes = 0;
                contadorTextoEl.textContent = 'El código ha expirado.';
                reenviarBtn.disabled = false;
                return;
            }

            actualizarTextoContador();
        }, 1000);
    }

    function actualizarTextoContador() {
        const unidad = segundosRestantes === 1 ? 'segundo' : 'segundos';
        contadorTextoEl.textContent = 'Código válido durante: ' + segundosRestantes + ' ' + unidad;
    }

    iniciarContador();

    document.getElementById('otp-form').addEventListener('submit', async function (event) {
        event.preventDefault();

        const button = document.getElementById('otp-button');
        const error = document.getElementById('otp-error');
        error.classList.add('d-none');
        button.disabled = true;

        try {
            const response = await fetch('{{ url('/otp/verificar') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    codigo: document.getElementById('otp-codigo').value,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                if (response.status === 419) {
                    error.textContent = 'Tu sesión expiró por inactividad. Inicia sesión nuevamente.';
                    error.classList.remove('d-none');
                    setTimeout(() => window.location.href = '{{ url('/login') }}', 1500);
                    return;
                }

                if (esBloqueoDeCuenta(response, data)) {
                    mostrarBloqueoCuenta();
                    button.disabled = false;
                    return;
                }

                throw new Error((response.status === 429 ? 'Demasiados intentos. Espera unos minutos e inténtalo nuevamente.' : data.message) || 'No fue posible verificar el código.');
            }

            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('auth_user', JSON.stringify(data.user));
            localStorage.removeItem('otp_correo_enmascarado');
            window.location.href = '{{ url('/') }}';
        } catch (requestError) {
            error.textContent = requestError.message;
            error.classList.remove('d-none');
            button.disabled = false;
        }
    });

    reenviarBtn.addEventListener('click', async function () {
        const error = document.getElementById('otp-error');
        error.classList.add('d-none');
        reenviarBtn.disabled = true;

        try {
            const response = await fetch('{{ url('/otp/reenviar') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            const data = await response.json();

            if (!response.ok) {
                if (response.status === 419) {
                    error.textContent = 'Tu sesión expiró por inactividad. Inicia sesión nuevamente.';
                    error.classList.remove('d-none');
                    setTimeout(() => window.location.href = '{{ url('/login') }}', 1500);
                    return;
                }

                if (esBloqueoDeCuenta(response, data)) {
                    mostrarBloqueoCuenta();
                    reenviarBtn.disabled = false;
                    return;
                }

                throw new Error((response.status === 429 ? 'Demasiados intentos. Espera unos minutos e inténtalo nuevamente.' : data.message) || 'No fue posible reenviar el código.');
            }

            iniciarContador();
        } catch (requestError) {
            error.textContent = requestError.message;
            error.classList.remove('d-none');
            reenviarBtn.disabled = false;
        }
    });
</script>

</body>
</html>
