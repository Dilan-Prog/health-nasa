<?php

namespace Tests\Feature;

use App\Livewire\Alertas;
use App\Models\AlertaEvento;
use App\Models\AlertaGestion;
use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class AlertasTest extends TestCase
{
    use DatabaseTransactions;

    private const CLAVE = 'saturacion_o2';

    private Astronauta $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        // Tripulante nuevo y sin mediciones: la única alerta es la que creamos aquí (determinista).
        $this->a = Astronauta::create(['nombre' => 'Alertas Test', 'mision' => 'T-1', 'entorno' => 'estacion', 'mision_id' => $mision->id]);
        TripulanteActual::fijar($this->a->id);
    }

    private function alertaCritica(): void
    {
        Medicion::create(['astronauta_id' => $this->a->id, 'indicador' => self::CLAVE,
            'valores' => ['valor' => 80], 'medido_en' => now()->subMinutes(5), 'origen' => 'manual']);
    }

    private function gestion(): ?AlertaGestion
    {
        return AlertaGestion::where('astronauta_id', $this->a->id)->where('indicador', self::CLAVE)->first();
    }

    private function eventos(): int
    {
        return AlertaEvento::where('astronauta_id', $this->a->id)->count();
    }

    public function test_la_pagina_se_muestra(): void
    {
        $this->withSession(['mision_id' => $this->a->mision_id, 'astronauta_id' => $this->a->id])
            ->get('/alertas')->assertOk()->assertSee('Bandeja de alertas');
    }

    public function test_cambia_de_vista_con_el_parametro_v(): void
    {
        Livewire::withQueryParams(['v' => 'b'])->test(Alertas::class)
            ->assertSet('vista', 'b')->assertSee('Nada aquí');
        Livewire::withQueryParams(['v' => 'c'])->test(Alertas::class)
            ->assertSet('vista', 'c')->assertSee('Línea de tiempo');
    }

    public function test_una_medicion_critica_aparece_como_alerta_nueva(): void
    {
        $this->alertaCritica();
        Livewire::test(Alertas::class)->assertSee('Saturación O₂')
            ->assertViewHas('lista', fn ($l) => count($l) === 1 && $l[0]['clave'] === self::CLAVE
                && $l[0]['s'] === 'crit' && $l[0]['est'] === 'nueva' && $l[0]['lvl'] === 0);
    }

    public function test_reconocer_guarda_gestion_y_evento(): void
    {
        $this->alertaCritica();
        Livewire::test(Alertas::class)->call('reconocer', self::CLAVE);

        $g = $this->gestion();
        $this->assertSame('vista', $g->estado);
        $this->assertSame(0, $g->nivel);
        $this->assertSame('crit', $g->severidad);
        $this->assertSame(1, $this->eventos());
    }

    public function test_reconocer_dos_veces_no_duplica_el_evento(): void
    {
        $this->alertaCritica();
        Livewire::test(Alertas::class)->call('reconocer', self::CLAVE)->call('reconocer', self::CLAVE);

        $this->assertSame(1, $this->eventos());
    }

    public function test_escalar_sube_de_nivel_y_a_flight_surgeon_encola_el_aviso(): void
    {
        $this->alertaCritica();
        $c = Livewire::test(Alertas::class)->call('escalar', self::CLAVE);

        $this->assertSame('escalada', $this->gestion()->estado);
        $this->assertSame(1, $this->gestion()->nivel);
        $this->assertSame(0, AlertaSaliente::where('astronauta_id', $this->a->id)->count());

        $c->call('escalar', self::CLAVE);

        $this->assertSame(2, $this->gestion()->nivel);
        $this->assertSame(2, $this->eventos());
        $this->assertTrue(AlertaEvento::where('astronauta_id', $this->a->id)->where('mensaje', 'like', '%Flight surgeon%')->exists());
        $s = AlertaSaliente::where('astronauta_id', $this->a->id)->get();
        $this->assertCount(1, $s);
        $this->assertSame('pendiente', $s[0]->estado);
        $this->assertSame(self::CLAVE, $s[0]->carga['indicador']);
        $this->assertSame('escalamiento_manual', $s[0]->carga['origen']);
    }

    public function test_escalar_en_el_nivel_maximo_no_hace_nada(): void
    {
        $this->alertaCritica();
        $c = Livewire::test(Alertas::class)->call('escalar', self::CLAVE)->call('escalar', self::CLAVE);
        $eventos = $this->eventos();

        $c->call('escalar', self::CLAVE);

        $this->assertSame(2, $this->gestion()->nivel);
        $this->assertSame($eventos, $this->eventos());
        $this->assertSame(1, AlertaSaliente::where('astronauta_id', $this->a->id)->count());
    }

    public function test_resolver_y_reabrir(): void
    {
        $this->alertaCritica();
        $c = Livewire::test(Alertas::class)->call('resolver', self::CLAVE);

        $this->assertSame('resuelta', $this->gestion()->estado);
        $this->assertSame(1, $this->eventos());
        $c->assertViewHas('lista', fn ($l) => count($l) === 0);

        $c->call('reabrir', self::CLAVE);

        $this->assertSame('vista', $this->gestion()->estado);
        $this->assertSame(2, $this->eventos());
        $this->assertDatabaseHas('alertas_eventos', ['astronauta_id' => $this->a->id, 'mensaje' => 'Saturación O₂: reabierta']);
    }

    public function test_escalar_una_alerta_resuelta_no_hace_nada(): void
    {
        $this->alertaCritica();
        Livewire::test(Alertas::class)->call('resolver', self::CLAVE)->call('escalar', self::CLAVE);

        $this->assertSame('resuelta', $this->gestion()->estado);
        $this->assertSame(0, $this->gestion()->nivel);
        $this->assertSame(1, $this->eventos());
        $this->assertSame(0, AlertaSaliente::where('astronauta_id', $this->a->id)->count());
    }

    public function test_clave_inexistente_se_ignora(): void
    {
        $this->alertaCritica();
        Livewire::test(Alertas::class)->call('escalar', 'no_existe')->call('resolver', 'no_existe');

        $this->assertNull($this->gestion());
        $this->assertSame(0, $this->eventos());
    }
}
