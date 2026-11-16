<?php

namespace Tests\Feature;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Services\Salud\ConsultasTierra;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class ConsultasTierraTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    private mixed $enlaceOriginal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enlaceOriginal = Cache::get('tierra.enlace');
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        $this->a = Astronauta::where('mision_id', $mision->id)->firstOrFail();
    }

    protected function tearDown(): void
    {
        $this->enlaceOriginal === null ? Cache::forget('tierra.enlace') : Cache::forever('tierra.enlace', $this->enlaceOriginal);
        parent::tearDown();
    }

    public function test_enviar_con_enlace_encola_alerta_y_avanza_las_etapas(): void
    {
        Cache::forever('tierra.enlace', true);
        $c = ConsultasTierra::enviar($this->a, 'msg', ['perdida_osea', 'presion_arterial'], 'Hola');

        $this->assertFalse($c->fallida);
        $this->assertSame(0, $c->etapa);
        $this->assertSame(1, AlertaSaliente::where('astronauta_id', $this->a->id)->where('estado', 'pendiente')->where('carga->origen', 'consulta')->count());

        $this->travel(2)->seconds();
        $this->assertSame(1, ConsultasTierra::lista($this->a)[0]['etapa']);

        $this->travel(2)->hours();
        $fila = ConsultasTierra::lista($this->a)[0];
        $this->assertGreaterThanOrEqual(2, $fila['etapa']);
        $this->assertCount(4, $fila['etapas']);
        $this->assertTrue($fila['etapas'][1]['hecha']);

        $c->refresh()->update(['respuesta' => 'Todo bien']);
        $fila = ConsultasTierra::lista($this->a)[0];
        $this->assertSame(3, $fila['etapa']);
        $this->assertSame('Todo bien', $fila['respuesta']);
    }

    public function test_sin_enlace_queda_fallida_y_reintentar_falla(): void
    {
        Cache::forever('tierra.enlace', false);
        $c = ConsultasTierra::enviar($this->a, 'msg', ['glucosa']);

        $this->assertTrue($c->fallida);
        $this->assertFalse(ConsultasTierra::reintentar($c));
        $fila = ConsultasTierra::lista($this->a)[0];
        $this->assertTrue($fila['fallida']);
        $this->assertSame(0, $fila['etapa']);
    }

    public function test_con_enlace_luego_reintentar_funciona(): void
    {
        Cache::forever('tierra.enlace', false);
        $c = ConsultasTierra::enviar($this->a, 'msg', ['glucosa']);
        Cache::forever('tierra.enlace', true);

        $this->assertTrue(ConsultasTierra::reintentar($c));
        $c->refresh();
        $this->assertFalse($c->fallida);
        $this->assertGreaterThanOrEqual(1, $c->etapa);
    }

    public function test_tipo_invalido_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConsultasTierra::enviar($this->a, 'fax', ['glucosa']);
    }

    public function test_exige_al_menos_un_indicador(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConsultasTierra::enviar($this->a, 'msg', []);
    }

    public function test_titulos(): void
    {
        Cache::forever('tierra.enlace', true);
        $m = ConsultasTierra::enviar($this->a, 'msg', ['perdida_osea', 'presion_arterial', 'glucosa']);
        $this->assertSame('Mensaje · pérdida ósea y presión arterial', $m->titulo);
        $v = ConsultasTierra::enviar($this->a, 'video', ['glucosa']);
        $this->assertSame('Videoconsulta · solicitud', $v->titulo);
    }

    public function test_votar_alterna(): void
    {
        $this->assertNull(ConsultasTierra::voto($this->a, 'glucosa'));
        $this->assertSame('up', ConsultasTierra::votar($this->a, 'glucosa', 'up'));
        $this->assertSame('down', ConsultasTierra::votar($this->a, 'glucosa', 'down'));
        $this->assertSame('down', ConsultasTierra::voto($this->a, 'glucosa'));
        $this->assertNull(ConsultasTierra::votar($this->a, 'glucosa', 'down'));
        $this->assertNull(ConsultasTierra::voto($this->a, 'glucosa'));
    }

    public function test_adjuntos_disponibles(): void
    {
        $adj = ConsultasTierra::adjuntosDisponibles($this->a);
        $this->assertCount(3, $adj);
        $this->assertSame(['l', 'd', 'ic'], array_keys($adj[0]));
    }
}
