<?php

namespace Tests\Feature;

use App\Models\Astronauta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never deletes the local database data. */
class ApiMeasurementsTest extends TestCase
{
    use DatabaseTransactions;

    private function crewWithToken(): array
    {
        $a = Astronauta::create(['nombre' => 'Watch', 'mision' => 'T-1', 'entorno' => 'estacion']);
        $token = 'test-token-'.$a->id;
        $a->forceFill(['token_dispositivo' => hash('sha256', $token)])->save();

        return [$a, $token];
    }

    public function test_missing_or_invalid_token_returns_401(): void
    {
        $body = ['mediciones' => [['indicador' => 'frecuencia_cardiaca', 'valor' => 70, 'medido_en' => now()->toIso8601String()]]];

        $this->postJson('/api/mediciones', $body)->assertStatus(401);
        $this->withToken('fake')->postJson('/api/mediciones', $body)->assertStatus(401);
    }

    public function test_saves_with_watch_source_for_the_token_crew_member(): void
    {
        [$a, $token] = $this->crewWithToken();

        $this->withToken($token)->postJson('/api/mediciones', ['mediciones' => [
            ['indicador' => 'frecuencia_cardiaca', 'valor' => 72, 'medido_en' => now()->subMinutes(10)->toIso8601String()],
            ['indicador' => 'saturacion_o2', 'valor' => 97, 'medido_en' => now()->subMinutes(10)->toIso8601String()],
        ]])->assertOk()->assertJson(['guardadas' => 2, 'duplicadas' => 0, 'rechazadas' => []]);

        $this->assertSame(2, $a->mediciones()->where('origen', 'reloj')->count());
    }

    public function test_resending_same_batch_counts_duplicates_without_error(): void
    {
        [$a, $token] = $this->crewWithToken();
        $body = ['mediciones' => [['indicador' => 'frecuencia_cardiaca', 'valor' => 70, 'medido_en' => now()->subMinutes(5)->toIso8601String()]]];

        $this->withToken($token)->postJson('/api/mediciones', $body)->assertOk()->assertJson(['guardadas' => 1]);
        $this->withToken($token)->postJson('/api/mediciones', $body)->assertOk()->assertJson(['guardadas' => 0, 'duplicadas' => 1]);

        $this->assertSame(1, $a->mediciones()->count());
    }

    public function test_impossible_value_and_future_date_are_rejected_without_failing_the_batch(): void
    {
        [$a, $token] = $this->crewWithToken();

        $r = $this->withToken($token)->postJson('/api/mediciones', ['mediciones' => [
            ['indicador' => 'frecuencia_cardiaca', 'valor' => 999, 'medido_en' => now()->subMinutes(3)->toIso8601String()],
            ['indicador' => 'frecuencia_cardiaca', 'valor' => 70, 'medido_en' => now()->addHour()->toIso8601String()],
            ['indicador' => 'saturacion_o2', 'valor' => 96, 'medido_en' => now()->subMinutes(2)->toIso8601String()],
        ]])->assertOk();

        $r->assertJson(['guardadas' => 1]);
        $this->assertCount(2, $r->json('rechazadas'));
        $this->assertSame(1, $a->mediciones()->count());
    }

    public function test_disallowed_indicator_returns_422(): void
    {
        [, $token] = $this->crewWithToken();

        $this->withToken($token)->postJson('/api/mediciones', ['mediciones' => [
            ['indicador' => 'glucosa', 'valor' => 90, 'medido_en' => now()->toIso8601String()],
        ]])->assertStatus(422);
    }
}
