<?php

namespace Tests\Feature;

use App\Models\Astronauta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class ApiMedicionesTest extends TestCase
{
    use DatabaseTransactions;

    private function conToken(): array
    {
        $a = Astronauta::create(['nombre' => 'Reloj', 'mision' => 'T-1', 'entorno' => 'estacion']);
        $token = 'token-de-prueba-'.$a->id;
        $a->forceFill(['token_dispositivo' => hash('sha256', $token)])->save();

        return [$a, $token];
    }

    public function test_sin_token_o_con_token_invalido_da_401(): void
    {
        $cuerpo = ['mediciones' => [['indicador' => 'frecuencia_cardiaca', 'valor' => 70, 'medido_en' => now()->toIso8601String()]]];

        $this->postJson('/api/mediciones', $cuerpo)->assertStatus(401);
        $this->withToken('falso')->postJson('/api/mediciones', $cuerpo)->assertStatus(401);
    }

    public function test_guarda_con_origen_reloj_para_el_tripulante_del_token(): void
    {
        [$a, $token] = $this->conToken();

        $this->withToken($token)->postJson('/api/mediciones', ['mediciones' => [
            ['indicador' => 'frecuencia_cardiaca', 'valor' => 72, 'medido_en' => now()->subMinutes(10)->toIso8601String()],
            ['indicador' => 'saturacion_o2', 'valor' => 97, 'medido_en' => now()->subMinutes(10)->toIso8601String()],
        ]])->assertOk()->assertJson(['guardadas' => 2, 'duplicadas' => 0, 'rechazadas' => []]);

        $this->assertSame(2, $a->mediciones()->where('origen', 'reloj')->count());
    }

    public function test_reenviar_el_mismo_lote_cuenta_duplicadas_sin_error(): void
    {
        [$a, $token] = $this->conToken();
        $cuerpo = ['mediciones' => [['indicador' => 'frecuencia_cardiaca', 'valor' => 70, 'medido_en' => now()->subMinutes(5)->toIso8601String()]]];

        $this->withToken($token)->postJson('/api/mediciones', $cuerpo)->assertOk()->assertJson(['guardadas' => 1]);
        $this->withToken($token)->postJson('/api/mediciones', $cuerpo)->assertOk()->assertJson(['guardadas' => 0, 'duplicadas' => 1]);

        $this->assertSame(1, $a->mediciones()->count());
    }

    public function test_valor_imposible_y_fecha_futura_se_rechazan_sin_tumbar_el_lote(): void
    {
        [$a, $token] = $this->conToken();

        $r = $this->withToken($token)->postJson('/api/mediciones', ['mediciones' => [
            ['indicador' => 'frecuencia_cardiaca', 'valor' => 999, 'medido_en' => now()->subMinutes(3)->toIso8601String()],
            ['indicador' => 'frecuencia_cardiaca', 'valor' => 70, 'medido_en' => now()->addHour()->toIso8601String()],
            ['indicador' => 'saturacion_o2', 'valor' => 96, 'medido_en' => now()->subMinutes(2)->toIso8601String()],
        ]])->assertOk();

        $r->assertJson(['guardadas' => 1]);
        $this->assertCount(2, $r->json('rechazadas'));
        $this->assertSame(1, $a->mediciones()->count());
    }

    public function test_indicador_no_permitido_da_422(): void
    {
        [, $token] = $this->conToken();

        $this->withToken($token)->postJson('/api/mediciones', ['mediciones' => [
            ['indicador' => 'glucosa', 'valor' => 90, 'medido_en' => now()->toIso8601String()],
        ]])->assertStatus(422);
    }
}
