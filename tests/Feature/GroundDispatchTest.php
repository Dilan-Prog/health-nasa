<?php

namespace Tests\Feature;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Services\Salud\EnvioATierra;
use App\Services\Salud\ProcesadorMediciones;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroundDispatchTest extends TestCase
{
    use DatabaseTransactions;

    private ?bool $originalLink = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLink = Cache::get('tierra.enlace');
        Event::fake();
        // Isolates the test: alerts already in the local DB must not be sent.
        AlertaSaliente::where('estado', 'pendiente')->update(['estado' => 'enviada']);
    }

    protected function tearDown(): void
    {
        $this->originalLink === null ? Cache::forget('tierra.enlace') : Cache::forever('tierra.enlace', $this->originalLink);
        parent::tearDown();
    }

    private function criticalAlert(): AlertaSaliente
    {
        $a = Astronauta::create(['nombre' => 'T', 'mision' => 'T', 'entorno' => 'base_marciana']);
        $ev = ProcesadorMediciones::make()->registrar($a, 'frecuencia_cardiaca', ['valor' => 150]);

        return AlertaSaliente::where('evaluacion_id', $ev->id)->firstOrFail();
    }

    public function test_without_link_alerts_wait(): void
    {
        Cache::forever('tierra.enlace', false);
        $alert = $this->criticalAlert();

        $this->assertSame(0, app(EnvioATierra::class)->enviarPendientes());
        $this->assertSame('pendiente', $alert->fresh()->estado);
    }

    public function test_with_link_in_simulated_mode_alerts_are_marked_sent(): void
    {
        Cache::forever('tierra.enlace', true);
        config(['tierra.url' => null]);
        $alert = $this->criticalAlert();

        $this->assertSame(1, app(EnvioATierra::class)->enviarPendientes());
        $this->assertSame('enviada', $alert->fresh()->estado);
        $this->assertNotNull($alert->fresh()->enviada_en);
    }

    public function test_with_url_it_posts_and_on_failure_the_alert_stays_pending(): void
    {
        Cache::forever('tierra.enlace', true);
        config(['tierra.url' => 'http://tierra.test/alertas']);
        $alert = $this->criticalAlert();

        // Http::fake stubs accumulate: a sequence models "fails first, then responds".
        Http::fake(['tierra.test/*' => Http::sequence()->push('error', 500)->push('ok', 200)]);
        $this->assertSame(0, app(EnvioATierra::class)->enviarPendientes());
        $this->assertSame('pendiente', $alert->fresh()->estado);

        $this->assertSame(1, app(EnvioATierra::class)->enviarPendientes());
        $this->assertSame('enviada', $alert->fresh()->estado);
        Http::assertSent(fn ($r) => $r['estado'] === 'critico');
    }

    public function test_toggle_link(): void
    {
        Cache::forever('tierra.enlace', false);
        $envio = app(EnvioATierra::class);

        $this->assertTrue($envio->alternarEnlace());
        $this->assertFalse($envio->alternarEnlace());
    }
}
