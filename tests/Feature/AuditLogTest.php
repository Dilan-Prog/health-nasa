<?php

namespace Tests\Feature;

use App\Livewire\Auditoria as AuditScreen;
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

class AuditLogTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $rol): User
    {
        return User::create(['name' => $rol, 'email' => $rol.'-aud@example.test', 'password' => 'Test-password-1', 'rol' => $rol]);
    }

    private function crewMember(): Astronauta
    {
        $m = Mision::create(['nombre' => 'AUD-1', 'entorno' => 'estacion', 'inicio_en' => now()->subDays(10), 'duracion_dias' => 180]);

        return Astronauta::create(['nombre' => 'Audit', 'mision' => 'AUD-1', 'entorno' => 'estacion', 'mision_id' => $m->id]);
    }

    public function test_anonymous_access_is_logged_without_login(): void
    {
        config(['mision.exigir_login' => false]);
        $a = $this->crewMember();

        $r = Auditoria::registrar('ver', 'ficha', $a->id, $a->id);

        $this->assertSame('anonimo', $r->rol);
        $this->assertNull($r->user_id);
        $this->assertDatabaseHas('auditoria_accesos', ['accion' => 'ver', 'recurso' => 'ficha', 'astronauta_id' => $a->id]);
    }

    public function test_records_user_and_role(): void
    {
        $u = $this->user('cmo');
        $this->actingAs($u);

        $r = Auditoria::registrar('exportar', 'expediente', 5, 5);

        $this->assertSame($u->id, $r->user_id);
        $this->assertSame('cmo', $r->rol);
    }

    public function test_deduplicates_within_window_and_records_outside_it(): void
    {
        $this->actingAs($this->user('tripulante'));

        Auditoria::registrar('ver', 'botiquin');
        Auditoria::registrar('ver', 'botiquin');
        $this->assertSame(1, AuditoriaAcceso::count());

        Auditoria::registrar('ver', 'ficha', 3, 3);
        $this->assertSame(2, AuditoriaAcceso::count());

        AuditoriaAcceso::query()->update(['ocurrido_en' => now()->subMinutes(6)]);
        Auditoria::registrar('ver', 'botiquin');
        $this->assertSame(3, AuditoriaAcceso::count());
    }

    public function test_window_is_configurable(): void
    {
        config(['auditoria.ventana_dedup_min' => 1]);
        Auditoria::registrar('ver', 'botiquin');
        AuditoriaAcceso::query()->update(['ocurrido_en' => now()->subMinutes(2)]);
        Auditoria::registrar('ver', 'botiquin');

        $this->assertSame(2, AuditoriaAcceso::count());
    }

    public function test_audit_failure_does_not_break(): void
    {
        // An action too long for the column causes a DB error: it is reported and returns null without throwing.
        $this->assertNull(Auditoria::registrar(str_repeat('a', 500), 'botiquin'));
        $this->assertSame(0, AuditoriaAcceso::count());
    }

    public function test_ground_send_is_logged_without_medical_values(): void
    {
        $a = $this->crewMember();
        ConsultasTierra::enviar($a, 'msg', ['frecuencia_cardiaca'], 'Note with chest pain 187 bpm');

        $row = AuditoriaAcceso::where('accion', 'enviar')->firstOrFail();
        $this->assertSame('consultas_tierra', $row->recurso);
        $this->assertSame($a->id, $row->astronauta_id);
        $dump = json_encode($row->getAttributes());
        $this->assertStringNotContainsString('187', $dump);
        $this->assertStringNotContainsString('chest pain', $dump);
        $this->assertStringNotContainsString('frecuencia_cardiaca', $dump);
        $this->assertSame(
            ['id', 'user_id', 'rol', 'accion', 'recurso', 'recurso_id', 'astronauta_id', 'ip', 'user_agent', 'ocurrido_en'],
            array_keys($row->getAttributes())
        );
    }

    public function test_sensitive_screens_log_on_mount(): void
    {
        $a = $this->crewMember();
        Livewire::test(\App\Livewire\Botiquin::class);
        Livewire::test(\App\Livewire\ColaTierra::class);
        Livewire::test(\App\Livewire\MedicoVuelo::class);
        Livewire::test(\App\Livewire\FichaTripulante::class, ['astronauta' => $a]);

        $this->assertEqualsCanonicalizing(
            ['botiquin', 'consultas_tierra', 'medico_vuelo', 'ficha'],
            AuditoriaAcceso::pluck('recurso')->all()
        );
    }

    public function test_crew_member_cannot_open_audit_log(): void
    {
        config(['mision.exigir_login' => true]);
        $this->actingAs($this->user('tripulante'));

        Livewire::test(AuditScreen::class)->assertForbidden();
    }

    public function test_cmo_cannot_either_but_flight_surgeon_can(): void
    {
        config(['mision.exigir_login' => true]);
        Auditoria::registrar('ver', 'botiquin');

        $this->actingAs($this->user('cmo'));
        Livewire::test(AuditScreen::class)->assertForbidden();

        $this->actingAs($this->user('flight_surgeon'));
        Livewire::test(AuditScreen::class)->assertOk()->assertSee('botiquin');
    }

    public function test_audit_route_is_flight_surgeon_only(): void
    {
        config(['mision.exigir_login' => true]);

        $this->actingAs($this->user('tripulante'))->get('/auditoria')->assertForbidden();
        $this->actingAs($this->user('flight_surgeon'))->get('/auditoria')->assertOk()->assertSee('Audit log');
    }

    public function test_filters(): void
    {
        $a = $this->crewMember();
        Auditoria::registrar('ver', 'botiquin');
        Auditoria::registrar('enviar', 'consultas_tierra', 1, $a->id);

        Livewire::test(AuditScreen::class)
            ->set('accion', 'enviar')->assertSeeHtml('<td>consultas_tierra #1</td>')->assertDontSeeHtml('<td>botiquin</td>')
            ->set('accion', '')->set('tripulante', (string) $a->id)->assertSee('consultas_tierra')
            ->set('tripulante', '')->set('desde', now()->addDay()->toDateString())->assertSee('No records');
    }

    public function test_prune_simulates_and_deletes_with_confirm(): void
    {
        Auditoria::registrar('ver', 'botiquin');
        Auditoria::registrar('ver', 'ficha', 1, 1);
        AuditoriaAcceso::where('recurso', 'botiquin')->update(['ocurrido_en' => now()->subDays(91)]);

        $this->artisan('auditoria:podar')->expectsOutputToContain('Records to prune: 1')->assertSuccessful();
        $this->assertSame(2, AuditoriaAcceso::count());

        $this->artisan('auditoria:podar --confirmar')->assertSuccessful();
        $this->assertSame(1, AuditoriaAcceso::count());
    }
}
