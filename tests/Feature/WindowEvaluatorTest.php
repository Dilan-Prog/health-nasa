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
 * Reglas de ventana (config/ventanas.php). Supuestos del proyecto, no de la norma: 3 lecturas diarias bajo 6 h.
 * DatabaseTransactions: solo toca la BD de pruebas y deshace todo.
 */
class EvaluadorVentanasTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->seed(RequisitosSeeder::class);
        ResolvedorRequisito::olvidar();
    }

    /** Serie de sueño: horas por noche, la última es la más reciente; null = día sin dato. */
    private function serie(array $horas): array
    {
        $fin = CarbonImmutable::parse('2026-11-14 08:00:00');
        $n = count($horas);
        $out = [];
        foreach ($horas as $i => $h) {
            if ($h === null) {
                continue;
            }
            $out[] = ['valores' => ['valor' => $h], 'en' => $fin->subDays($n - 1 - $i)];
        }

        return $out;
    }

    public static function casos(): array
    {
        return [
            'una noche mala' => [[7.5, 7.2, 5.0], false],
            'dos noches malas' => [[7.5, 5.5, 5.0], false],
            'tres noches malas' => [[5.5, 5.8, 5.0], true],
            'más de tres, todas malas' => [[7.0, 5.5, 5.8, 5.0, 4.9], true],
            'intercalada' => [[5.5, 7.0, 5.8, 5.0], false],
            'intercalada al final' => [[5.5, 5.8, 6.5], false],
            'exactamente 6 h no cuenta' => [[6.0, 5.5, 5.0], false],
            'datos faltantes: solo 2 lecturas' => [[5.5, 5.0], false],
            'sin datos' => [[], false],
            'hueco de un día sin dato' => [[5.5, null, 5.8, 5.0], false],
        ];
    }

    /** @dataProvider casos */
    public function test_tabla_de_casos_de_sueno(array $horas, bool $espera): void
    {
        $ev = new EvaluadorVentanas(config('ventanas'));

        $this->assertSame($espera, $ev->cumple(config('ventanas.reglas.sueno_corto'), $this->serie($horas)));
    }

    public function test_el_numero_de_lecturas_es_configurable(): void
    {
        $regla = ['lecturas' => 2] + config('ventanas.reglas.sueno_corto');

        $this->assertTrue((new EvaluadorVentanas())->cumple($regla, $this->serie([7.5, 5.5, 5.0])));
    }

    public function test_calorias_y_pvt_estan_desactivadas_y_proximamente(): void
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

    public function test_la_regla_cita_la_fila_de_la_guia_y_no_una_de_la_norma(): void
    {
        $r = (new EvaluadorVentanas())->requisito(['requisito_def' => config('ventanas.reglas.sueno_corto.requisito')]);

        $this->assertInstanceOf(Requisito::class, $r);
        $this->assertSame('guia', $r->tipo);
        $this->assertSame(ResolvedorRequisito::para('sueno')->id, $r->id);
    }

    private function astronauta(?Mision $mision = null): Astronauta
    {
        return Astronauta::create(['nombre' => 'Ventanas', 'mision' => 'T-V', 'entorno' => 'estacion', 'mision_id' => $mision?->id]);
    }

    private function noche(Astronauta $a, float $h, int $diasAtras)
    {
        return ProcesadorMediciones::make()->registrar(
            $a, 'sueno', ['valor' => $h], 'manual', CarbonImmutable::now()->subDays($diasAtras)->subMinutes($diasAtras), false, false
        );
    }

    public function test_en_el_procesador_la_tercera_noche_mala_cita_la_guia_y_es_preventiva(): void
    {
        $a = $this->astronauta();
        $e1 = $this->noche($a, 5.8, 2);
        $e2 = $this->noche($a, 5.8, 1);
        $e3 = $this->noche($a, 5.8, 0);

        foreach ([$e1, $e2] as $e) {
            $this->assertStringNotContainsString('preventiva', mb_strtolower($e->recomendacion));
        }
        $this->assertNotSame(Estado::Normal->value, $e3->estado);
        $this->assertStringContainsString('Acción preventiva', $e3->recomendacion);
        $this->assertStringNotContainsStringIgnoringCase('hipn', $e3->recomendacion);
        $this->assertSame('guia', Requisito::find($e3->requisito_id)->tipo);
        $this->assertStringContainsString('48 h', Requisito::find($e3->requisito_id)->texto);
    }

    public function test_noche_buena_intercalada_no_activa_la_regla(): void
    {
        $a = $this->astronauta();
        $this->noche($a, 5.8, 2);
        $this->noche($a, 7.8, 1);
        $e = $this->noche($a, 5.8, 0);

        $this->assertStringNotContainsString('Acción preventiva', $e->recomendacion);
    }

    public function test_la_regla_eleva_un_estado_normal_a_precaucion_como_minimo(): void
    {
        // Con los umbrales actuales cada noche < 6 h ya es precaución por sí sola; se sube el umbral de la regla
        // para comprobar que la ventana, por sí misma, eleva un estado normal y nunca baja uno grave.
        config(['ventanas.reglas.sueno_corto.umbral' => 8.0]);
        $a = $this->astronauta();
        $this->noche($a, 7.5, 2);
        $this->noche($a, 7.5, 1);
        $e = $this->noche($a, 7.5, 0); // 7.5 h está en rango normal

        $this->assertSame(Estado::Precaucion->value, $e->estado);
        $this->assertSame('recomendar', $e->accion);
    }

    public function test_un_indicador_sin_regla_no_hace_la_consulta_de_ventana(): void
    {
        $a = $this->astronauta();
        DB::enableQueryLog();
        ProcesadorMediciones::make()->registrar($a, 'glucosa', ['valor' => 90], 'manual', null, false, false);
        $consultas = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'limit 2'));
        DB::disableQueryLog();

        $this->assertCount(0, $consultas);
    }

    private function tripulanteConMision(string $nombre): Astronauta
    {
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        $a = $this->astronauta($mision);
        $a->update(['nombre' => $nombre]);
        TripulanteActual::fijar($a->id);

        return $a;
    }

    public function test_la_tarjeta_del_medico_de_vuelo_muestra_la_regla_y_su_requisito(): void
    {
        $a = $this->tripulanteConMision('Medico Ventana');
        foreach ([2, 1, 0] as $d) {
            $this->noche($a, 5.8, $d);
        }

        Livewire::test(MedicoVuelo::class)
            ->assertSee('Regla de ventana')
            ->assertSee('Preventiva')
            ->assertSee('Sueño menor a 6 h durante 3 lecturas diarias seguidas')
            ->assertSee('Sueño menor a 6 h por más de 48 h (severidad preventiva)')
            ->assertSee('no de la norma');
    }

    public function test_con_una_sola_noche_mala_la_tarjeta_no_muestra_regla_de_ventana(): void
    {
        $a = $this->tripulanteConMision('Medico Ventana 2');
        $this->noche($a, 5.8, 0);

        Livewire::test(MedicoVuelo::class)->assertDontSee('Regla de ventana');
    }
}
