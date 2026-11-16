<?php

namespace Tests\Feature;

use App\Livewire\FichaTripulante;
use App\Models\ExamenAscan;
use App\Models\Mision;
use App\Services\Tripulacion\AltaTripulante;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Ficha ASCAN: solo registro (resultado, fecha, estado). DatabaseTransactions. */
class AscanTest extends TestCase
{
    use DatabaseTransactions;

    private function astronauta()
    {
        $m = Mision::create(['nombre' => 'Test ASCAN '.uniqid(), 'entorno' => 'nave', 'inicio_en' => now()->toDateString(), 'duracion_dias' => 100]);
        $a = app(AltaTripulante::class)->registrar([
            'nombre' => 'Tripulante ASCAN', 'fecha_nacimiento' => now()->subYears(35)->toDateString(), 'sexo' => 'masculino',
            'rol' => 'Piloto', 'agencia' => 'AEM', 'nacionalidad' => 'Mexicana', 'altura_m' => 1.8, 'peso_kg' => 80, 'grupo_sanguineo' => 'A+',
            'aptitud_vuelo' => 'apto',
        ], $m);
        session(['mision_id' => $m->id]);

        return $a;
    }

    public function test_la_ficha_lista_las_pruebas_y_aclara_que_no_interpreta(): void
    {
        $a = $this->astronauta();
        $pruebas = collect(config('ascan.grupos'))->flatMap(fn ($g) => $g['pruebas']);
        $this->assertGreaterThanOrEqual(30, $pruebas->count());
        $this->assertTrue($pruebas->contains('Factor V Leiden'));

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->assertSee('Registro, no interpretación')
            ->assertSee('Factor V Leiden')
            ->assertSee('Lipoproteína (a)')
            ->assertSee('Test de aliento de H. pylori');
    }

    public function test_captura_resultado_fecha_y_estado_y_omite_las_pendientes_vacias(): void
    {
        $a = $this->astronauta();

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->set('asc.g6pd.resultado', 'Sin deficiencia')
            ->set('asc.g6pd.fecha', '2026-10-05')
            ->set('asc.g6pd.estado', 'normal')
            ->set('asc.lipoproteina_a.estado', 'anormal')
            ->call('guardarAscan')
            ->assertHasNoErrors()
            ->assertSee('Ficha ASCAN guardada.');

        $g = ExamenAscan::where('astronauta_id', $a->id)->where('prueba', 'g6pd')->firstOrFail();
        $this->assertSame('Sin deficiencia', $g->resultado);
        $this->assertSame('2026-10-05', $g->fecha->toDateString());
        $this->assertSame('normal', $g->estado);
        $this->assertSame('anormal', ExamenAscan::where('astronauta_id', $a->id)->where('prueba', 'lipoproteina_a')->value('estado'));
        $this->assertSame(2, ExamenAscan::where('astronauta_id', $a->id)->count());

        // Volver a vacío/pendiente elimina el registro.
        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->assertSet('asc.g6pd.estado', 'normal')
            ->set('asc.g6pd.resultado', '')->set('asc.g6pd.fecha', '')->set('asc.g6pd.estado', 'pendiente')
            ->call('guardarAscan');
        $this->assertSame(1, ExamenAscan::where('astronauta_id', $a->id)->count());
    }

    public function test_valida_estado_fecha_y_longitud(): void
    {
        $a = $this->astronauta();

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->set('asc.g6pd.estado', 'dudoso')
            ->set('asc.ig_g.fecha', 'no-es-fecha')
            ->set('asc.ig_a.resultado', str_repeat('x', 256))
            ->call('guardarAscan')
            ->assertHasErrors(['asc.g6pd.estado' => 'in', 'asc.ig_g.fecha' => 'date', 'asc.ig_a.resultado' => 'max']);

        $this->assertSame(0, ExamenAscan::where('astronauta_id', $a->id)->count());
    }
}
