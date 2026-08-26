@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
    <h1 class="h2 mb-0"><i class="bi bi-{{ $icon }} me-2" aria-hidden="true"></i>{{ $title }}</h1>
</div>

<div id="module-error" class="alert alert-danger d-none" role="alert"></div>
<div id="module-loading" class="alert alert-info" role="status">Cargando información...</div>
<div id="module-content" class="row g-3"></div>
@endsection

@section('scripts')
<script>
    const moduleContent = document.getElementById('module-content');
    const moduleLoading = document.getElementById('module-loading');
    const moduleError = document.getElementById('module-error');
    const token = localStorage.getItem('auth_token');

    function renderValue(value) {
        if (Array.isArray(value)) {
            return `<div class="table-responsive"><table class="table table-striped align-middle mb-0"><tbody>${value.map((item) => `<tr><td>${renderValue(item)}</td></tr>`).join('')}</tbody></table></div>`;
        }

        if (value && typeof value === 'object') {
            return `<dl class="row mb-0">${Object.entries(value).map(([key, item]) => `<dt class="col-sm-5 text-muted">${key.replaceAll('_', ' ')}</dt><dd class="col-sm-7">${renderValue(item)}</dd>`).join('')}</dl>`;
        }

        return document.createTextNode(value ?? '-').textContent;
    }

    async function loadModule() {
        if (!token) {
            window.location.href = '{{ route('login') }}';
            return;
        }

        try {
            const response = await fetch('{{ $endpoint }}', {
                headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
            });

            if (response.status === 401) {
                localStorage.removeItem('auth_token');
                localStorage.removeItem('auth_user');
                window.location.href = '{{ route('login') }}';
                return;
            }

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'No fue posible cargar este módulo.');
            }

            const payload = data.data ?? data;
            moduleContent.innerHTML = `<div class="col-12"><div class="card shadow-sm"><div class="card-body">${renderValue(payload)}</div></div></div>`;
            moduleLoading.classList.add('d-none');
        } catch (error) {
            moduleLoading.classList.add('d-none');
            moduleError.textContent = error.message;
            moduleError.classList.remove('d-none');
        }
    }

    loadModule();
</script>
@endsection