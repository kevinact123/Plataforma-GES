<footer class="border-top bg-light py-3 mt-auto">
    <div class="container-fluid text-center text-muted small">
        Plataforma GES
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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

    const currentUser = JSON.parse(localStorage.getItem('auth_user') || 'null');
    const currentUserLabel = document.getElementById('current-user-label');
    const currentUserDetail = document.getElementById('current-user-detail');

    if (currentUser && currentUserLabel) {
        const role = currentUser.es_admin ? 'Administrador' : (currentUser.rol || 'Digitadora');
        currentUserLabel.textContent = `${currentUser.nombre} / ${role}`;
        if (currentUserDetail) currentUserDetail.textContent = `${currentUser.nombre} ${currentUser.apellido || ''} · ${role}`;
    }

    if (currentUser?.es_admin) {
        document.getElementById('assignments-nav-item')?.classList.remove('d-none');
    }
</script>