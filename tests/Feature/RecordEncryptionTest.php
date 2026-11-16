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

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class CifradoExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private function astronauta(): Astronauta
    {
        $m = Mision::create(['nombre' => 'Test Cifrado '.uniqid(), 'entorno' => 'nave', 'inicio_en' => now()->toDateString(), 'duracion_dias' => 100]);

        return Astronauta::create(['mision_id' => $m->id, 'nombre' => 'Cifrada '.uniqid()]);
    }

    public function test_en_la_base_no_es_texto_plano_y_el_modelo_lo_devuelve_en_claro(): void
    {
        $a = $this->astronauta();
        $al = Alergia::create(['astronauta_id' => $a->id, 'sustancia' => 'Penicilina', 'reaccion' => 'Urticaria', 'gravedad' => 'grave']);
        $c = ContactoEmergencia::create(['astronauta_id' => $a->id, 'nombre' => 'Ana Pérez', 'parentesco' => 'conyuge', 'telefono' => '+34 600 111 222', 'principal' => true]);
        $e = ExpedienteMedico::create(['astronauta_id' => $a->id, 'restricciones' => 'Sin EVA prolongada', 'notas' => 'Nota privada']);

        $raw = DB::table('alergias')->where('id', $al->id)->first();
        $this->assertStringNotContainsString('Penicilina', $raw->sustancia);
        $this->assertStringNotContainsString('Urticaria', $raw->reaccion);
        $this->assertSame('grave', $raw->gravedad); // columna de filtro: sin cifrar
        $this->assertStringNotContainsString('600 111', DB::table('contactos_emergencia')->where('id', $c->id)->value('telefono'));
        $this->assertStringNotContainsString('EVA', DB::table('expedientes_medicos')->where('id', $e->id)->value('restricciones'));

        $this->assertSame('Penicilina', Alergia::find($al->id)->sustancia);
        $this->assertSame('+34 600 111 222', ContactoEmergencia::find($c->id)->telefono);
        $this->assertSame('Sin EVA prolongada', ExpedienteMedico::find($e->id)->restricciones);
        $this->assertSame('Penicilina', $a->fresh()->alergias->first()->sustancia);
    }

    public function test_un_dato_antiguo_en_claro_sigue_legible_y_null_se_respeta(): void
    {
        $a = $this->astronauta();
        $id = DB::table('alergias')->insertGetId([
            'astronauta_id' => $a->id, 'sustancia' => 'Polen', 'reaccion' => null, 'gravedad' => 'leve',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $al = Alergia::find($id);
        $this->assertSame('Polen', $al->sustancia);
        $this->assertNull($al->reaccion);
        $this->assertFalse(EncryptedFlexible::estaCifrado('Polen'));
    }

    public function test_el_comando_simula_por_defecto_y_es_idempotente(): void
    {
        $a = $this->astronauta();
        $id = DB::table('alergias')->insertGetId([
            'astronauta_id' => $a->id, 'sustancia' => 'Latex', 'reaccion' => 'Rinitis', 'gravedad' => 'leve',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $nuevo = Alergia::create(['astronauta_id' => $a->id, 'sustancia' => 'Nuez', 'gravedad' => 'leve']);
        $rawNuevo = DB::table('alergias')->where('id', $nuevo->id)->value('sustancia');

        Artisan::call('expediente:cifrar');
        $this->assertSame('Latex', DB::table('alergias')->where('id', $id)->value('sustancia'), 'La simulación no escribe.');

        Artisan::call('expediente:cifrar', ['--confirmar' => true]);
        $cifrado = DB::table('alergias')->where('id', $id)->value('sustancia');
        $this->assertNotSame('Latex', $cifrado);
        $this->assertSame('Latex', Alergia::find($id)->sustancia);
        $this->assertSame($rawNuevo, DB::table('alergias')->where('id', $nuevo->id)->value('sustancia'), 'Lo ya cifrado no se toca.');

        Artisan::call('expediente:cifrar', ['--confirmar' => true]);
        $this->assertSame($cifrado, DB::table('alergias')->where('id', $id)->value('sustancia'), 'Segunda pasada: sin doble cifrado.');
        $this->assertSame('Latex', Alergia::find($id)->sustancia);
    }
}
