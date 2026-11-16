<?php

namespace Tests\Feature;

use App\Models\Astronauta;
use App\Services\Reloj\TokenReloj;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Watch API security: expiry, grace period, rate limit, signature and battery. DatabaseTransactions: does not delete data. */
class WatchApiSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private function crewMember(): Astronauta
    {
        return Astronauta::create(['nombre' => 'Watch Sec', 'mision' => 'T-1', 'entorno' => 'estacion']);
    }

    private function body(array $extra = []): array
    {
        return ['mediciones' => [['indicador' => 'frecuencia_cardiaca', 'valor' => 70, 'medido_en' => now()->subMinutes(3)->toIso8601String()]]] + $extra;
    }

    /** POST with the exact JSON body and optional signature headers. */
    private function send(string $token, array $body, ?string $secret = null, ?int $timestamp = null, ?string $forcedSignature = null)
    {
        $json = json_encode($body);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token];
        if ($secret !== null || $forcedSignature !== null) {
            $timestamp ??= time();
            $headers['HTTP_X_MARCA_TIEMPO'] = (string) $timestamp;
            $headers['HTTP_X_FIRMA'] = $forcedSignature ?? 'sha256='.hash_hmac('sha256', $timestamp.'.'.$json, $secret);
        }

        return $this->call('POST', '/api/mediciones', [], [], [], $headers, $json);
    }

    public function test_expired_token_returns_401_with_clear_message(): void
    {
        $a = $this->crewMember();
        $r = app(TokenReloj::class)->emitir($a, 30);
        $a->forceFill(['token_vence_en' => now()->subMinute()])->save();

        $this->send($r['token'], $this->body())->assertStatus(401)->assertJsonFragment(['mensaje' => 'Token expired. Request a new token (php artisan reloj:token).']);
    }

    public function test_valid_token_works_and_command_accepts_days(): void
    {
        $a = $this->crewMember();
        $this->artisan('reloj:token', ['astronauta' => $a->id, '--dias' => 5])->assertSuccessful();
        $a->refresh();
        $this->assertEqualsWithDelta(now()->addDays(5)->timestamp, $a->token_vence_en->timestamp, 5);
        $this->assertNotNull($a->secreto_firma);
        $this->artisan('reloj:token', ['astronauta' => $a->id, '--dias' => 0])->assertFailed();
    }

    public function test_previous_token_stays_valid_during_grace_then_stops(): void
    {
        $a = $this->crewMember();
        $old = app(TokenReloj::class)->emitir($a->fresh())['token'];
        $new = app(TokenReloj::class)->emitir($a->fresh());

        $this->send($old, $this->body())->assertOk();
        $this->send($new['token'], $this->body())->assertOk();

        $a->refresh()->forceFill(['token_previo_vence_en' => now()->subSecond()])->save();
        $this->send($old, $this->body())->assertStatus(401)->assertJsonFragment(['mensaje' => 'Token expired. Request a new token (php artisan reloj:token).']);
        $this->send($new['token'], $this->body())->assertOk();
    }

    public function test_per_token_limit_returns_429_without_affecting_other_tokens(): void
    {
        config(['mision.reloj_limite_por_minuto' => 2]);
        $a = $this->crewMember();
        $b = $this->crewMember();
        $tokenA = app(TokenReloj::class)->emitir($a)['token'];
        $tokenB = app(TokenReloj::class)->emitir($b)['token'];
        RateLimiter::clear('reloj:'.hash('sha256', $tokenA));
        RateLimiter::clear('reloj:'.hash('sha256', $tokenB));

        $this->send($tokenA, $this->body())->assertOk();
        $this->send($tokenA, $this->body())->assertOk();
        $this->send($tokenA, $this->body())->assertStatus(429);
        $this->send($tokenB, $this->body())->assertOk();
    }

    public function test_good_signature_passes_when_signature_required(): void
    {
        config(['mision.reloj_exigir_firma' => true]);
        $a = $this->crewMember();
        $r = app(TokenReloj::class)->emitir($a);

        $this->assertSame($r['secreto'], Crypt::decryptString($a->fresh()->secreto_firma));
        $this->assertStringNotContainsString($r['secreto'], (string) $a->fresh()->getRawOriginal('secreto_firma'));
        $this->send($r['token'], $this->body(), $r['secreto'])->assertOk()->assertJson(['guardadas' => 1]);
    }

    public function test_bad_signature_returns_401(): void
    {
        config(['mision.reloj_exigir_firma' => true]);
        $a = $this->crewMember();
        $r = app(TokenReloj::class)->emitir($a);

        $this->send($r['token'], $this->body(), 'otro-secreto')->assertStatus(401)->assertJsonFragment(['mensaje' => 'Invalid signature.']);
        // Body altered after signing.
        $timestamp = time();
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.json_encode($this->body()), $r['secreto']);
        $this->send($r['token'], $this->body(['bateria' => 50]), null, $timestamp, $signature)->assertStatus(401);
    }

    public function test_stale_timestamp_returns_401(): void
    {
        config(['mision.reloj_exigir_firma' => true]);
        $a = $this->crewMember();
        $r = app(TokenReloj::class)->emitir($a);

        $this->send($r['token'], $this->body(), $r['secreto'], time() - 600)->assertStatus(401)->assertJsonFragment(['mensaje' => 'Timestamp is stale or out of range.']);
    }

    public function test_unsigned_batch_returns_401_when_signature_required(): void
    {
        config(['mision.reloj_exigir_firma' => true]);
        $a = $this->crewMember();
        $r = app(TokenReloj::class)->emitir($a);

        $this->send($r['token'], $this->body())->assertStatus(401);
    }

    public function test_unsigned_batch_accepted_when_signature_not_required(): void
    {
        config(['mision.reloj_exigir_firma' => false]);
        $a = $this->crewMember();
        $r = app(TokenReloj::class)->emitir($a);

        $this->send($r['token'], $this->body())->assertOk()->assertJson(['guardadas' => 1]);
    }

    public function test_saves_battery_and_profile_warns_when_low(): void
    {
        $a = $this->crewMember();
        $r = app(TokenReloj::class)->emitir($a);

        $this->send($r['token'], $this->body(['bateria' => 12]))->assertOk();
        $a->refresh();
        $this->assertSame(12, $a->bateria_pct);
        $this->assertNotNull($a->bateria_en);

        $this->send($r['token'], $this->body(['bateria' => 150]))->assertStatus(422);
        $this->assertSame(12, $a->fresh()->bateria_pct);

        $m = \App\Models\Mision::create(['nombre' => 'Test Bat '.uniqid(), 'entorno' => 'nave', 'inicio_en' => now()->toDateString(), 'duracion_dias' => 100]);
        $b = app(\App\Services\Tripulacion\AltaTripulante::class)->registrar([
            'nombre' => 'Low Battery', 'fecha_nacimiento' => now()->subYears(35)->toDateString(), 'sexo' => 'femenino',
            'rol' => 'Engineer', 'agencia' => 'AEM', 'nacionalidad' => 'Mexican', 'altura_m' => 1.66, 'peso_kg' => 60, 'grupo_sanguineo' => 'O-',
        ], $m);
        session(['mision_id' => $m->id]);
        $b->forceFill(['bateria_pct' => 12, 'bateria_en' => now()])->save();
        \Livewire\Livewire::test(\App\Livewire\FichaTripulante::class, ['astronauta' => $b])->assertSee('Low battery');
        $b->forceFill(['bateria_pct' => 80])->save();
        \Livewire\Livewire::test(\App\Livewire\FichaTripulante::class, ['astronauta' => $b])->assertDontSee('Low battery');
    }
}
