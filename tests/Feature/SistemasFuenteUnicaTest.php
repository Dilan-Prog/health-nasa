<?php

namespace Tests\Feature;

use App\Livewire\Sistemas;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * config/sistemas.php es la ÚNICA definición de los sistemas corporales. Estas pruebas impiden que
 * Indicador, Sistemas y el índice por sistema vuelvan a divergir.
 */
class SistemasFuenteUnicaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_son_nueve_secciones_del_documento_y_dos_grupos_del_proyecto(): void
    {
        $grupos = collect(config('sistemas.grupos'));

        $this->assertCount(9, $grupos->where('origen', 'documento'));
        $this->assertSame(['metabolico', 'radiacion'], $grupos->where('origen', 'proyecto')->pluck('clave')->values()->all());
        $this->assertSame(['1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7', '1.8', '1.9'],
            $grupos->where('origen', 'documento')->pluck('seccion')->sort()->values()->all());
        $this->assertSame($grupos->pluck('clave')->all(), $grupos->pluck('clave')->unique()->values()->all(), 'claves únicas');
    }

    public function test_la_lista_de_indicador_se_deriva_de_la_fuente_unica(): void
    {
        $esperado = collect(config('sistemas.grupos'))->map(fn ($g) => [$g['titulo'], $g['ic']])->all();

        $this->assertSame($esperado, config('indicadores.sistemas'));
    }

    public function test_cada_marcador_apunta_al_grupo_que_lo_contiene(): void
    {
        $grupos = config('sistemas.grupos');
        foreach (config('indicadores.marcadores') as $clave => $m) {
            $this->assertNotNull($m['sys'], "$clave no pertenece a ningún sistema");
            $this->assertContains($clave, $grupos[$m['sys']]['marcadores'], "$clave apunta a un grupo que no lo contiene");
        }
    }

    public function test_el_sistema_de_umbrales_coincide_con_el_grupo_de_cada_indicador(): void
    {
        $porClave = [];
        foreach (config('sistemas.grupos') as $g) {
            foreach (array_merge($g['marcadores'], $g['extra'] ?? []) as $clave) {
                $porClave[$clave] = $g['clave'];
            }
        }

        foreach (config('umbrales.indicadores') as $clave => $i) {
            $this->assertSame($porClave[$clave] ?? null, $i['sistema'] ?? null, "$clave: sistema distinto entre umbrales y config/sistemas.php");
        }
        $this->assertSame(array_column(config('sistemas.grupos'), 'clave'), config('umbrales.sistemas'));
        // El caso que antes se contradecía: la temperatura era "inmune" en umbrales y "Metabólico" en las pantallas.
        $this->assertSame('metabolico', config('umbrales.indicadores.temperatura.sistema'));
    }

    public function test_un_sistema_sin_indicadores_declara_que_falta_medir(): void
    {
        $vacios = collect(config('sistemas.grupos'))->filter(fn ($g) => $g['marcadores'] === [] && empty($g['extra']));

        $this->assertSame(['sensorial', 'inmunologico', 'hematologico'], $vacios->pluck('clave')->values()->all());
        foreach ($vacios as $g) {
            $this->assertNotEmpty($g['pendiente'] ?? '', $g['clave'].' debe decir qué falta medir');
        }
    }

    public function test_la_pantalla_marca_sin_cobertura_y_no_normal_a_los_sistemas_vacios(): void
    {
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        TripulanteActual::fijar(Astronauta::where('mision_id', $mision->id)->firstOrFail()->id);

        Livewire::test(Sistemas::class)->assertViewHas('tarjetas', function ($tarjetas) {
            $porTitulo = collect($tarjetas)->keyBy('titulo');
            foreach (['Sensorial y vestibular', 'Inmunológico', 'Hematológico'] as $t) {
                if ($porTitulo[$t]['estado'] !== 'sin' || $porTitulo[$t]['estado_txt'] !== 'SIN COBERTURA' || $porTitulo[$t]['filas'] !== []) {
                    return false;
                }
            }

            return $porTitulo['Respiratorio']['estado'] !== 'sin' && count($porTitulo['Respiratorio']['filas']) === 1;
        });
    }
}
