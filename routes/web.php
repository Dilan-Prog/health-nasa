<?php

use App\Livewire\Panel;
use App\Livewire\Tripulacion;
use Illuminate\Support\Facades\Route;

Route::get('/', Panel::class)->name('panel');

Route::get('/tripulacion', Tripulacion::class)->name('tripulacion');

Route::get('/demo/silueta', \App\Livewire\SiluetaDemo::class)->name('silueta.demo');
