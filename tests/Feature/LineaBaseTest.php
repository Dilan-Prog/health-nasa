<?php

namespace Tests\Feature;

use App\Livewire\FichaTripulante;
use App\Models\ExpedienteMedico;
use App\Models\LineaBase;
use App\Models\Medicion;
use App\Models\Mision;
use App\Services\Salud\DatosIndicador;
use App\Services\Salud\DesviacionBase;
use App\Services\Tripulacion\AltaTripulante;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Línea base pre-vuelo. DatabaseTransactions: nunca borra datos de la BD. Los números son de ejemplo. */
class LineaBaseTest extends TestCase
{
    use DatabaseTransactions;

    private function crew(): array
    {
        $m = Mision::create(['nombre' => 'Test LB '.uniqid(), 'entorno' => 'nave', 'inicio_en' => '2027-03-01', 'duracion_dias' => 100]);
        $a = app(AltaTripulante::class)->registrar([
            'nombre' => 'Tripulante LB', 'fecha_nacimiento' => now()->subYears(35)->toDateString(), 'sexo' => 'femenino',
            'rol' => 'Piloto', 'agencia' => 'AEM', 'nacionalidad' => 'Mexicana', 'altura_m' => 1.7, 'peso_kg' => 65, 'grupo_sanguineo' => 'O+',
            'aptitud_vuelo' => 'apto',
        ], $m);
        session(['mision_id' => $m->id]);

        return [$m, $a];
    }

    public static function casos(): array
    {
        return [
            'sube 10 %' => [100.0, 110.0, 10.0],
            'baja 20 %' => [50.0, 40.0, -20.0],
            'sin cambio' => [37.0, 37.0, 0.0],
            'base cero' => [0.0, 5.0, null],
            'sin base' => [null, 5.0, null],
        ];
    }

    /** @dataProvider casos */
    public function test_calculo_de_la_desviacion(?float $base, float $actual, ?float $esperado): void
    {
        $r = DesviacionBase::calcular($base, $actual);
        $esperado === null ? $this->assertNull($r) : $this->assertEqualsWithDelta($esperado, $r, 1e-9);
    }

    public function test_porcentaje_usa_linea_base_y_sin_base_es_null(): void
    {
        [, $a] = $this->crew();
        $this->assertNull(DesviacionBase::valorBase($a, 'fuerza'));
        $this->assertNull(DesviacionBase::porcentaje($a, 'fuerza', 10));

        LineaBase::create(['astronauta_id' => $a->id, 'metrica' => 'fuerza', 'valor' => 200, 'unidad' => 'N']);
        $this->assertSame(200.0, DesviacionBase::valorBase($a, 'fuerza'));
        $this->assertEqualsWithDelta(-10.0, DesviacionBase::porcentaje($a, 'fuerza', 180), 1e-9);
    }

    public function test_base_del_expediente_y_prioridad_de_linea_base(): void
    {
        [, $a] = $this->crew();
        ExpedienteMedico::updateOrCreate(['astronauta_id' => $a->id], ['vo2max' => 45.0, 'temperatura_basal' => 36.5]);

        $this->assertSame(45.0, DesviacionBase::valorBase($a, 'vo2max'));
        $this->assertSame(36.5, DesviacionBase::valorBase($a, 'temperatura_basal'));

        LineaBase::create(['astronauta_id' => $a->id, 'metrica' => 'vo2max', 'valor' => 50]);
        $this->assertSame(50.0, DesviacionBase::valorBase($a, 'vo2max')); // linea_base gana al expediente
        $this->assertNull(DesviacionBase::valorBase($a, 'dmo_cadera'));   // sin campo equivalente
    }

    public function test_temperatura_usa_la_base_pre_vuelo_si_existe(): void
    {
        [, $a] = $this->crew();
        $this->assertNull(DesviacionBase::paraIndicador($a, 'temperatura', 37.0));
        $this->assertNull(DesviacionBase::paraIndicador($a, 'glucosa', 90.0)); // sin métrica asociada

        ExpedienteMedico::updateOrCreate(['astronauta_id' => $a->id], ['temperatura_basal' => 36.0]);
        $this->assertSame(['metrica' => 'temperatura_basal', 'base' => 36.0, 'pct' => 2.8], DesviacionBase::paraIndicador($a, 'temperatura', 37.0));

        LineaBase::create(['astronauta_id' => $a->id, 'metrica' => 'temperatura_basal', 'valor' => 37.0]);
        $this->assertSame(['metrica' => 'temperatura_basal', 'base' => 37.0, 'pct' => 0.0], DesviacionBase::paraIndicador($a, 'temperatura', 37.0));
    }

