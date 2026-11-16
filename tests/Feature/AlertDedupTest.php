<?php

namespace Tests\Feature;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Services\Salud\ProcesadorMediciones;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never deletes the local database data. */
class AlertDedupTest extends TestCase
{
    use DatabaseTransactions;

    private function crewMember(): Astronauta
    {
        return Astronauta::create(['nombre' => 'Dedup', 'mision' => 'T-1', 'entorno' => 'base_marciana']);
    }

    public function test_simulated_source_does_not_repeat_same_indicator_alert_within_window(): void
    {
        $a = $this->crewMember();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 150], 'simulado', now()->subMinutes(3));
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 152], 'simulado', now()->subMinutes(2));
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 154], 'simulado', now()->subMinute());

        $this->assertSame(1, AlertaSaliente::where('astronauta_id', $a->id)->count());
    }

    public function test_different_indicator_and_different_crew_member_still_alert(): void
    {
        $a = $this->crewMember();
        $b = $this->crewMember();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 150], 'simulado');
        $p->registrar($a, 'saturacion_o2', ['valor' => 80], 'simulado');
        $p->registrar($b, 'frecuencia_cardiaca', ['valor' => 150], 'simulado');

        $this->assertSame(2, AlertaSaliente::where('astronauta_id', $a->id)->count());
        $this->assertSame(1, AlertaSaliente::where('astronauta_id', $b->id)->count());
    }

    public function test_alerts_again_after_window_elapses(): void
    {
        $a = $this->crewMember();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 150], 'simulado');
        AlertaSaliente::where('astronauta_id', $a->id)->update(['created_at' => now()->subMinutes(30)]);
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 155], 'simulado', now()->addMinutes(5));

        $this->assertSame(2, AlertaSaliente::where('astronauta_id', $a->id)->count());
    }

    public function test_manual_and_watch_sources_alert_every_time(): void
    {
        $a = $this->crewMember();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 150], 'manual', now()->subMinutes(3));
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 152], 'manual', now()->subMinutes(2));
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 154], 'reloj', now()->subMinute());

        $this->assertSame(3, AlertaSaliente::where('astronauta_id', $a->id)->count());
    }
}
