<?php

use App\Livewire\Indicador;
use App\Livewire\Panel;
use App\Livewire\Tripulacion;
use Illuminate\Support\Facades\Route;

Route::get('/', Panel::class)->name('panel');

Route::get('/tripulacion', Tripulacion::class)->name('tripulacion');

Route::get('/indicador', Indicador::class)->name('indicador');

Route::get('/demo/silueta', \App\Livewire\SiluetaDemo::class)->name('silueta.demo');

// Las pantallas Registrar y Rutinas las crean los bloques G y H; el guard evita romper el resto mientras tanto.
if (class_exists(\App\Livewire\Registrar::class)) {
    Route::get('/registrar', \App\Livewire\Registrar::class)->name('registrar');
}
if (class_exists(\App\Livewire\Rutinas::class)) {
    Route::get('/rutinas', \App\Livewire\Rutinas::class)->name('rutinas');
}
