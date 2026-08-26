<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Plataforma GES') - Hospital / Sección GES</title>

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

    @include('layouts.navbar')

    <div class="container-fluid app-shell flex-grow-1">
        <div class="row app-row h-100">
            @include('layouts.sidebar')

            <!-- Contenido Principal Dinámico -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                @yield('content')
            </main>
        </div>
    </div>

    @include('layouts.footer')
    @yield('scripts')
</body>
</html>