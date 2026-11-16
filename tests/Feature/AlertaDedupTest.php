<?php

namespace Tests\Feature;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Services\Salud\ProcesadorMediciones;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class AlertaDedupTest extends TestCase
{
    use DatabaseTransactions;

    private function astro(): Astronauta
    {
        return Astronauta::create(['nombre' => 'Dedup', 'mision' => 'T-1', 'entorno' => 'base_marciana']);
    }

    public function test_simulado_no_repite_alerta_del_mismo_indicador_en_la_ventana(): void
    {
        $a = $this->astro();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 150], 'simulado', now()->subMinutes(3));
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 152], 'simulado', now()->subMinutes(2));
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 154], 'simulado', now()->subMinute());

        $this->assertSame(1, AlertaSaliente::where('astronauta_id', $a->id)->count());
    }

    public function test_otro_indicador_y_otro_astronauta_si_alertan(): void
    {
        $a = $this->astro();
        $b = $this->astro();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 150], 'simulado');
        $p->registrar($a, 'saturacion_o2', ['valor' => 80], 'simulado');
        $p->registrar($b, 'frecuencia_cardiaca', ['valor' => 150], 'simulado');

        $this->assertSame(2, AlertaSaliente::where('astronauta_id', $a->id)->count());
        $this->assertSame(1, AlertaSaliente::where('astronauta_id', $b->id)->count());
    }

    public function test_pasada_la_ventana_vuelve_a_alertar(): void
    {
        $a = $this->astro();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 150], 'simulado');
        AlertaSaliente::where('astronauta_id', $a->id)->update(['created_at' => now()->subMinutes(30)]);
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 155], 'simulado', now()->addMinutes(5));

        $this->assertSame(2, AlertaSaliente::where('astronauta_id', $a->id)->count());
    }

    public function test_origen_manual_no_cambia_y_sigue_alertando_cada_vez(): void
    {
        $a = $this->astro();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 150], 'manual', now()->subMinutes(3));
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 152], 'manual', now()->subMinutes(2));
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 154], 'reloj', now()->subMinute());

        $this->assertSame(3, AlertaSaliente::where('astronauta_id', $a->id)->count());
    }
}
