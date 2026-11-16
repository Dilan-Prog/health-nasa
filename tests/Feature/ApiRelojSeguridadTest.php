<?php

namespace Tests\Feature;

use App\Models\Astronauta;
use App\Services\Reloj\TokenReloj;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Seguridad de la API del reloj: expiración, gracia, límite, firma y batería. DatabaseTransactions: no borra datos. */
class ApiRelojSeguridadTest extends TestCase
{
    use DatabaseTransactions;

    private function tripulante(): Astronauta
    {
        return Astronauta::create(['nombre' => 'Reloj Seg', 'mision' => 'T-1', 'entorno' => 'estacion']);
    }

    private function cuerpo(array $extra = []): array
    {
        return ['mediciones' => [['indicador' => 'frecuencia_cardiaca', 'valor' => 70, 'medido_en' => now()->subMinutes(3)->toIso8601String()]]] + $extra;
    }

    /** POST con cuerpo JSON exacto y cabeceras de firma opcionales. */
    private function enviar(string $token, array $cuerpo, ?string $secreto = null, ?int $marca = null, ?string $firmaForzada = null)
    {
        $json = json_encode($cuerpo);
        $cab = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token];
        if ($secreto !== null || $firmaForzada !== null) {
            $marca ??= time();
            $cab['HTTP_X_MARCA_TIEMPO'] = (string) $marca;
            $cab['HTTP_X_FIRMA'] = $firmaForzada ?? 'sha256='.hash_hmac('sha256', $marca.'.'.$json, $secreto);
        }

