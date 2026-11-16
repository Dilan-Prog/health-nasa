<?php

use App\Livewire\Alertas;
use App\Livewire\ContextoMision;
use App\Livewire\Guia;
use App\Livewire\Indicador;
use App\Livewire\MedicoVuelo;
use App\Livewire\Panel;
use App\Livewire\Registrar;
use App\Livewire\Rutinas;
use App\Livewire\SiluetaDemo;
use App\Livewire\Tripulacion;
use Illuminate\Support\Facades\Route;

// Acceso público: login y Aviso legal. El resto exige sesión solo si MISION_EXIGIR_LOGIN=true.
Route::get('/acceso', \App\Livewire\Login::class)->name('login');
Route::post('/cerrar-sesion', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');
if (class_exists(\App\Livewire\AvisoLegal::class)) {
    Route::get('/aviso-legal', \App\Livewire\AvisoLegal::class)->name('aviso-legal');
}

Route::middleware('login.requerido')->group(function () {
    Route::get('/', Panel::class)->name('panel');
    Route::get('/tripulacion', Tripulacion::class)->name('tripulacion');
    Route::get('/indicador', Indicador::class)->name('indicador');
    Route::get('/registrar', Registrar::class)->name('registrar');
    Route::get('/rutinas', Rutinas::class)->name('rutinas');
    Route::get('/alertas', Alertas::class)->name('alertas');
    Route::get('/guia', Guia::class)->name('guia');
    Route::get('/mision', ContextoMision::class)->name('mision');
    Route::get('/medico-de-vuelo', MedicoVuelo::class)->name('medico');

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

    if (class_exists(\App\Livewire\Botiquin::class)) {
        Route::get('/botiquin', \App\Livewire\Botiquin::class)->name('botiquin');
    }
    if (class_exists(\App\Livewire\ColaTierra::class)) {
        Route::get('/cola-a-tierra', \App\Livewire\ColaTierra::class)->name('cola-tierra');
    }
});
