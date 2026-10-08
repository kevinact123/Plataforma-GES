<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('inicio');

Route::get('/login', function () {
    return view('login');
})->name('login');

Route::get('/otp', function () {
    return view('otp');
})->name('otp');

// Rutas con sesión de Laravel: mantienen el estado "OTP pendiente" hasta la verificación.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:otp-generar');
Route::post('/otp/verificar', [AuthController::class, 'verificarOtp'])->middleware('throttle:otp-verificar');
Route::post('/otp/reenviar', [AuthController::class, 'reenviarOtp'])->middleware('throttle:otp-reenviar');


Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

Route::get('/documentacion', function () {
    return view('documentacion');
})->name('documentacion');

Route::get('/pacientes', function () {
    return view('pacientes');
})->name('pacientes');

Route::get('/registros-ges', function () {
    return view('registros-ges');
})->name('registros');

Route::get('/asignaciones', function () {
    return view('asignaciones');
})->name('asignaciones');

Route::get('/hitos', function () {
    return view('hitos');
})->name('hitos');

Route::get('/estadisticas', function () {
    return redirect()->route('dashboard');
})->name('estadisticas');
