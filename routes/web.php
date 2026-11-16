<?php

use App\Livewire\Alertas;
use App\Livewire\ContextoMision;
use App\Livewire\Guia;
use App\Livewire\Indicador;
use App\Livewire\Panel;
use App\Livewire\Registrar;
use App\Livewire\Rutinas;
use App\Livewire\SiluetaDemo;
use App\Livewire\Tripulacion;
use Illuminate\Support\Facades\Route;

Route::get('/', Panel::class)->name('panel');
Route::get('/tripulacion', Tripulacion::class)->name('tripulacion');
Route::get('/indicador', Indicador::class)->name('indicador');
Route::get('/registrar', Registrar::class)->name('registrar');
Route::get('/rutinas', Rutinas::class)->name('rutinas');
Route::get('/alertas', Alertas::class)->name('alertas');
Route::get('/guia', Guia::class)->name('guia');
Route::get('/mision', ContextoMision::class)->name('mision');

// Demo de la silueta: solo disponible fuera de producción.
if (! app()->environment('production')) {
    Route::get('/demo/silueta', SiluetaDemo::class)->name('silueta.demo');
    Route::get('/simulacion', \App\Livewire\Simulacion::class)->name('simulacion');
}

if (class_exists(\App\Livewire\Historial::class)) {
    Route::get('/historial', \App\Livewire\Historial::class)->name('historial');
}

if (class_exists(\App\Livewire\Sistemas::class)) {
    Route::get('/sistemas', \App\Livewire\Sistemas::class)->name('sistemas');
}

if (class_exists(\App\Livewire\NuevoTripulante::class)) {
    Route::get('/tripulacion/nuevo', \App\Livewire\NuevoTripulante::class)->name('tripulacion.nuevo');
}

if (class_exists(\App\Livewire\FichaTripulante::class)) {
    Route::get('/tripulacion/{astronauta}/expediente', \App\Livewire\FichaTripulante::class)->name('tripulacion.ficha');
}
