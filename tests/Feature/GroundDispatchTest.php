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

class EnvioATierraTest extends TestCase
{
    use DatabaseTransactions;

    private ?bool $enlaceOriginal = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enlaceOriginal = Cache::get('tierra.enlace');
        Event::fake();
        // Aísla el test: las alertas ya existentes en la BD local no deben enviarse.
        AlertaSaliente::where('estado', 'pendiente')->update(['estado' => 'enviada']);
    }

    protected function tearDown(): void
    {
        $this->enlaceOriginal === null ? Cache::forget('tierra.enlace') : Cache::forever('tierra.enlace', $this->enlaceOriginal);
        parent::tearDown();
    }

    private function alertaCritica(): AlertaSaliente
    {
        $a = Astronauta::create(['nombre' => 'T', 'mision' => 'T', 'entorno' => 'base_marciana']);
        $ev = ProcesadorMediciones::make()->registrar($a, 'frecuencia_cardiaca', ['valor' => 150]);

        return AlertaSaliente::where('evaluacion_id', $ev->id)->firstOrFail();
    }

    public function test_sin_enlace_las_alertas_esperan(): void
    {
        Cache::forever('tierra.enlace', false);
        $alerta = $this->alertaCritica();

        $this->assertSame(0, app(EnvioATierra::class)->enviarPendientes());
        $this->assertSame('pendiente', $alerta->fresh()->estado);
    }

    public function test_con_enlace_en_modo_simulado_se_marcan_enviadas(): void
    {
        Cache::forever('tierra.enlace', true);
        config(['tierra.url' => null]);
        $alerta = $this->alertaCritica();

        $this->assertSame(1, app(EnvioATierra::class)->enviarPendientes());
        $this->assertSame('enviada', $alerta->fresh()->estado);
        $this->assertNotNull($alerta->fresh()->enviada_en);
    }

    public function test_con_url_hace_post_y_si_falla_la_alerta_sigue_pendiente(): void
    {
        Cache::forever('tierra.enlace', true);
        config(['tierra.url' => 'http://tierra.test/alertas']);
        $alerta = $this->alertaCritica();

        // Los stubs de Http::fake se acumulan: una secuencia modela "primero falla, luego responde".
        Http::fake(['tierra.test/*' => Http::sequence()->push('error', 500)->push('ok', 200)]);
        $this->assertSame(0, app(EnvioATierra::class)->enviarPendientes());
        $this->assertSame('pendiente', $alerta->fresh()->estado);

        $this->assertSame(1, app(EnvioATierra::class)->enviarPendientes());
        $this->assertSame('enviada', $alerta->fresh()->estado);
        Http::assertSent(fn ($r) => $r['estado'] === 'critico');
    }

    public function test_alternar_enlace(): void
    {
        Cache::forever('tierra.enlace', false);
        $envio = app(EnvioATierra::class);

        $this->assertTrue($envio->alternarEnlace());
        $this->assertFalse($envio->alternarEnlace());
    }
}
