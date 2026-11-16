<?php

namespace Tests\Feature;

use App\Livewire\MedicoVuelo;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Models\Requisito;
use App\Services\Salud\Estado;
use App\Services\Salud\EvaluadorVentanas;
use App\Services\Salud\ProcesadorMediciones;
use App\Services\Salud\ResolvedorRequisito;
use App\Support\TripulanteActual;
use Carbon\CarbonImmutable;
use Database\Seeders\ExpedicionSeeder;
use Database\Seeders\RequisitosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Window rules (config/ventanas.php). Project assumptions, not from the standard: 3 daily readings under 6 h.
 * DatabaseTransactions: only touches the test DB and rolls everything back.
 */
class WindowEvaluatorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->seed(RequisitosSeeder::class);
        ResolvedorRequisito::olvidar();
    }

    /** Sleep series: hours per night, the last is the most recent; null = day with no data. */
    private function series(array $hours): array
    {
        $end = CarbonImmutable::parse('2026-11-14 08:00:00');
        $n = count($hours);
        $out = [];
        foreach ($hours as $i => $h) {
            if ($h === null) {
                continue;
            }
            $out[] = ['valores' => ['valor' => $h], 'en' => $end->subDays($n - 1 - $i)];
        }

        return $out;
    }

    public static function cases(): array
    {
        return [
            'one bad night' => [[7.5, 7.2, 5.0], false],
            'two bad nights' => [[7.5, 5.5, 5.0], false],
            'three bad nights' => [[5.5, 5.8, 5.0], true],
            'more than three, all bad' => [[7.0, 5.5, 5.8, 5.0, 4.9], true],
            'interleaved' => [[5.5, 7.0, 5.8, 5.0], false],
            'interleaved at the end' => [[5.5, 5.8, 6.5], false],
            'exactly 6 h does not count' => [[6.0, 5.5, 5.0], false],
            'missing data: only 2 readings' => [[5.5, 5.0], false],
            'no data' => [[], false],
            'one-day gap with no data' => [[5.5, null, 5.8, 5.0], false],
        ];
    }

    /** @dataProvider cases */
    public function test_sleep_case_table(array $hours, bool $expected): void
    {
        $ev = new EvaluadorVentanas(config('ventanas'));

        $this->assertSame($expected, $ev->cumple(config('ventanas.reglas.sueno_corto'), $this->series($hours)));
    }

    public function test_number_of_readings_is_configurable(): void
    {
        $regla = ['lecturas' => 2] + config('ventanas.reglas.sueno_corto');

        $this->assertTrue((new EvaluadorVentanas())->cumple($regla, $this->series([7.5, 5.5, 5.0])));
    }

    public function test_calories_and_pvt_are_disabled_and_coming_soon(): void
    {
        foreach (['calorias_bajas', 'pvt_lapsos'] as $id) {
            $r = config("ventanas.reglas.$id");
            $this->assertFalse($r['activa']);
            $this->assertTrue($r['proximamente']);
            $this->assertSame('guia', $r['requisito']['tipo']);
        }
        $ev = new EvaluadorVentanas();
        $this->assertFalse($ev->aplicaA('calorias'));
        $this->assertFalse($ev->aplicaA('pvt'));
        $this->assertTrue($ev->aplicaA('sueno'));
        $this->assertFalse($ev->aplicaA('glucosa'));
    }

    public function test_rule_cites_the_guide_row_not_a_standard_row(): void
    {
        $r = (new EvaluadorVentanas())->requisito(['requisito_def' => config('ventanas.reglas.sueno_corto.requisito')]);

        $this->assertInstanceOf(Requisito::class, $r);
        $this->assertSame('guia', $r->tipo);
        $this->assertSame(ResolvedorRequisito::para('sueno')->id, $r->id);
    }

    private function crewMember(?Mision $mission = null): Astronauta
    {
        return Astronauta::create(['nombre' => 'Ventanas', 'mision' => 'T-V', 'entorno' => 'estacion', 'mision_id' => $mission?->id]);
    }

    private function night(Astronauta $a, float $h, int $daysAgo)
    {
        return ProcesadorMediciones::make()->registrar(
            $a, 'sueno', ['valor' => $h], 'manual', CarbonImmutable::now()->subDays($daysAgo)->subMinutes($daysAgo), false, false
        );
    }

    public function test_in_the_processor_the_third_bad_night_cites_the_guide_and_is_preventive(): void
    {
        $a = $this->crewMember();
        $e1 = $this->night($a, 5.8, 2);
        $e2 = $this->night($a, 5.8, 1);
        $e3 = $this->night($a, 5.8, 0);

        foreach ([$e1, $e2] as $e) {
            $this->assertStringNotContainsString('preventive', mb_strtolower($e->recomendacion));
        }
        $this->assertNotSame(Estado::Normal->value, $e3->estado);
        $this->assertStringContainsString('Preventive action', $e3->recomendacion);
        $this->assertStringNotContainsStringIgnoringCase('hypn', $e3->recomendacion);
        $this->assertSame('guia', Requisito::find($e3->requisito_id)->tipo);
        $this->assertStringContainsString('48 h', Requisito::find($e3->requisito_id)->texto);
    }

    public function test_interleaved_good_night_does_not_trigger_the_rule(): void
    {
        $a = $this->crewMember();
        $this->night($a, 5.8, 2);
        $this->night($a, 7.8, 1);
        $e = $this->night($a, 5.8, 0);

        $this->assertStringNotContainsString('Preventive action', $e->recomendacion);
    }

    public function test_rule_raises_a_normal_state_to_caution_at_minimum(): void
    {
        // With the current thresholds each night < 6 h is already caution on its own; the rule threshold is raised
        // to check that the window, by itself, raises a normal state and never lowers a serious one.
        config(['ventanas.reglas.sueno_corto.umbral' => 8.0]);
        $a = $this->crewMember();
        $this->night($a, 7.5, 2);
        $this->night($a, 7.5, 1);
        $e = $this->night($a, 7.5, 0); // 7.5 h is in the normal range

        $this->assertSame(Estado::Precaucion->value, $e->estado);
        $this->assertSame('recomendar', $e->accion);
    }

    public function test_an_indicator_without_a_rule_does_not_run_the_window_query(): void
    {
        $a = $this->crewMember();
        DB::enableQueryLog();
        ProcesadorMediciones::make()->registrar($a, 'glucosa', ['valor' => 90], 'manual', null, false, false);
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'limit 2'));
        DB::disableQueryLog();

        $this->assertCount(0, $queries);
    }

    private function crewWithMission(string $nombre): Astronauta
    {
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mission->id]);
        $a = $this->crewMember($mission);
        $a->update(['nombre' => $nombre]);
        TripulanteActual::fijar($a->id);

        return $a;
    }

    public function test_flight_surgeon_card_shows_the_rule_and_its_requirement(): void
    {
        $a = $this->crewWithMission('Window Medic');
        foreach ([2, 1, 0] as $d) {
            $this->night($a, 5.8, $d);
        }

        Livewire::test(MedicoVuelo::class)
            ->assertSee('Window rule')
            ->assertSee('Preventive')
            ->assertSee(str_replace('{n}', (string) config('ventanas.reglas.sueno_corto.lecturas'), config('ventanas.reglas.sueno_corto.texto')))
            ->assertSee('Sleep under 6 h for more than 48 h (preventive severity)')
            ->assertSee('not from the standard');
    }

    public function test_with_a_single_bad_night_the_card_shows_no_window_rule(): void
    {
        $a = $this->crewWithMission('Window Medic 2');
        $this->night($a, 5.8, 0);

        Livewire::test(MedicoVuelo::class)->assertDontSee('Window rule');
    }
}
