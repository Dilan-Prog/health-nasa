<?php

namespace App\Providers;

use App\Contracts\Orientador;
use App\Services\Ia\OrientadorProximamente;
use App\Services\Ia\OrientadorSimulado;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Costura de la IA: 'simulado' (por defecto) u 'ia_local' (Próximamente). Un modo desconocido cae en el simulado.
        $this->app->bind(Orientador::class, fn ($app) => config('ia.modo') === 'ia_local'
            ? new OrientadorProximamente()
            : $app->make(OrientadorSimulado::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
