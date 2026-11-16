<?php

namespace Tests\Feature;

use App\Livewire\FichaTripulante;
use App\Models\ExamenAscan;
use App\Models\Mision;
use App\Services\Tripulacion\AltaTripulante;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** ASCAN record: recording only (result, date, status). DatabaseTransactions. */
class AscanTest extends TestCase
{
    use DatabaseTransactions;

    private function crewMember()
    {
        $m = Mision::create(['nombre' => 'Test ASCAN '.uniqid(), 'entorno' => 'nave', 'inicio_en' => now()->toDateString(), 'duracion_dias' => 100]);
        $a = app(AltaTripulante::class)->registrar([
            'nombre' => 'ASCAN Crew', 'fecha_nacimiento' => now()->subYears(35)->toDateString(), 'sexo' => 'masculino',
            'rol' => 'Pilot', 'agencia' => 'AEM', 'nacionalidad' => 'Mexican', 'altura_m' => 1.8, 'peso_kg' => 80, 'grupo_sanguineo' => 'A+',
            'aptitud_vuelo' => 'apto',
        ], $m);
        session(['mision_id' => $m->id]);

        return $a;
    }

    public function test_record_lists_the_tests_and_states_it_does_not_interpret(): void
    {
        $a = $this->crewMember();
        $tests = collect(config('ascan.grupos'))->flatMap(fn ($g) => $g['pruebas']);
        $this->assertGreaterThanOrEqual(30, $tests->count());
        $this->assertTrue($tests->contains('Factor V Leiden'));

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->assertSee('Recording only, no interpretation')
            ->assertSee('Factor V Leiden')
            ->assertSee('Lipoprotein(a)')
            ->assertSee('H. pylori breath test');
    }

    public function test_captures_result_date_and_status_and_skips_empty_pending(): void
    {
        $a = $this->crewMember();

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->set('asc.g6pd.resultado', 'No deficiency')
            ->set('asc.g6pd.fecha', '2026-10-05')
            ->set('asc.g6pd.estado', 'normal')
            ->set('asc.lipoproteina_a.estado', 'anormal')
            ->call('guardarAscan')
            ->assertHasNoErrors()
            ->assertSee('ASCAN record saved.');

        $g = ExamenAscan::where('astronauta_id', $a->id)->where('prueba', 'g6pd')->firstOrFail();
        $this->assertSame('No deficiency', $g->resultado);
        $this->assertSame('2026-10-05', $g->fecha->toDateString());
        $this->assertSame('normal', $g->estado);
        $this->assertSame('anormal', ExamenAscan::where('astronauta_id', $a->id)->where('prueba', 'lipoproteina_a')->value('estado'));
        $this->assertSame(2, ExamenAscan::where('astronauta_id', $a->id)->count());

        // Reverting to empty/pending deletes the record.
        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->assertSet('asc.g6pd.estado', 'normal')
            ->set('asc.g6pd.resultado', '')->set('asc.g6pd.fecha', '')->set('asc.g6pd.estado', 'pendiente')
            ->call('guardarAscan');
        $this->assertSame(1, ExamenAscan::where('astronauta_id', $a->id)->count());
    }

    public function test_validates_status_date_and_length(): void
    {
        $a = $this->crewMember();

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->set('asc.g6pd.estado', 'dudoso')
            ->set('asc.ig_g.fecha', 'no-es-fecha')
            ->set('asc.ig_a.resultado', str_repeat('x', 256))
            ->call('guardarAscan')
            ->assertHasErrors(['asc.g6pd.estado' => 'in', 'asc.ig_g.fecha' => 'date', 'asc.ig_a.resultado' => 'max']);

        $this->assertSame(0, ExamenAscan::where('astronauta_id', $a->id)->count());
    }
}
