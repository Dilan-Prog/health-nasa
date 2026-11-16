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

class GroundConsultationsTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    private mixed $originalLink;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLink = Cache::get('tierra.enlace');
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        $this->a = Astronauta::where('mision_id', $mision->id)->firstOrFail();
    }

    protected function tearDown(): void
    {
        $this->originalLink === null ? Cache::forget('tierra.enlace') : Cache::forever('tierra.enlace', $this->originalLink);
        parent::tearDown();
    }

    public function test_send_with_link_queues_alert_and_advances_stages(): void
    {
        Cache::forever('tierra.enlace', true);
        $c = ConsultasTierra::enviar($this->a, 'msg', ['perdida_osea', 'presion_arterial'], 'Hello');

        $this->assertFalse($c->fallida);
        $this->assertSame(0, $c->etapa);
        $this->assertSame(1, AlertaSaliente::where('astronauta_id', $this->a->id)->where('estado', 'pendiente')->where('carga->origen', 'consulta')->count());

        $this->travel(2)->seconds();
        $this->assertSame(1, ConsultasTierra::lista($this->a)[0]['etapa']);

        $this->travel(2)->hours();
        $row = ConsultasTierra::lista($this->a)[0];
        $this->assertGreaterThanOrEqual(2, $row['etapa']);
        $this->assertCount(4, $row['etapas']);
        $this->assertTrue($row['etapas'][1]['hecha']);

        $c->refresh()->update(['respuesta' => 'All good']);
        $row = ConsultasTierra::lista($this->a)[0];
        $this->assertSame(3, $row['etapa']);
        $this->assertSame('All good', $row['respuesta']);
    }

    public function test_without_link_consultation_fails_and_retry_fails(): void
    {
        Cache::forever('tierra.enlace', false);
        $c = ConsultasTierra::enviar($this->a, 'msg', ['glucosa']);

        $this->assertTrue($c->fallida);
        $this->assertFalse(ConsultasTierra::reintentar($c));
        $row = ConsultasTierra::lista($this->a)[0];
        $this->assertTrue($row['fallida']);
        $this->assertSame(0, $row['etapa']);
    }

    public function test_retry_succeeds_once_link_is_restored(): void
    {
        Cache::forever('tierra.enlace', false);
        $c = ConsultasTierra::enviar($this->a, 'msg', ['glucosa']);
        Cache::forever('tierra.enlace', true);

        $this->assertTrue(ConsultasTierra::reintentar($c));
        $c->refresh();
        $this->assertFalse($c->fallida);
        $this->assertGreaterThanOrEqual(1, $c->etapa);
    }

    public function test_invalid_type_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConsultasTierra::enviar($this->a, 'fax', ['glucosa']);
    }

    public function test_requires_at_least_one_indicator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConsultasTierra::enviar($this->a, 'msg', []);
    }

    public function test_titles(): void
    {
        Cache::forever('tierra.enlace', true);
        $m = ConsultasTierra::enviar($this->a, 'msg', ['perdida_osea', 'presion_arterial', 'glucosa']);
        $this->assertSame('Message · bone loss and blood pressure', $m->titulo);
        $v = ConsultasTierra::enviar($this->a, 'video', ['glucosa']);
        $this->assertSame('Video consultation · request', $v->titulo);
    }

    public function test_voting_toggles(): void
    {
        $this->assertNull(ConsultasTierra::voto($this->a, 'glucosa'));
        $this->assertSame('up', ConsultasTierra::votar($this->a, 'glucosa', 'up'));
        $this->assertSame('down', ConsultasTierra::votar($this->a, 'glucosa', 'down'));
        $this->assertSame('down', ConsultasTierra::voto($this->a, 'glucosa'));
        $this->assertNull(ConsultasTierra::votar($this->a, 'glucosa', 'down'));
        $this->assertNull(ConsultasTierra::voto($this->a, 'glucosa'));
    }

    public function test_available_attachments(): void
    {
        $attachments = ConsultasTierra::adjuntosDisponibles($this->a);
        $this->assertCount(3, $attachments);
        $this->assertSame(['l', 'd', 'ic'], array_keys($attachments[0]));
    }
}
