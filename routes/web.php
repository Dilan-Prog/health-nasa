<?php

use App\Livewire\Panel;
use Illuminate\Support\Facades\Route;

Route::get('/', Panel::class)->name('panel');

Route::get('/demo/silueta', \App\Livewire\SiluetaDemo::class)->name('silueta.demo');
