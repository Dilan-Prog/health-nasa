<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Mediciones enviadas por el reloj (token por tripulante, ver `php artisan reloj:token`).
Route::post('/mediciones', [\App\Http\Controllers\Api\MedicionesController::class, 'store'])->name('api.mediciones');