        return $this->call('POST', '/api/mediciones', [], [], [], $cab, $json);
    }

    public function test_token_vencido_da_401_con_mensaje_claro(): void
    {
        $a = $this->tripulante();
        $r = app(TokenReloj::class)->emitir($a, 30);
        $a->forceFill(['token_vence_en' => now()->subMinute()])->save();

        $this->enviar($r['token'], $this->cuerpo())->assertStatus(401)->assertJsonFragment(['mensaje' => 'Token vencido. Pide un token nuevo (php artisan reloj:token).']);
    }

    public function test_token_vigente_funciona_y_el_comando_acepta_dias(): void
    {
        $a = $this->tripulante();
        $this->artisan('reloj:token', ['astronauta' => $a->id, '--dias' => 5])->assertSuccessful();
        $a->refresh();
        $this->assertEqualsWithDelta(now()->addDays(5)->timestamp, $a->token_vence_en->timestamp, 5);
        $this->assertNotNull($a->secreto_firma);
        $this->artisan('reloj:token', ['astronauta' => $a->id, '--dias' => 0])->assertFailed();
    }

    public function test_token_anterior_sigue_valido_durante_la_gracia_y_deja_de_serlo_despues(): void
    {
        $a = $this->tripulante();
        $viejo = app(TokenReloj::class)->emitir($a->fresh())['token'];
        $nuevo = app(TokenReloj::class)->emitir($a->fresh());

        $this->enviar($viejo, $this->cuerpo())->assertOk();
        $this->enviar($nuevo['token'], $this->cuerpo())->assertOk();

        $a->refresh()->forceFill(['token_previo_vence_en' => now()->subSecond()])->save();
        $this->enviar($viejo, $this->cuerpo())->assertStatus(401)->assertJsonFragment(['mensaje' => 'Token vencido. Pide un token nuevo (php artisan reloj:token).']);
        $this->enviar($nuevo['token'], $this->cuerpo())->assertOk();
    }

    public function test_el_limite_por_token_devuelve_429_sin_afectar_a_otros_tokens(): void
    {
        config(['mision.reloj_limite_por_minuto' => 2]);
        $a = $this->tripulante();
        $b = $this->tripulante();
        $ta = app(TokenReloj::class)->emitir($a)['token'];
        $tb = app(TokenReloj::class)->emitir($b)['token'];
        RateLimiter::clear('reloj:'.hash('sha256', $ta));
        RateLimiter::clear('reloj:'.hash('sha256', $tb));

        $this->enviar($ta, $this->cuerpo())->assertOk();
        $this->enviar($ta, $this->cuerpo())->assertOk();
        $this->enviar($ta, $this->cuerpo())->assertStatus(429);
        $this->enviar($tb, $this->cuerpo())->assertOk();
    }

    public function test_firma_buena_pasa_con_firma_exigida(): void
    {
        config(['mision.reloj_exigir_firma' => true]);
        $a = $this->tripulante();
        $r = app(TokenReloj::class)->emitir($a);

        $this->assertSame($r['secreto'], Crypt::decryptString($a->fresh()->secreto_firma));
        $this->assertStringNotContainsString($r['secreto'], (string) $a->fresh()->getRawOriginal('secreto_firma'));
        $this->enviar($r['token'], $this->cuerpo(), $r['secreto'])->assertOk()->assertJson(['guardadas' => 1]);
    }

    public function test_firma_mala_da_401(): void
    {
        config(['mision.reloj_exigir_firma' => true]);
        $a = $this->tripulante();
        $r = app(TokenReloj::class)->emitir($a);

        $this->enviar($r['token'], $this->cuerpo(), 'otro-secreto')->assertStatus(401)->assertJsonFragment(['mensaje' => 'Firma inválida.']);
        // Cuerpo alterado tras firmar.
        $marca = time();
        $firma = 'sha256='.hash_hmac('sha256', $marca.'.'.json_encode($this->cuerpo()), $r['secreto']);
        $this->enviar($r['token'], $this->cuerpo(['bateria' => 50]), null, $marca, $firma)->assertStatus(401);
    }

    public function test_marca_de_tiempo_antigua_da_401(): void
    {
        config(['mision.reloj_exigir_firma' => true]);
        $a = $this->tripulante();
        $r = app(TokenReloj::class)->emitir($a);

        $this->enviar($r['token'], $this->cuerpo(), $r['secreto'], time() - 600)->assertStatus(401)->assertJsonFragment(['mensaje' => 'Marca de tiempo antigua o fuera de rango.']);
    }

    public function test_con_firma_exigida_un_lote_sin_firma_da_401(): void
    {
        config(['mision.reloj_exigir_firma' => true]);
        $a = $this->tripulante();
        $r = app(TokenReloj::class)->emitir($a);

        $this->enviar($r['token'], $this->cuerpo())->assertStatus(401);
    }

    public function test_firma_no_exigida_acepta_lote_sin_firma(): void
    {
        config(['mision.reloj_exigir_firma' => false]);
        $a = $this->tripulante();
        $r = app(TokenReloj::class)->emitir($a);

        $this->enviar($r['token'], $this->cuerpo())->assertOk()->assertJson(['guardadas' => 1]);
    }

    public function test_guarda_bateria_y_la_ficha_avisa_si_es_baja(): void
    {
        $a = $this->tripulante();
        $r = app(TokenReloj::class)->emitir($a);

        $this->enviar($r['token'], $this->cuerpo(['bateria' => 12]))->assertOk();
        $a->refresh();
        $this->assertSame(12, $a->bateria_pct);
        $this->assertNotNull($a->bateria_en);

        $this->enviar($r['token'], $this->cuerpo(['bateria' => 150]))->assertStatus(422);
        $this->assertSame(12, $a->fresh()->bateria_pct);

        $m = \App\Models\Mision::create(['nombre' => 'Test Bat '.uniqid(), 'entorno' => 'nave', 'inicio_en' => now()->toDateString(), 'duracion_dias' => 100]);
        $b = app(\App\Services\Tripulacion\AltaTripulante::class)->registrar([
            'nombre' => 'Bateria Baja', 'fecha_nacimiento' => now()->subYears(35)->toDateString(), 'sexo' => 'femenino',
            'rol' => 'Ingeniera', 'agencia' => 'AEM', 'nacionalidad' => 'Mexicana', 'altura_m' => 1.66, 'peso_kg' => 60, 'grupo_sanguineo' => 'O-',
        ], $m);
        session(['mision_id' => $m->id]);
        $b->forceFill(['bateria_pct' => 12, 'bateria_en' => now()])->save();
        \Livewire\Livewire::test(\App\Livewire\FichaTripulante::class, ['astronauta' => $b])->assertSee('Batería baja');
        $b->forceFill(['bateria_pct' => 80])->save();
        \Livewire\Livewire::test(\App\Livewire\FichaTripulante::class, ['astronauta' => $b])->assertDontSee('Batería baja');
    }
}
