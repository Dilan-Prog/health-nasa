<?php

namespace Tests\Feature;

use App\Livewire\FichaTripulante;
use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\Mision;
use App\Services\Tripulacion\AltaTripulante;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): never wipes the local DB data. The mission and crew are created here. */
class CrewProfileTest extends TestCase
{
    use DatabaseTransactions;

    private function mission(): Mision
    {
        // id higher than any development mission: it is the "current" one for TripulanteActual.
        return Mision::create(['nombre' => 'Test Profile '.uniqid(), 'entorno' => 'nave', 'inicio_en' => now()->toDateString(), 'duracion_dias' => 100]);
    }

    private function enroll(Mision $m, array $extra = []): Astronauta
    {
        return app(AltaTripulante::class)->registrar(array_merge([
            'nombre' => 'Sofía Rivas Ortega',
            'fecha_nacimiento' => now()->subYears(35)->subDays(10)->toDateString(),
            'sexo' => 'femenino', 'rol' => 'Flight Surgeon', 'agencia' => 'AEM', 'nacionalidad' => 'Mexicana',
            'altura_m' => 1.66, 'peso_kg' => 60.4, 'grupo_sanguineo' => 'O-',
            'aptitud_vuelo' => 'apto_restricciones', 'restricciones' => 'No EVA until the ophthalmology review.',
            'linea_base' => ['frecuencia_cardiaca' => 62, 'glucosa' => 88],
            'alergias' => [['sustancia' => 'Penicillin', 'reaccion' => 'Anaphylaxis', 'gravedad' => 'grave']],
            'medicamentos' => [['nombre' => 'Melatonin', 'dosis_texto' => '3 mg', 'activo' => true]],
            'contactos' => [['nombre' => 'Andrés Rivas', 'parentesco' => 'hermano', 'telefono' => '+52 55 1234 5678', 'principal' => true]],
        ], $extra), $m);
    }

    private function setCurrent(Mision $m): void
    {
        session(['mision_id' => $m->id]);
    }

    public function test_shows_header_lists_and_coming_soon_blocks(): void
    {
        $m = $this->mission();
        $a = $this->enroll($m);
        $this->setCurrent($m);

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->assertSee('Sofía Rivas Ortega')
            ->assertSee('Flight Surgeon')
            ->assertSee('Fit with restrictions')
            ->assertSee('Severe allergy: Penicillin')
            ->assertSee('Blood type O−', false)
            ->assertSee('Flight restriction:')
            ->assertSee('No EVA until the ophthalmology review.')
            ->assertSee('Melatonin 3 mg')
            ->assertSee('The app does not prescribe or change doses.')
            ->assertSee('Andrés Rivas')
            ->assertSee('Primary')
            ->assertSee('Edit record')
            ->assertSee('COMING SOON')
            ->assertSee('watch when this feature is available')
            ->assertSee('Personal baseline');
    }

    public function test_table_lists_the_14_markers_and_uses_only_real_data(): void
    {
        $m = $this->mission();
        $a = $this->enroll($m);
        $this->setCurrent($m);
        Medicion::create(['astronauta_id' => $a->id, 'indicador' => 'frecuencia_cardiaca', 'valores' => ['valor' => 70], 'medido_en' => now('UTC'), 'origen' => 'manual']);

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->assertViewHas('linea', function ($filas) {
                $porClave = collect($filas)->keyBy('clave');
                $fc = $porClave['frecuencia_cardiaca'];
                $glu = $porClave['glucosa'];

                return count($filas) === 14
                    && $fc['base'] === '62' && $fc['ultimo'] === '70' && $fc['delta'] === '+8' && $fc['estado'] !== 'neu'
                    // With a baseline but no measurement: no value or state is invented.
                    && $glu['base'] === '88' && $glu['ultimo'] === null && $glu['delta'] === null && $glu['estado'] === 'neu'
                    && $porClave['imc']['base'] === null;
            })
            ->assertSee('Not measured');
    }

    public function test_completeness_matches_the_service(): void
    {
        $m = $this->mission();
        $a = $this->enroll($m);
        $this->setCurrent($m);
        $pct = app(AltaTripulante::class)->completitud($a)['porcentaje'];

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->assertViewHas('comp', fn ($c) => $c['porcentaje'] === $pct)
            ->assertSee($pct.' %');
    }

    public function test_empty_lists_show_a_message(): void
    {
        $m = $this->mission();
        $a = $this->enroll($m, ['alergias' => [], 'medicamentos' => [], 'contactos' => [], 'aptitud_vuelo' => 'apto', 'restricciones' => null, 'linea_base' => []]);
        $this->setCurrent($m);

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->assertSee('No allergies recorded.')
            ->assertSee('No medications recorded.')
            ->assertSee('No contacts recorded.')
            ->assertDontSee('Flight restriction:');
    }

    public function test_a_crew_member_from_another_mission_returns_404(): void
    {
        $other = $this->mission();
        $foreign = $this->enroll($other, ['nombre' => 'Foreign Other Mission']);
        $current = $this->mission();
        $this->enroll($current, ['nombre' => 'Own']);
        $this->setCurrent($current);

        Livewire::test(FichaTripulante::class, ['astronauta' => $foreign])->assertStatus(404);
    }

    public function test_route_responds_and_returns_404_for_a_foreign_member(): void
    {
        $other = $this->mission();
        $foreign = $this->enroll($other, ['nombre' => 'Foreign Other Mission']);
        $current = $this->mission();
        $own = $this->enroll($current, ['nombre' => 'Own Crew Member']);
        $this->setCurrent($current);

        $this->get(route('tripulacion.ficha', $own))->assertOk()->assertSee('Own Crew Member');
        $this->get(route('tripulacion.ficha', $foreign))->assertNotFound();
    }
}
