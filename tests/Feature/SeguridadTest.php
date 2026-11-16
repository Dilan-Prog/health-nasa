<?php

namespace Tests\Feature;

use App\Livewire\Login;
use App\Livewire\SelectorTripulante;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Models\User;
use App\Support\TripulanteActual;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class SeguridadTest extends TestCase
{
    use DatabaseTransactions;

    private const CLAVE = 'clave-de-prueba-123';

    private function escenario(): array
    {
        $m1 = Mision::create(['nombre' => 'Misión SG1', 'entorno' => 'estacion', 'inicio_en' => now()->subDays(5), 'duracion_dias' => 30]);
        $m2 = Mision::create(['nombre' => 'Misión SG2', 'entorno' => 'estacion', 'inicio_en' => now()->subDays(5), 'duracion_dias' => 30]);
        $mk = fn ($m, $n) => Astronauta::create(['mision_id' => $m->id, 'nombre' => $n, 'mision' => 'SG', 'entorno' => 'estacion']);
        $a1 = $mk($m1, 'Ana SG');
        $a2 = $mk($m1, 'Beto SG');
        $b1 = $mk($m2, 'Carla SG');
        $u = fn ($rol, $a) => tap(User::create(['name' => ucfirst($rol), 'email' => $rol.'-sg@prueba.test', 'password' => self::CLAVE, 'rol' => $rol]), function ($usr) use ($a) {
            if ($a) {
                $a->forceFill(['user_id' => $usr->id])->save();
            }
        });

        return [
            'm1' => $m1, 'm2' => $m2, 'a1' => $a1, 'a2' => $a2, 'b1' => $b1,
            'trip' => $u('tripulante', $a1), 'cmo' => $u('cmo', $a2), 'fs' => $u('flight_surgeon', null),
        ];
    }

    private function activar(): void
    {
        config(['mision.exigir_login' => true]);
    }

    public function test_sin_login_redirige_al_acceso(): void
    {
        $this->activar();
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/alertas')->assertRedirect(route('login'));
        $this->get('/acceso')->assertOk();
        $this->get('/aviso-legal')->assertOk();
    }

    public function test_con_la_bandera_apagada_todo_funciona_sin_login(): void
    {
        config(['mision.exigir_login' => false]);
        $this->escenario();
        $this->get('/')->assertOk();
        $this->assertSame(Astronauta::count(), Astronauta::visiblesPara(null)->count());
    }

    public function test_el_tripulante_solo_ve_lo_suyo(): void
    {
        $e = $this->escenario();
        $this->activar();
        $this->actingAs($e['trip']);

        $this->assertEquals([$e['a1']->id], Astronauta::visiblesPara($e['trip'])->pluck('id')->all());
        $this->assertSame($e['a1']->id, TripulanteActual::obtener()->id);
        $this->assertCount(1, TripulanteActual::tripulacion());

        TripulanteActual::fijar($e['a2']->id);
        $this->assertSame($e['a1']->id, TripulanteActual::obtener()->id);
    }

    public function test_si_no_ve_al_fijado_en_sesion_cae_al_primero_visible(): void
    {
        $e = $this->escenario();
        $this->activar();
        $this->actingAs($e['trip'])->withSession(['astronauta_id' => $e['a2']->id, 'mision_id' => $e['m1']->id]);

        $this->assertSame($e['a1']->id, TripulanteActual::obtener()->id);
        Livewire::test(SelectorTripulante::class)->assertSee('Ana SG')->assertDontSee('Beto SG');
    }

    public function test_el_cmo_ve_su_tripulacion_y_no_otra_mision(): void
    {
        $e = $this->escenario();
        $this->activar();

        $ids = Astronauta::visiblesPara($e['cmo'])->pluck('id')->sort()->values()->all();
        $this->assertEquals([$e['a1']->id, $e['a2']->id], $ids);
        $this->assertNotContains($e['b1']->id, $ids);

        $this->actingAs($e['cmo'])->withSession(['mision_id' => $e['m2']->id]);
        $this->assertSame($e['m1']->id, TripulanteActual::mision()->id);
    }

    public function test_el_flight_surgeon_ve_todo(): void
    {
        $e = $this->escenario();
        $this->activar();
        $visibles = Astronauta::visiblesPara($e['fs'])->pluck('id')->all();

        $this->assertContains($e['a1']->id, $visibles);
        $this->assertContains($e['b1']->id, $visibles);
        $this->assertSame(Astronauta::count(), count($visibles));
    }

    public function test_sin_usuario_y_con_login_exigido_no_se_ve_nada(): void
    {
        $this->escenario();
        $this->activar();
        $this->assertSame(0, Astronauta::visiblesPara(null)->count());
    }

    public function test_login_correcto_e_incorrecto(): void
    {
        $e = $this->escenario();
        $this->activar();

        Livewire::test(Login::class)
            ->set('email', $e['trip']->email)->set('password', 'otra-clave')->call('entrar')
            ->assertSet('error', 'Correo o contraseña incorrectos.')->assertNoRedirect();
        $this->assertGuest();

        // Correo inexistente: exactamente el mismo mensaje (no revela si existe).
        Livewire::test(Login::class)
            ->set('email', 'nadie@prueba.test')->set('password', 'x')->call('entrar')
            ->assertSet('error', 'Correo o contraseña incorrectos.');

        Livewire::test(Login::class)
            ->set('email', $e['trip']->email)->set('password', self::CLAVE)->call('entrar')
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($e['trip']);
    }

    public function test_cerrar_sesion_y_shell_con_nombre_y_rol(): void
    {
        $e = $this->escenario();
        $this->activar();

        $this->actingAs($e['cmo'])->get('/')->assertOk()->assertSee('Cerrar sesión')->assertSee('OFICIAL MÉDICO (CMO)');
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sin_sesion_no_hay_boton_cerrar_sesion(): void
    {
        config(['mision.exigir_login' => false]);
        $this->get('/')->assertOk()->assertDontSee('Cerrar sesión');
    }

    public function test_la_api_del_reloj_sigue_con_token_y_sin_sesion(): void
    {
        $this->activar();
        $a = Astronauta::create(['nombre' => 'Reloj SG', 'mision' => 'T-1', 'entorno' => 'estacion']);
        $token = 'token-sg-'.$a->id;
        $a->forceFill(['token_dispositivo' => hash('sha256', $token)])->save();

        $this->withToken($token)->postJson('/api/mediciones', ['mediciones' => [
            ['indicador' => 'frecuencia_cardiaca', 'valor' => 72, 'medido_en' => now()->subMinutes(10)->toIso8601String()],
        ]])->assertOk()->assertJson(['guardadas' => 1]);
        $this->assertGuest();
    }
}
