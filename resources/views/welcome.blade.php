@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-house-door me-2"></i>Bienvenido a la Plataforma GES</h1>
</div>

<div class="alert alert-info shadow-sm" role="alert">
    <h4 class="alert-heading"><i class="bi bi-check-circle me-2"></i>Estructura Visual Base Lista</h4>
    <p class="mb-0">El layout principal con Bootstrap y la navegabilidad base se han instalado correctamente. Esta vista servirá de base para construir los módulos de autenticación y carga laboral.</p>
</div>
@endsection