    public function test_el_payload_del_indicador_trae_desv_solo_con_base_y_medicion(): void
    {
        [, $a] = $this->crew();
        $desv = fn () => collect(DatosIndicador::para($a)['marcadores'])->keyBy('id')['temperatura']['desv'] ?? null;

        LineaBase::create(['astronauta_id' => $a->id, 'metrica' => 'temperatura_basal', 'valor' => 36.0]);
        $this->assertNull($desv()); // hay base pero no medición real

        Medicion::create(['astronauta_id' => $a->id, 'indicador' => 'temperatura', 'valores' => ['valor' => 36.72], 'medido_en' => now('UTC'), 'origen' => 'manual']);
        $d = $desv();
        $this->assertSame('temperatura_basal', $d['metrica']);
        $this->assertSame(2.0, $d['pct']);
    }

    public function test_reglas_preparadas_se_evaluan_con_valores_directos(): void
    {
        $this->assertTrue(DesviacionBase::evaluarRegla('dmo_80', 1.0, 0.79));
        $this->assertFalse(DesviacionBase::evaluarRegla('dmo_80', 1.0, 0.80));
        $this->assertTrue(DesviacionBase::evaluarRegla('kcal_95', 2000, 1899));
        $this->assertFalse(DesviacionBase::evaluarRegla('kcal_95', 2000, 1900));
        $this->assertTrue(DesviacionBase::evaluarRegla('retina_20um', 250, 270));
        $this->assertFalse(DesviacionBase::evaluarRegla('retina_20um', 250, 269));
        $this->assertNull(DesviacionBase::evaluarRegla('dmo_80', 0.0, 1.0));
        $this->assertNull(DesviacionBase::evaluarRegla('no_existe', 1, 1));

        $r = DesviacionBase::evaluarReglas(['dmo_cadera' => 1.0, 'grosor_retina_um' => 250], ['dmo_cadera' => 0.7, 'grosor_retina_um' => 255]);
        $this->assertSame([['regla' => 'dmo_80', 'metrica' => 'dmo_cadera', 'texto' => 'Densidad mineral ósea por debajo del 80 % de la base']], $r);
    }

    public function test_ficha_precarga_ventana_y_guarda_la_linea_base(): void
    {
        [, $a] = $this->crew();

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->assertSet('lb.vo2max.desde', '2026-09-02')   // 2027-03-01 - 180 d
            ->assertSet('lb.vo2max.hasta', '2027-01-30')   // 2027-03-01 - 30 d
            ->assertSee('Línea base pre-vuelo')
            ->set('lb.vo2max.valor', '44,5')
            ->set('lb.vo2max.fuente', 'Prueba de esfuerzo')
            ->call('guardarLineaBase')
            ->assertHasNoErrors()
            ->assertSee('Línea base guardada.');

        $f = LineaBase::where('astronauta_id', $a->id)->where('metrica', 'vo2max')->firstOrFail();
        $this->assertSame(44.5, $f->valor);
        $this->assertSame('Prueba de esfuerzo', $f->fuente);
        $this->assertSame('2026-09-02', $f->ventana_desde->toDateString());
        $this->assertSame(1, LineaBase::where('astronauta_id', $a->id)->count()); // las vacías no crean fila

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->set('lb.vo2max.valor', '')->call('guardarLineaBase');
        $this->assertSame(0, LineaBase::where('astronauta_id', $a->id)->count());
    }

    public function test_ficha_valida_la_linea_base(): void
    {
        [, $a] = $this->crew();

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])
            ->set('lb.vo2max.valor', 'abc')
            ->set('lb.fuerza.valor', '-3')
            ->set('lb.dmo_cadera.valor', '1')
            ->set('lb.dmo_cadera.desde', '2027-01-10')
            ->set('lb.dmo_cadera.hasta', '2027-01-01')
            ->call('guardarLineaBase')
            ->assertHasErrors(['lb.vo2max.valor' => 'numeric', 'lb.fuerza.valor' => 'min', 'lb.dmo_cadera.hasta' => 'after_or_equal']);

        $this->assertSame(0, LineaBase::where('astronauta_id', $a->id)->count());
    }

    public function test_ficha_muestra_la_desviacion_actual(): void
    {
        [, $a] = $this->crew();
        LineaBase::create(['astronauta_id' => $a->id, 'metrica' => 'temperatura_basal', 'valor' => 36.0]);
        Medicion::create(['astronauta_id' => $a->id, 'indicador' => 'temperatura', 'valores' => ['valor' => 36.72], 'medido_en' => now('UTC'), 'origen' => 'manual']);

        Livewire::test(FichaTripulante::class, ['astronauta' => $a])->assertSee('Desviación:')->assertSee('+2.0 %');
    }
}
