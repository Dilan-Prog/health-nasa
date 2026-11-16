<?php

namespace Tests\Feature;

use App\Models\Astronauta;
use App\Models\Mision;
use App\Services\Salud\OrientacionAlertas;
use App\Services\Salud\ProcesadorMediciones;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): never deletes data from the local database. */
class AlertGuidanceTest extends TestCase
{
    use DatabaseTransactions;

    private function crewMember(): Astronauta
    {
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        $base = $mission->tripulantes()->firstOrFail();
        $crew = $base->replicate();
        $crew->nombre = 'Guidance Test';
        $crew->save();

        return $crew;
    }

    private function measure(Astronauta $crew, string $key, array $values): void
    {
        ProcesadorMediciones::make()->registrar($crew, $key, $values, 'manual', null, false, false);
    }

    public function test_no_incidents_yields_no_cards(): void
    {
        $crew = $this->crewMember();
        $this->assertSame([], OrientacionAlertas::para($crew));
        $this->assertSame([], OrientacionAlertas::compartibles($crew));
    }

    public function test_cards_shape_and_order(): void
    {
        $crew = $this->crewMember();
        $this->measure($crew, 'presion_arterial', ['sistolica' => 128, 'diastolica' => 84]); // warn
        $this->measure($crew, 'perdida_osea', ['valor' => -1.6]); // crit

        $cards = OrientacionAlertas::para($crew);
        $this->assertSame(['perdida_osea', 'presion_arterial'], array_column($cards, 'id'));
        $this->assertEqualsCanonicalizing(['id', 'kind', 'rule', 'sev', 'finding', 'steps', 'pend', 'conf', 'safe', 'src', 'time', 'etiqueta', 'valor', 'icono', 'fuente', 'referencia', 'banda'], array_keys($cards[0]));
        $this->assertSame('ai', $cards[0]['kind']);
        $this->assertSame('crit', $cards[0]['sev']);
        $this->assertStringContainsString('−1.6 %/month', $cards[0]['finding']);
        $this->assertSame('escal', $cards[0]['safe']);
        $this->assertSame(78, $cards[0]['conf']);
        $this->assertStringContainsString('[pending medical review]', $cards[0]['pend']);
        $this->assertSame(['l', 't', 'ic'], array_keys($cards[0]['src'][0]));
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2} UTC$/', $cards[0]['time']);
        $this->assertSame(config('panel.indicadores.perdida_osea.etiqueta'), $cards[0]['etiqueta']);
        $this->assertSame('−1.6 %/month', $cards[0]['valor']);
        $this->assertSame('warn', $cards[1]['sev']);
        $this->assertNull($cards[1]['pend']);
    }

    public function test_spo2_85_emergency_comes_first_and_without_ai(): void
    {
        $crew = $this->crewMember();
        $this->measure($crew, 'perdida_osea', ['valor' => -1.6]);
        $this->measure($crew, 'saturacion_o2', ['valor' => 85]);

        $cards = OrientacionAlertas::para($crew);
        $this->assertSame('saturacion_o2', $cards[0]['id']);
        $this->assertSame('emerg', $cards[0]['kind']);
        $this->assertSame('rule', $cards[0]['safe']);
        $this->assertSame('crit', $cards[0]['sev']);
        $this->assertNull($cards[0]['conf']);
        $this->assertStringContainsString('85 %', $cards[0]['finding']);
        $this->assertCount(1, array_filter($cards, fn ($x) => $x['id'] === 'saturacion_o2'));

        $shareable = collect(OrientacionAlertas::compartibles($crew))->keyBy('id');
        $this->assertTrue($shareable['saturacion_o2']['locked']);
        $this->assertFalse($shareable['perdida_osea']['locked']);
    }

    public function test_indicator_without_library_gets_generic_rule_card(): void
    {
        $crew = $this->crewMember();
        $this->measure($crew, 'imc', ['valor' => 30]);

        $cards = OrientacionAlertas::para($crew);
        $this->assertCount(1, $cards);
        $this->assertSame('rule', $cards[0]['kind']);
        $this->assertSame('none', $cards[0]['safe']);
        $this->assertNotNull($cards[0]['rule']);
        $this->assertSame(config('panel.indicadores.imc.acciones'), $cards[0]['steps']);
        $this->assertStringContainsString(config('panel.indicadores.imc.etiqueta').' out of range', $cards[0]['finding']);
    }

    public function test_shareable_cards_shape_and_order(): void
    {
        $crew = $this->crewMember();
        $this->measure($crew, 'presion_arterial', ['sistolica' => 128, 'diastolica' => 84]);
        $this->measure($crew, 'perdida_osea', ['valor' => -1.6]);

        $this->assertSame([
            ['id' => 'perdida_osea', 'label' => config('panel.indicadores.perdida_osea.etiqueta'), 'val' => '−1.6 %/month', 'sev' => 'crit', 'locked' => false],
            ['id' => 'presion_arterial', 'label' => config('panel.indicadores.presion_arterial.etiqueta'), 'val' => '128/84 mmHg', 'sev' => 'warn', 'locked' => false],
        ], OrientacionAlertas::compartibles($crew));
    }
}
