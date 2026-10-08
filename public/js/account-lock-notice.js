(function () {
    const DURACION_MS = 30000;
    const MENSAJE = 'Tu cuenta ha sido bloqueada por demasiados intentos fallidos. Debes ponerte en contacto con el administrador para reactivar tu cuenta.';

    window.mostrarBloqueoCuenta = function () {
        const anterior = document.getElementById('account-lock-notice');
        if (anterior) {
            clearTimeout(anterior.dataset.timer);
            anterior.remove();
        }

        const aviso = document.createElement('div');
        aviso.id = 'account-lock-notice';
        aviso.className = 'position-fixed top-50 start-50 translate-middle p-4 rounded shadow-lg bg-danger text-white d-flex align-items-start gap-3 fs-5';
        aviso.style.zIndex = '2000';
        aviso.style.pointerEvents = 'none';
        aviso.style.maxWidth = '90vw';
        aviso.style.width = '460px';
        aviso.setAttribute('role', 'alert');
        aviso.innerHTML = '<i class="bi bi-lock-fill fs-2"></i><div></div>';
        aviso.lastChild.textContent = MENSAJE;
        document.body.appendChild(aviso);

        aviso.dataset.timer = setTimeout(() => aviso.remove(), DURACION_MS);
    };

    window.esBloqueoDeCuenta = function (response, data) {
        return response.status === 423 || (data && data.bloqueada === true);
    };
})();