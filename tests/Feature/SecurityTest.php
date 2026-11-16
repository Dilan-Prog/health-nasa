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

/** DatabaseTransactions (not RefreshDatabase): never wipes the local database. */
class SecurityTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'test-password-123';

    private function scenario(): array
    {
        $m1 = Mision::create(['nombre' => 'Mission SG1', 'entorno' => 'estacion', 'inicio_en' => now()->subDays(5), 'duracion_dias' => 30]);
        $m2 = Mision::create(['nombre' => 'Mission SG2', 'entorno' => 'estacion', 'inicio_en' => now()->subDays(5), 'duracion_dias' => 30]);
        $mk = fn ($m, $n) => Astronauta::create(['mision_id' => $m->id, 'nombre' => $n, 'mision' => 'SG', 'entorno' => 'estacion']);
        $a1 = $mk($m1, 'Ana SG');
        $a2 = $mk($m1, 'Beto SG');
        $b1 = $mk($m2, 'Carla SG');
        $u = fn ($role, $a) => tap(User::create(['name' => ucfirst($role), 'email' => $role.'-sg@example.test', 'password' => self::PASSWORD, 'rol' => $role]), function ($usr) use ($a) {
            if ($a) {
                $a->forceFill(['user_id' => $usr->id])->save();
            }
        });

        return [
            'm1' => $m1, 'm2' => $m2, 'a1' => $a1, 'a2' => $a2, 'b1' => $b1,
            'trip' => $u('tripulante', $a1), 'cmo' => $u('cmo', $a2), 'fs' => $u('flight_surgeon', null),
        ];
    }

    private function enableLogin(): void
    {
        config(['mision.exigir_login' => true]);
    }

    public function test_without_login_redirects_to_sign_in(): void
    {
        $this->enableLogin();
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/alertas')->assertRedirect(route('login'));
        $this->get('/acceso')->assertOk();
        $this->get('/aviso-legal')->assertOk();
    }

    public function test_with_flag_off_everything_works_without_login(): void
    {
        config(['mision.exigir_login' => false]);
        $this->scenario();
        $this->get('/')->assertOk();
        $this->assertSame(Astronauta::count(), Astronauta::visiblesPara(null)->count());
    }

    public function test_crew_member_sees_only_own_data(): void
    {
        $e = $this->scenario();
        $this->enableLogin();
        $this->actingAs($e['trip']);

        $this->assertEquals([$e['a1']->id], Astronauta::visiblesPara($e['trip'])->pluck('id')->all());
        $this->assertSame($e['a1']->id, TripulanteActual::obtener()->id);
        $this->assertCount(1, TripulanteActual::tripulacion());

        TripulanteActual::fijar($e['a2']->id);
        $this->assertSame($e['a1']->id, TripulanteActual::obtener()->id);
    }

    public function test_falls_back_to_first_visible_when_session_pick_is_not_visible(): void
    {
        $e = $this->scenario();
        $this->enableLogin();
        $this->actingAs($e['trip'])->withSession(['astronauta_id' => $e['a2']->id, 'mision_id' => $e['m1']->id]);

        $this->assertSame($e['a1']->id, TripulanteActual::obtener()->id);
        Livewire::test(SelectorTripulante::class)->assertSee('Ana SG')->assertDontSee('Beto SG');
    }

    public function test_cmo_sees_own_crew_and_not_another_mission(): void
    {
        $e = $this->scenario();
        $this->enableLogin();

        $ids = Astronauta::visiblesPara($e['cmo'])->pluck('id')->sort()->values()->all();
        $this->assertEquals([$e['a1']->id, $e['a2']->id], $ids);
        $this->assertNotContains($e['b1']->id, $ids);

        $this->actingAs($e['cmo'])->withSession(['mision_id' => $e['m2']->id]);
        $this->assertSame($e['m1']->id, TripulanteActual::mision()->id);
    }

    public function test_flight_surgeon_sees_everything(): void
    {
        $e = $this->scenario();
        $this->enableLogin();
        $visibles = Astronauta::visiblesPara($e['fs'])->pluck('id')->all();

        $this->assertContains($e['a1']->id, $visibles);
        $this->assertContains($e['b1']->id, $visibles);
        $this->assertSame(Astronauta::count(), count($visibles));
    }

    public function test_no_user_with_login_required_sees_nothing(): void
    {
        $this->scenario();
        $this->enableLogin();
        $this->assertSame(0, Astronauta::visiblesPara(null)->count());
    }

    public function test_login_succeeds_and_fails_correctly(): void
    {
        $e = $this->scenario();
        $this->enableLogin();

        Livewire::test(Login::class)
            ->set('email', $e['trip']->email)->set('password', 'wrong-password')->call('entrar')
            ->assertSet('error', 'Incorrect email or password.')->assertNoRedirect();
        $this->assertGuest();

        // Unknown email: exactly the same message (does not reveal whether it exists).
        Livewire::test(Login::class)
            ->set('email', 'nobody@example.test')->set('password', 'x')->call('entrar')
            ->assertSet('error', 'Incorrect email or password.');

        Livewire::test(Login::class)
            ->set('email', $e['trip']->email)->set('password', self::PASSWORD)->call('entrar')
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($e['trip']);
    }

    public function test_sign_out_and_shell_show_name_and_role(): void
    {
        $e = $this->scenario();
        $this->enableLogin();

        $this->actingAs($e['cmo'])->get('/')->assertOk()->assertSee('Sign out')->assertSee('CREW MEDICAL OFFICER (CMO)');
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_without_session_there_is_no_sign_out_button(): void
    {
        config(['mision.exigir_login' => false]);
        $this->get('/')->assertOk()->assertDontSee('Sign out');
    }

    public function test_watch_api_still_works_with_token_and_without_session(): void
    {
        $this->enableLogin();
        $a = Astronauta::create(['nombre' => 'Watch SG', 'mision' => 'T-1', 'entorno' => 'estacion']);
        $token = 'sg-token-'.$a->id;
        $a->forceFill(['token_dispositivo' => hash('sha256', $token)])->save();

        $this->withToken($token)->postJson('/api/mediciones', ['mediciones' => [
            ['indicador' => 'frecuencia_cardiaca', 'valor' => 72, 'medido_en' => now()->subMinutes(10)->toIso8601String()],
        ]])->assertOk()->assertJson(['guardadas' => 1]);
        $this->assertGuest();
    }
}
