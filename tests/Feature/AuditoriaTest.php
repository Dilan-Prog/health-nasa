<?php

namespace Tests\Feature;

use App\Livewire\Auditoria as PantallaAuditoria;
use App\Models\Astronauta;
use App\Models\AuditoriaAcceso;
use App\Models\Mision;
use App\Models\User;
use App\Services\Auditoria;
use App\Services\Salud\ConsultasTierra;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(string $rol): User
    {
        return User::create(['name' => $rol, 'email' => $rol.'-aud@prueba.test', 'password' => 'Clave-prueba-1', 'rol' => $rol]);
    }

    private function tripulante(): Astronauta
    {
        $m = Mision::create(['nombre' => 'AUD-1', 'entorno' => 'estacion', 'inicio_en' => now()->subDays(10), 'duracion_dias' => 180]);

        return Astronauta::create(['nombre' => 'Audit', 'mision' => 'AUD-1', 'entorno' => 'estacion', 'mision_id' => $m->id]);
    }

    public function test_se_registra_el_acceso_anonimo_sin_login(): void
    {
        config(['mision.exigir_login' => false]);
        $a = $this->tripulante();

        $r = Auditoria::registrar('ver', 'ficha', $a->id, $a->id);

        $this->assertSame('anonimo', $r->rol);
        $this->assertNull($r->user_id);
        $this->assertDatabaseHas('auditoria_accesos', ['accion' => 'ver', 'recurso' => 'ficha', 'astronauta_id' => $a->id]);
    }

    public function test_registra_usuario_y_rol(): void
    {
        $u = $this->usuario('cmo');
        $this->actingAs($u);

        $r = Auditoria::registrar('exportar', 'expediente', 5, 5);

        $this->assertSame($u->id, $r->user_id);
        $this->assertSame('cmo', $r->rol);
    }

    public function test_deduplica_dentro_de_la_ventana_y_registra_fuera(): void
    {
        $this->actingAs($this->usuario('tripulante'));

        Auditoria::registrar('ver', 'botiquin');
        Auditoria::registrar('ver', 'botiquin');
        $this->assertSame(1, AuditoriaAcceso::count());

        Auditoria::registrar('ver', 'ficha', 3, 3);
        $this->assertSame(2, AuditoriaAcceso::count());

        AuditoriaAcceso::query()->update(['ocurrido_en' => now()->subMinutes(6)]);
        Auditoria::registrar('ver', 'botiquin');
        $this->assertSame(3, AuditoriaAcceso::count());
    }

    public function test_la_ventana_es_configurable(): void
    {
        config(['auditoria.ventana_dedup_min' => 1]);
        Auditoria::registrar('ver', 'botiquin');
        AuditoriaAcceso::query()->update(['ocurrido_en' => now()->subMinutes(2)]);
        Auditoria::registrar('ver', 'botiquin');

        $this->assertSame(2, AuditoriaAcceso::count());
    }

    public function test_un_fallo_al_auditar_no_rompe(): void
    {
        // Una acción demasiado larga para la columna provoca un error de BD: se reporta y devuelve null, sin lanzar.
        $this->assertNull(Auditoria::registrar(str_repeat('a', 500), 'botiquin'));
        $this->assertSame(0, AuditoriaAcceso::count());
    }

    public function test_el_envio_a_tierra_queda_registrado_sin_valores_medicos(): void
    {
        $a = $this->tripulante();
        ConsultasTierra::enviar($a, 'msg', ['frecuencia_cardiaca'], 'Nota con dolor de pecho 187 lpm');

        $fila = AuditoriaAcceso::where('accion', 'enviar')->firstOrFail();
        $this->assertSame('consultas_tierra', $fila->recurso);
        $this->assertSame($a->id, $fila->astronauta_id);
        $volcado = json_encode($fila->getAttributes());
        $this->assertStringNotContainsString('187', $volcado);
        $this->assertStringNotContainsString('dolor', $volcado);
        $this->assertStringNotContainsString('frecuencia_cardiaca', $volcado);
        $this->assertSame(
            ['id', 'user_id', 'rol', 'accion', 'recurso', 'recurso_id', 'astronauta_id', 'ip', 'user_agent', 'ocurrido_en'],
            array_keys($fila->getAttributes())
        );
    }

    public function test_pantallas_sensibles_registran_al_montar(): void
    {
        $a = $this->tripulante();
        Livewire::test(\App\Livewire\Botiquin::class);
        Livewire::test(\App\Livewire\ColaTierra::class);
        Livewire::test(\App\Livewire\MedicoVuelo::class);
        Livewire::test(\App\Livewire\FichaTripulante::class, ['astronauta' => $a]);

        $this->assertEqualsCanonicalizing(
            ['botiquin', 'consultas_tierra', 'medico_vuelo', 'ficha'],
            AuditoriaAcceso::pluck('recurso')->all()
        );
    }

    public function test_el_tripulante_no_puede_abrir_la_auditoria(): void
    {
        config(['mision.exigir_login' => true]);
        $this->actingAs($this->usuario('tripulante'));

        Livewire::test(PantallaAuditoria::class)->assertForbidden();
    }

    public function test_el_cmo_tampoco_y_el_flight_surgeon_si(): void
    {
        config(['mision.exigir_login' => true]);
        Auditoria::registrar('ver', 'botiquin');

        $this->actingAs($this->usuario('cmo'));
        Livewire::test(PantallaAuditoria::class)->assertForbidden();

        $this->actingAs($this->usuario('flight_surgeon'));
        Livewire::test(PantallaAuditoria::class)->assertOk()->assertSee('botiquin');
    }

    public function test_ruta_auditoria_solo_flight_surgeon(): void
    {
        require base_path('routes/auditoria.php');
        app('router')->getRoutes()->refreshNameLookups();
        config(['mision.exigir_login' => true]);

        $this->actingAs($this->usuario('tripulante'))->get('/auditoria')->assertForbidden();
        $this->actingAs($this->usuario('flight_surgeon'))->get('/auditoria')->assertOk()->assertSee('Registro de accesos');
    }

    public function test_filtros(): void
    {
        $a = $this->tripulante();
        Auditoria::registrar('ver', 'botiquin');
        Auditoria::registrar('enviar', 'consultas_tierra', 1, $a->id);

        Livewire::test(PantallaAuditoria::class)
            ->set('accion', 'enviar')->assertSeeHtml('<td>consultas_tierra #1</td>')->assertDontSeeHtml('<td>botiquin</td>')
            ->set('accion', '')->set('tripulante', (string) $a->id)->assertSee('consultas_tierra')
            ->set('tripulante', '')->set('desde', now()->addDay()->toDateString())->assertSee('Sin registros');
    }

    public function test_podar_simula_y_con_confirmar_borra(): void
    {
        Auditoria::registrar('ver', 'botiquin');
        Auditoria::registrar('ver', 'ficha', 1, 1);
        AuditoriaAcceso::where('recurso', 'botiquin')->update(['ocurrido_en' => now()->subDays(91)]);

        $this->artisan('auditoria:podar')->expectsOutputToContain('Registros a podar: 1')->assertSuccessful();
        $this->assertSame(2, AuditoriaAcceso::count());

        $this->artisan('auditoria:podar --confirmar')->assertSuccessful();
        $this->assertSame(1, AuditoriaAcceso::count());
    }
}
