<?php

namespace Tests\Feature;

use App\Livewire\Guia;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Models\Requisito;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Database\Seeders\RequisitosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class FuenteChipTest extends TestCase
{
    use DatabaseTransactions;

    private function tripulante(): void
    {
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        TripulanteActual::fijar(Astronauta::where('mision_id', $mision->id)->firstOrFail()->id);
    }

    public function test_el_chip_muestra_etiqueta_revision_y_valor_de_ejemplo(): void
    {
        $html = Blade::render('<x-fuente-chip tipo="hrp" revision="Rev. X" />');
        $this->assertStringContainsString('HRP (observación)', $html);
        $this->assertStringContainsString('Rev. X', $html);
        $this->assertStringContainsString('valor de ejemplo', $html);
    }

    public function test_el_chip_validado_no_muestra_valor_de_ejemplo(): void
    {
        $html = Blade::render('<x-fuente-chip tipo="norma" :validado="true" />');
        $this->assertStringContainsString('NASA-STD-3001 Vol. 1 Rev. C', $html);
        $this->assertStringNotContainsString('valor de ejemplo', $html);
    }

    public function test_el_chip_acepta_etiqueta_propia(): void
    {
        $this->assertStringContainsString('Mi fuente', Blade::render('<x-fuente-chip tipo="guia" label="Mi fuente" />'));
    }

    public function test_la_guia_muestra_chips_y_el_fallback_sin_requisito(): void
    {
        $this->tripulante();
        $this->seed(RequisitosSeeder::class);

        Livewire::test(Guia::class)
            ->assertSee('valor de ejemplo')
            ->assertSee('NASA-STD-3001 Vol. 1 Rev. C')
            ->assertSee('Umbral de ejemplo (sin requisito asociado)') // glucosa, imc y SpO2 no tienen requisito
            ->assertViewHas('tarjetas', fn ($t) => collect($t)->firstWhere('clave', 'perdida_osea')['fuentes'] !== []
                && collect($t)->firstWhere('clave', 'glucosa')['fuentes'] === []);
    }

    public function test_la_guia_carga_los_requisitos_con_una_sola_consulta(): void
    {
        $this->tripulante();
        $this->seed(RequisitosSeeder::class);

        $n = 0;
        DB::listen(function ($q) use (&$n) {
            if (str_contains($q->sql, 'requisitos')) {
                $n++;
            }
        });
        Livewire::test(Guia::class);
        $this->assertSame(1, $n);
    }

    public function test_la_guia_se_renderiza_con_la_tabla_de_requisitos_vacia(): void
    {
        $this->tripulante();
        Requisito::query()->delete();

        Livewire::test(Guia::class)
            ->assertSee('Cómo afecta el espacio a tu cuerpo')
            ->assertSee('Umbral de ejemplo (sin requisito asociado)')
            ->assertDontSee('Fuente: ');
    }

    public function test_la_guia_muestra_la_tarjeta_de_animo_sin_tocar_los_14_marcadores(): void
    {
        $this->tripulante();
        $this->assertCount(14, config('indicadores.marcadores'));

        Livewire::test(Guia::class)
            ->assertSee('Estado de ánimo')
            ->assertSee('MÍN 50 · MÁX 100 VAS 0-100')
            ->assertViewHas('tarjetas', fn ($t) => count($t) === 14)
            ->assertViewHas('extras', fn ($x) => count($x) === 1 && $x[0]['clave'] === 'animo');
    }

    public function test_la_tarjeta_de_animo_muestra_el_ultimo_valor_del_tripulante(): void
    {
        $this->tripulante();
        $a = TripulanteActual::obtener();
        \App\Models\Medicion::create(['astronauta_id' => $a->id, 'indicador' => 'animo', 'valores' => ['valor' => 20], 'medido_en' => now(), 'origen' => 'manual']);

        Livewire::test(Guia::class)
            ->assertViewHas('extras', fn ($x) => $x[0]['valor'] === '20' && $x[0]['estado'] === 'crit');
    }
}
