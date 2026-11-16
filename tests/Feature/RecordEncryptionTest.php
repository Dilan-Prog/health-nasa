<?php

namespace Tests\Feature;

use App\Casts\EncryptedFlexible;
use App\Models\Alergia;
use App\Models\Astronauta;
use App\Models\ContactoEmergencia;
use App\Models\ExpedienteMedico;
use App\Models\Mision;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never deletes the local database data. */
class RecordEncryptionTest extends TestCase
{
    use DatabaseTransactions;

    private function crewMember(): Astronauta
    {
        $m = Mision::create(['nombre' => 'Test Encryption '.uniqid(), 'entorno' => 'nave', 'inicio_en' => now()->toDateString(), 'duracion_dias' => 100]);

        return Astronauta::create(['mision_id' => $m->id, 'nombre' => 'Encrypted '.uniqid()]);
    }

    public function test_database_holds_no_plaintext_and_model_returns_clear_text(): void
    {
        $a = $this->crewMember();
        $al = Alergia::create(['astronauta_id' => $a->id, 'sustancia' => 'Penicillin', 'reaccion' => 'Hives', 'gravedad' => 'grave']);
        $c = ContactoEmergencia::create(['astronauta_id' => $a->id, 'nombre' => 'Ana Pérez', 'parentesco' => 'conyuge', 'telefono' => '+34 600 111 222', 'principal' => true]);
        $e = ExpedienteMedico::create(['astronauta_id' => $a->id, 'restricciones' => 'No prolonged EVA', 'notas' => 'Private note']);

        $raw = DB::table('alergias')->where('id', $al->id)->first();
        $this->assertStringNotContainsString('Penicillin', $raw->sustancia);
        $this->assertStringNotContainsString('Hives', $raw->reaccion);
        $this->assertSame('grave', $raw->gravedad); // filter column: not encrypted
        $this->assertStringNotContainsString('600 111', DB::table('contactos_emergencia')->where('id', $c->id)->value('telefono'));
        $this->assertStringNotContainsString('EVA', DB::table('expedientes_medicos')->where('id', $e->id)->value('restricciones'));

        $this->assertSame('Penicillin', Alergia::find($al->id)->sustancia);
        $this->assertSame('+34 600 111 222', ContactoEmergencia::find($c->id)->telefono);
        $this->assertSame('No prolonged EVA', ExpedienteMedico::find($e->id)->restricciones);
        $this->assertSame('Penicillin', $a->fresh()->alergias->first()->sustancia);
    }

    public function test_legacy_plaintext_value_stays_readable_and_null_is_respected(): void
    {
        $a = $this->crewMember();
        $id = DB::table('alergias')->insertGetId([
            'astronauta_id' => $a->id, 'sustancia' => 'Pollen', 'reaccion' => null, 'gravedad' => 'leve',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $al = Alergia::find($id);
        $this->assertSame('Pollen', $al->sustancia);
        $this->assertNull($al->reaccion);
        $this->assertFalse(EncryptedFlexible::estaCifrado('Pollen'));
    }

    public function test_command_simulates_by_default_and_is_idempotent(): void
    {
        $a = $this->crewMember();
        $id = DB::table('alergias')->insertGetId([
            'astronauta_id' => $a->id, 'sustancia' => 'Latex', 'reaccion' => 'Rhinitis', 'gravedad' => 'leve',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $new = Alergia::create(['astronauta_id' => $a->id, 'sustancia' => 'Nut', 'gravedad' => 'leve']);
        $rawNew = DB::table('alergias')->where('id', $new->id)->value('sustancia');

        Artisan::call('expediente:cifrar');
        $this->assertSame('Latex', DB::table('alergias')->where('id', $id)->value('sustancia'), 'Dry run does not write.');

        Artisan::call('expediente:cifrar', ['--confirmar' => true]);
        $encrypted = DB::table('alergias')->where('id', $id)->value('sustancia');
        $this->assertNotSame('Latex', $encrypted);
        $this->assertSame('Latex', Alergia::find($id)->sustancia);
        $this->assertSame($rawNew, DB::table('alergias')->where('id', $new->id)->value('sustancia'), 'Already-encrypted data is untouched.');

        Artisan::call('expediente:cifrar', ['--confirmar' => true]);
        $this->assertSame($encrypted, DB::table('alergias')->where('id', $id)->value('sustancia'), 'Second pass: no double encryption.');
        $this->assertSame('Latex', Alergia::find($id)->sustancia);
    }
}
