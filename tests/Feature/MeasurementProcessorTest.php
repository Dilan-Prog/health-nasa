<?php

namespace Tests\Feature;

use App\Events\MedicionRegistrada;
use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Services\Salud\ProcesadorMediciones;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): never deletes data from the local database. */
class MeasurementProcessorTest extends TestCase
{
    use DatabaseTransactions;

    private function crewMember(string $environment = 'estacion'): Astronauta
    {
        return Astronauta::create(['nombre' => 'Test', 'mision' => 'T-1', 'entorno' => $environment]);
    }

    public function test_normal_measurement_is_saved_without_an_alert(): void
    {
        Event::fake([MedicionRegistrada::class]);
        $a = $this->crewMember();

        $ev = ProcesadorMediciones::make()->registrar($a, 'frecuencia_cardiaca', ['valor' => 72]);

        $this->assertSame('normal', $ev->estado);
        $this->assertSame('ninguno', $ev->accion);
        $this->assertSame(0, AlertaSaliente::where('evaluacion_id', $ev->id)->count());
        Event::assertDispatched(MedicionRegistrada::class, fn ($e) => $e->astronautaId === $a->id && $e->estado === 'normal');
    }

    public function test_critical_measurement_queues_an_alert_to_ground(): void
    {
        Event::fake([MedicionRegistrada::class]);
        $a = $this->crewMember('base_marciana');

        $ev = ProcesadorMediciones::make()->registrar($a, 'frecuencia_cardiaca', ['valor' => 150]);

        $this->assertSame('critico', $ev->estado);
        $this->assertSame('contactar_tierra', $ev->accion);
        $alert = AlertaSaliente::where('evaluacion_id', $ev->id)->first();
        $this->assertSame('pendiente', $alert->estado);
        $this->assertSame('critico', $alert->carga['estado']);
    }

    public function test_impossible_value_is_rejected_and_not_saved(): void
    {
        $a = $this->crewMember();

        try {
            ProcesadorMediciones::make()->registrar($a, 'temperatura', ['valor' => 80]);
            $this->fail('Expected a ValidationException');
        } catch (ValidationException) {
            $this->assertSame(0, $a->mediciones()->count());
        }
    }

    public function test_duplicate_within_one_minute_is_rejected(): void
    {
        Event::fake([MedicionRegistrada::class]);
        $a = $this->crewMember();
        $p = ProcesadorMediciones::make();

        $p->registrar($a, 'glucosa', ['valor' => 95]);
        $this->expectException(ValidationException::class);
        $p->registrar($a, 'glucosa', ['valor' => 95]);
    }

    public function test_latest_returns_the_most_recent_state_per_indicator(): void
    {
        Event::fake([MedicionRegistrada::class]);
        $a = $this->crewMember();
        $p = ProcesadorMediciones::make();

        $p->registrar($a, 'glucosa', ['valor' => 95], instante: now()->subMinutes(10));
        $p->registrar($a, 'glucosa', ['valor' => 250], instante: now());

        $this->assertSame('critico', $p->ultimas($a)['glucosa']->estado);
    }
}
