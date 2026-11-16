<?php

namespace Tests\Feature;

use App\Livewire\Alertas;
use App\Models\AlertaEvento;
use App\Models\AlertaGestion;
use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never deletes the local database data. */
class AlertsTest extends TestCase
{
    use DatabaseTransactions;

    private const CLAVE = 'saturacion_o2';

    private Astronauta $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        // New crew member with no measurements: the only alert is the one created here (deterministic).
        $this->a = Astronauta::create(['nombre' => 'Alerts Test', 'mision' => 'T-1', 'entorno' => 'estacion', 'mision_id' => $mision->id]);
        TripulanteActual::fijar($this->a->id);
    }

    private function criticalAlert(): void
    {
        Medicion::create(['astronauta_id' => $this->a->id, 'indicador' => self::CLAVE,
            'valores' => ['valor' => 80], 'medido_en' => now()->subMinutes(5), 'origen' => 'manual']);
    }

    private function management(): ?AlertaGestion
    {
        return AlertaGestion::where('astronauta_id', $this->a->id)->where('indicador', self::CLAVE)->first();
    }

    private function eventCount(): int
    {
        return AlertaEvento::where('astronauta_id', $this->a->id)->count();
    }

    public function test_page_renders(): void
    {
        $this->withSession(['mision_id' => $this->a->mision_id, 'astronauta_id' => $this->a->id])
            ->get('/alertas')->assertOk()->assertSee('Alerts inbox');
    }

    public function test_view_switches_with_v_parameter(): void
    {
        Livewire::withQueryParams(['v' => 'b'])->test(Alertas::class)
            ->assertSet('vista', 'b')->assertSee('Nothing here');
        Livewire::withQueryParams(['v' => 'c'])->test(Alertas::class)
            ->assertSet('vista', 'c')->assertSee('Timeline');
    }

    public function test_critical_measurement_appears_as_new_alert(): void
    {
        $this->criticalAlert();
        Livewire::test(Alertas::class)->assertSee(config('panel.indicadores.'.self::CLAVE.'.etiqueta'))
            ->assertViewHas('lista', fn ($l) => count($l) === 1 && $l[0]['clave'] === self::CLAVE
                && $l[0]['s'] === 'crit' && $l[0]['est'] === 'nueva' && $l[0]['lvl'] === 0);
    }

    public function test_acknowledge_saves_management_and_event(): void
    {
        $this->criticalAlert();
        Livewire::test(Alertas::class)->call('reconocer', self::CLAVE);

        $g = $this->management();
        $this->assertSame('vista', $g->estado);
        $this->assertSame(0, $g->nivel);
        $this->assertSame('crit', $g->severidad);
        $this->assertSame(1, $this->eventCount());
    }

    public function test_acknowledging_twice_does_not_duplicate_event(): void
    {
        $this->criticalAlert();
        Livewire::test(Alertas::class)->call('reconocer', self::CLAVE)->call('reconocer', self::CLAVE);

        $this->assertSame(1, $this->eventCount());
    }

    public function test_escalate_raises_level_and_queues_ground_alert_at_flight_surgeon(): void
    {
        $this->criticalAlert();
        $c = Livewire::test(Alertas::class)->call('escalar', self::CLAVE);

        $this->assertSame('escalada', $this->management()->estado);
        $this->assertSame(1, $this->management()->nivel);
        $this->assertSame(0, AlertaSaliente::where('astronauta_id', $this->a->id)->count());

        $c->call('escalar', self::CLAVE);

        $this->assertSame(2, $this->management()->nivel);
        $this->assertSame(2, $this->eventCount());
        $this->assertTrue(AlertaEvento::where('astronauta_id', $this->a->id)->where('mensaje', 'like', '%Flight Surgeon%')->exists());
        $s = AlertaSaliente::where('astronauta_id', $this->a->id)->get();
        $this->assertCount(1, $s);
        $this->assertSame('pendiente', $s[0]->estado);
        $this->assertSame(self::CLAVE, $s[0]->carga['indicador']);
        $this->assertSame('escalamiento_manual', $s[0]->carga['origen']);
    }

    public function test_escalating_at_max_level_does_nothing(): void
    {
        $this->criticalAlert();
        $c = Livewire::test(Alertas::class)->call('escalar', self::CLAVE)->call('escalar', self::CLAVE);
        $events = $this->eventCount();

        $c->call('escalar', self::CLAVE);

        $this->assertSame(2, $this->management()->nivel);
        $this->assertSame($events, $this->eventCount());
        $this->assertSame(1, AlertaSaliente::where('astronauta_id', $this->a->id)->count());
    }

    public function test_resolve_and_reopen(): void
    {
        $this->criticalAlert();
        $c = Livewire::test(Alertas::class)->call('resolver', self::CLAVE);

        $this->assertSame('resuelta', $this->management()->estado);
        $this->assertSame(1, $this->eventCount());
        $c->assertViewHas('lista', fn ($l) => count($l) === 0);

        $c->call('reabrir', self::CLAVE);

        $this->assertSame('vista', $this->management()->estado);
        $this->assertSame(2, $this->eventCount());
        $this->assertDatabaseHas('alertas_eventos', ['astronauta_id' => $this->a->id, 'mensaje' => config('panel.indicadores.'.self::CLAVE.'.etiqueta').': reopened']);
    }

    public function test_escalating_resolved_alert_does_nothing(): void
    {
        $this->criticalAlert();
        Livewire::test(Alertas::class)->call('resolver', self::CLAVE)->call('escalar', self::CLAVE);

        $this->assertSame('resuelta', $this->management()->estado);
        $this->assertSame(0, $this->management()->nivel);
        $this->assertSame(1, $this->eventCount());
        $this->assertSame(0, AlertaSaliente::where('astronauta_id', $this->a->id)->count());
    }

    public function test_unknown_key_is_ignored(): void
    {
        $this->criticalAlert();
        Livewire::test(Alertas::class)->call('escalar', 'no_existe')->call('resolver', 'no_existe');

        $this->assertNull($this->management());
        $this->assertSame(0, $this->eventCount());
    }
}
