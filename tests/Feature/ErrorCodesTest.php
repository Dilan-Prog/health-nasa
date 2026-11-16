<?php

namespace Tests\Feature;

use App\Models\Astronauta;
use App\Services\Salud\CodigoErrorMedicion;
use App\Services\Salud\MedicionInvalidaException;
use App\Services\Salud\ProcesadorMediciones;
use App\Services\Salud\ValidadorMedicion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Validation errors carry stable codes; behavior is decided by code, never by message text. */
class ErrorCodesTest extends TestCase
{
    use DatabaseTransactions;

    private function crew(): array
    {
        $a = Astronauta::create(['nombre' => 'Codes', 'mision' => 'T-1', 'entorno' => 'estacion']);
        $token = 'codes-token-'.$a->id;
        $a->forceFill(['token_dispositivo' => hash('sha256', $token)])->save();

        return [$a, $token];
    }

    public function test_validator_returns_typed_codes_with_english_messages(): void
    {
        $v = new ValidadorMedicion(config('umbrales'));

        $errors = $v->validarConCodigos('temperatura', ['valor' => 80]);
        $this->assertSame(CodigoErrorMedicion::Impossible, $errors[0]['codigo']);
        $this->assertStringContainsString('impossible', $errors[0]['mensaje']);

        $this->assertSame(CodigoErrorMedicion::Missing, $v->validarConCodigos('temperatura', [])[0]['codigo']);
        $this->assertSame(CodigoErrorMedicion::NotNumeric, $v->validarConCodigos('temperatura', ['valor' => 'abc'])[0]['codigo']);
        $this->assertSame(CodigoErrorMedicion::OutOfRange, $v->validarConCodigos('presion_arterial', ['sistolica' => 100, 'diastolica' => 110])[0]['codigo']);
        $this->assertSame(
            array_column($errors, 'mensaje'),
            $v->validar('temperatura', ['valor' => 80]),
            'validar() keeps returning plain messages'
        );
    }

    public function test_processor_throws_exception_with_recoverable_codes_and_same_shape(): void
    {
        [$a] = $this->crew();

        try {
            ProcesadorMediciones::make()->registrar($a, 'temperatura', ['valor' => 80], 'manual');
            $this->fail('Expected a validation exception');
        } catch (MedicionInvalidaException $e) {
            $this->assertTrue($e->tiene(CodigoErrorMedicion::Impossible));
            $this->assertSame([CodigoErrorMedicion::Impossible], $e->codigos());
            $this->assertArrayHasKey('valores', $e->errors());
            $this->assertStringContainsString('impossible', $e->errors()['valores'][0]);
        }
    }

    public function test_duplicate_still_counts_as_duplicate_in_the_api(): void
    {
        [$a, $token] = $this->crew();
        $body = ['mediciones' => [['indicador' => 'frecuencia_cardiaca', 'valor' => 72, 'medido_en' => now()->subMinutes(5)->toIso8601String()]]];

        $this->withToken($token)->postJson('/api/mediciones', $body)->assertOk()->assertJson(['guardadas' => 1, 'duplicadas' => 0]);
        $this->withToken($token)->postJson('/api/mediciones', $body)->assertOk()
            ->assertJson(['guardadas' => 0, 'duplicadas' => 1, 'rechazadas' => []]);

        $this->assertSame(1, $a->mediciones()->count());
    }

    public function test_impossible_value_is_still_rejected_with_english_reason(): void
    {
        [, $token] = $this->crew();

        $res = $this->withToken($token)->postJson('/api/mediciones', ['mediciones' => [
            ['indicador' => 'saturacion_o2', 'valor' => 250, 'medido_en' => now()->subMinutes(5)->toIso8601String()],
        ]])->assertOk()->assertJson(['guardadas' => 0, 'duplicadas' => 0]);

        $this->assertCount(1, $res->json('rechazadas'));
        $this->assertStringContainsString('impossible', $res->json('rechazadas.0.motivo'));
    }

    public function test_behavior_depends_on_code_not_on_message_text(): void
    {
        [$a, $token] = $this->crew();
        $body = ['mediciones' => [['indicador' => 'frecuencia_cardiaca', 'valor' => 70, 'medido_en' => now()->subMinutes(5)->toIso8601String()]]];
        $this->withToken($token)->postJson('/api/mediciones', $body)->assertOk();

        // Even if the validator's wording changes completely, a duplicate is still a duplicate.
        $this->app->bind(ProcesadorMediciones::class, fn () => ProcesadorMediciones::make());
        $mensajeRaro = 'zzz totally different wording zzz';
        try {
            $e = MedicionInvalidaException::desde([['codigo' => CodigoErrorMedicion::Duplicate, 'mensaje' => $mensajeRaro]]);
            throw $e;
        } catch (MedicionInvalidaException $e) {
            $this->assertTrue($e->tiene(CodigoErrorMedicion::Duplicate));
            $this->assertFalse($e->tiene(CodigoErrorMedicion::Impossible));
            $this->assertSame([$mensajeRaro], $e->errors()['valores']);
        }

        // A message that merely contains the old Spanish keywords must NOT be classified by text.
        $falso = MedicionInvalidaException::desde([['codigo' => CodigoErrorMedicion::Missing, 'mensaje' => 'duplicada es imposible']]);
        $this->assertFalse($falso->tiene(CodigoErrorMedicion::Duplicate));
        $this->assertFalse($falso->tiene(CodigoErrorMedicion::Impossible));
    }
}
