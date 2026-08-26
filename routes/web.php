<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('inicio');

Route::get('/login', function () {
    return view('login');
})->name('login');

Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

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
    return view('estadisticas');
})->name('estadisticas');
