<?php

namespace Tests\Feature;

use App\Contracts\Orientador;
use App\Livewire\Panel;
use App\Models\Astronauta;
use App\Models\CasoClinico;
use App\Models\Mision;
use App\Services\Ia\Orientacion;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase). El Orientador se fakea. */
class PanelCasoTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    private Astronauta $otro;

    private object $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        [$this->a, $this->otro] = Astronauta::where('mision_id', $mision->id)->orderBy('id')->take(2)->get()->all();
        TripulanteActual::fijar($this->a->id);

        $this->fake = new class implements Orientador
        {
            public int $llamadas = 0;

            public function orientar(CasoClinico $caso): Orientacion
            {
                $this->llamadas++;

                return new Orientacion(
                    casoId: $caso->id, astronautaId: $caso->astronauta_id, patron: $caso->patron, severidad: $caso->severidad,
                    titulo: 'Posible hipoxia (prueba '.$caso->astronauta_id.')', hallazgo: 'Los valores podrían sugerir un patrón de prueba.',
                    acciones: [['texto' => 'Descansa y repite la medición', 'fuente' => null]],
                    historial7d: [['indicador' => 'spo2', 'etiqueta' => 'SpO₂', 'unidad' => '%', 'serie' => [98, 96, 94], 'actual' => '94', 'base' => '98']],
                    expedienteRelevante: ['Sin antecedentes relacionados'], tratamientos: ['Sin tratamientos registrados'],
                    fuentes: [], confianza: 60,
                );
            }
        };
        $this->app->instance(Orientador::class, $this->fake);
    }

    private function caso(Astronauta $a, string $estado = 'abierto'): CasoClinico
    {
        return CasoClinico::create([
            'astronauta_id' => $a->id, 'patron' => 'hipoxia', 'severidad' => 'precaucion', 'evidencia' => [], 'estado' => $estado,
            'detectado_en' => now(), 'cerrado_en' => $estado === 'cerrado' ? now() : null,
        ]);
    }

    public function test_sin_caso_no_hay_tarjeta(): void
    {
        Livewire::test(Panel::class)->assertDontSee('panel-caso')->assertViewHas('caso', null);
        $this->assertSame(0, $this->fake->llamadas);
    }

    public function test_un_caso_cerrado_o_de_otro_tripulante_no_genera_tarjeta(): void
    {
        $this->caso($this->a, 'cerrado');
        $this->caso($this->otro);
        Livewire::test(Panel::class)->assertDontSee('panel-caso')->assertDontSee('Posible hipoxia (prueba');
    }

    public function test_con_caso_abierto_muestra_la_tarjeta_y_conserva_las_sugerencias(): void
    {
        $this->caso($this->a);
        $t = Livewire::test(Panel::class)
            ->assertSee('Posible hipoxia (prueba '.$this->a->id.')')
            ->assertSee('Los valores podrían sugerir un patrón de prueba.')
            ->assertSee('Descansa y repite la medición')
            ->assertSee('Abrir en Médico de vuelo')
            ->assertSee('abrir-medico-flotante', false)
            ->assertSee('Qué hacer ahora')
            ->assertSee('Autochequeo guiado')
            ->assertSee('Esta herramienta orienta, no diagnostica');
        // Las tarjetas de siempre siguen: la vista recibe las mismas claves que antes.
        $t->assertViewHas('tarjetas');
        $t->assertViewHas('caso');
    }

    public function test_el_orientador_no_se_llama_dos_veces_por_el_mismo_caso(): void
    {
        $caso = $this->caso($this->a);
        $t = Livewire::test(Panel::class);
        $t->call('refrescar');
        // También el flotante comparte la misma orientación cacheada.
        \App\Livewire\MedicoFlotante::orientacionDe($caso->fresh());
        $this->assertSame(1, $this->fake->llamadas);
    }
}
