<?php

namespace Tests\Feature;

use App\Models\Astronauta;
use App\Models\ContactoEmergencia;
use App\Models\Mision;
use App\Services\Tripulacion\AltaTripulante;
use App\Services\Tripulacion\ReglasExpediente;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never deletes the local database data. */
class CrewIntakeTest extends TestCase
{
    use DatabaseTransactions;

    private function mission(): Mision
    {
        return Mision::create(['nombre' => 'Test Intake '.uniqid(), 'entorno' => 'nave', 'inicio_en' => now()->toDateString(), 'duracion_dias' => 100]);
    }

    public function test_rejects_more_than_five_crew_per_mission(): void
    {
        $m = $this->mission();
        $svc = app(AltaTripulante::class);
        for ($i = 1; $i <= 5; $i++) {
            $svc->registrar($this->minimal(['nombre' => 'Crew member '.$i]), $m);
        }
        $this->assertTrue(AltaTripulante::cupoLleno($m));

        try {
            $svc->registrar($this->minimal(['nombre' => 'Sixth']), $m);
            $this->fail('Should have rejected the sixth crew member.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('nombre', $e->errors());
        }
        $this->assertSame(5, \App\Models\Astronauta::where('mision_id', $m->id)->count());
    }

    private function minimal(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Ana Test',
            'fecha_nacimiento' => now()->subYears(35)->subDays(10)->toDateString(),
            'sexo' => 'femenino',
        ], $extra);
    }

    private function complete(array $extra = []): array
    {
        return $this->minimal(array_merge([
            'nacionalidad' => 'Mexican', 'agencia' => 'AEM', 'rol' => 'Medical officer',
            'fecha_ingreso' => '2020-01-01', 'estado_servicio' => 'reserva',
            'altura_m' => 1.70, 'peso_kg' => 65, 'grupo_sanguineo' => 'O+', 'evas' => 3,
            'fc_reposo' => 58, 'fc_maxima' => 190, 'presion_basal_sis' => 112, 'presion_basal_dia' => 72,
            'spo2_basal' => 98, 'temperatura_basal' => 36.6, 'glucosa_basal' => 90, 'vo2max' => 48.5,
            'grasa_corporal_pct' => 20.5, 'densidad_osea_tscore' => 0.5, 'horas_sueno_habituales' => 7.5,
            'fumador' => false, 'alcohol' => 'ocasional', 'actividad_fisica_semana_min' => 300,
            'dosis_radiacion_previa_msv' => 12.5,
            'linea_base' => ['presion_arterial' => 112, 'frecuencia_cardiaca' => 58, 'glucosa' => 90],
            'alergias' => [['sustancia' => 'Penicillin', 'reaccion' => 'Hives', 'gravedad' => 'moderada']],
            'condiciones' => [['nombre' => 'Mild asthma', 'estado' => 'controlada', 'desde' => '2010-05-01', 'notas' => null]],
            'medicamentos' => [['nombre' => 'Vitamina D', 'dosis_texto' => '1000 IU', 'frecuencia' => 'diaria', 'motivo' => 'supplement', 'desde' => '2022-01-01', 'activo' => true]],
            'antecedentes' => [['parentesco' => 'padre', 'condicion' => 'Hypertension', 'edad_aparicion' => 55]],
            'aptitud_vuelo' => 'apto_restricciones', 'restricciones' => 'No prolonged EVA',
            'ultimo_examen_en' => now()->subMonth()->toDateString(), 'notas' => 'Note',
            'contactos' => [
                ['nombre' => 'Luis', 'parentesco' => 'pareja', 'telefono' => '+52 55 1234 5678', 'principal' => true],
                ['nombre' => 'Rosa', 'parentesco' => 'madre', 'telefono' => '555-123-456', 'principal' => false],
            ],
        ], $extra));
    }

    public function test_full_intake_persists_everything(): void
    {
        $m = $this->mission();
        $a = app(AltaTripulante::class)->registrar($this->complete(), $m);

        $this->assertSame($m->id, $a->mision_id);
        $this->assertSame($m->nombre, $a->mision);
        $this->assertSame('nave', $a->entorno);
        $this->assertSame(35, $a->edad);
        $this->assertSame('reserva', $a->estado_servicio);
        $this->assertSame(3, (int) $a->evas);
        $this->assertSame(64, strlen($a->token_dispositivo));
        $this->assertSame(hash('sha256', $a->tokenPlano), $a->token_dispositivo);
        $this->assertSame('apto_restricciones', $a->expediente->aptitud_vuelo);
        $this->assertEquals(['presion_arterial' => 112, 'frecuencia_cardiaca' => 58, 'glucosa' => 90], $a->expediente->linea_base);
        $this->assertSame(36.6, $a->expediente->temperatura_basal);
        $this->assertCount(1, $a->alergias);
        $this->assertCount(1, $a->condiciones);
        $this->assertCount(1, $a->medicamentos);
        $this->assertCount(1, $a->antecedentes);
        $this->assertCount(2, $a->contactos);
        $this->assertTrue($a->contactos->firstWhere('nombre', 'Luis')->principal);
        $this->assertSame(1, $m->tripulantes()->count());
    }

    public function test_minimal_intake_uses_defaults(): void
    {
        $a = app(AltaTripulante::class)->registrar($this->minimal(), $this->mission());

        $this->assertSame('activo', $a->estado_servicio);
        $this->assertSame(0, (int) $a->evas);
        $this->assertNotNull($a->token_dispositivo);
        $this->assertSame('pendiente', $a->expediente->aptitud_vuelo);
        $this->assertFalse($a->expediente->fumador);
        $this->assertNull($a->expediente->linea_base);
        $this->assertCount(0, $a->contactos);
    }

    public function test_underage_fails(): void
    {
        try {
            app(AltaTripulante::class)->registrar($this->minimal(['fecha_nacimiento' => now()->subYears(17)->toDateString()]), $this->mission());
            $this->fail('Should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fecha_nacimiento', $e->errors());
            $this->assertStringContainsString('18', $e->errors()['fecha_nacimiento'][0]);
        }
    }

    public function test_over_80_fails(): void
    {
        $this->expectException(ValidationException::class);
        app(AltaTripulante::class)->registrar($this->minimal(['fecha_nacimiento' => now()->subYears(82)->toDateString()]), $this->mission());
    }

    public function test_baseline_with_unknown_key_fails(): void
    {
        try {
            app(AltaTripulante::class)->registrar($this->minimal(['linea_base' => ['inventado' => 5]]), $this->mission());
            $this->fail('Should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('linea_base', $e->errors());
        }
    }

    public function test_contacts_without_primary_fail(): void
    {
        try {
            app(AltaTripulante::class)->registrar($this->minimal(['contactos' => [
                ['nombre' => 'Luis', 'parentesco' => 'pareja', 'telefono' => '5551234567', 'principal' => false],
            ]]), $this->mission());
            $this->fail('Should have failed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('contactos', $e->errors());
        }
    }

    public function test_height_out_of_range_fails(): void
    {
        foreach ([1.0, 2.5] as $h) {
            try {
                app(AltaTripulante::class)->registrar($this->minimal(['altura_m' => $h]), $this->mission());
                $this->fail('Should have failed');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('altura_m', $e->errors());
                $this->assertStringContainsString('1.2', $e->errors()['altura_m'][0]);
            }
        }
    }

    public function test_rolls_back_if_it_fails_midway(): void
    {
        $before = Astronauta::count();
        ContactoEmergencia::creating(fn () => throw new RuntimeException('boom'));

        try {
            app(AltaTripulante::class)->registrar($this->complete(), $this->mission());
            $this->fail('Should have failed');
        } catch (RuntimeException) {
        }

        ContactoEmergencia::flushEventListeners();
        $this->assertSame($before, Astronauta::count());
        $this->assertSame(0, Astronauta::where('nombre', 'Ana Test')->count());
    }

    public function test_update_replaces_children_and_keeps_absent(): void
    {
        $svc = app(AltaTripulante::class);
        $a = $svc->registrar($this->complete(), $this->mission());

        $a = $svc->actualizar($a, [
            'peso_kg' => 70,
            'fecha_nacimiento' => now()->subYears(40)->subDay()->toDateString(),
            'alergias' => [
                ['sustancia' => 'Pollen', 'gravedad' => 'leve'],
                ['sustancia' => 'Nuts', 'gravedad' => 'grave'],
            ],
            'contactos' => [],
            'aptitud_vuelo' => 'apto',
        ]);

        $this->assertSame(70.0, $a->peso_kg);
        $this->assertSame(40, $a->edad);
        $this->assertSame('apto', $a->expediente->aptitud_vuelo);
        $this->assertSame(['Pollen', 'Nuts'], $a->alergias->pluck('sustancia')->all());
        $this->assertCount(0, $a->contactos);
        $this->assertCount(1, $a->condiciones, 'absent fields are untouched');
        $this->assertSame('Ana Test', $a->nombre);
        $this->assertSame(1, $a->expediente()->count());
    }

    public function test_update_validates(): void
    {
        $svc = app(AltaTripulante::class);
        $a = $svc->registrar($this->minimal(), $this->mission());

        $this->expectException(ValidationException::class);
        $svc->actualizar($a, ['peso_kg' => 500]);
    }

    public function test_completeness(): void
    {
        $svc = app(AltaTripulante::class);
        $m = $this->mission();

        $min = $svc->registrar($this->minimal(), $m);
        $c = $svc->completitud($min);
        $this->assertLessThan(30, $c['porcentaje']);
        $this->assertContains('Personal baseline', $c['faltantes']);
        $this->assertContains('Primary emergency contact', $c['faltantes']);

        $full = $svc->registrar($this->complete(['nombre' => 'Bruno']), $m);
        $c = $svc->completitud($full);
        $this->assertSame(100, $c['porcentaje']);
        $this->assertSame([], $c['faltantes']);
    }

    public function test_bmi_and_calculated_age(): void
    {
        $a = new Astronauta(['altura_m' => 1.80, 'peso_kg' => 81, 'edad' => 29]);
        $this->assertSame(25.0, $a->imc());
        $this->assertSame(29, $a->edadCalculada(), 'without a date it uses age');

        $a->fecha_nacimiento = now()->subYears(33)->subDays(5);
        $this->assertSame(33, $a->edadCalculada());

        $this->assertNull((new Astronauta)->imc());
        $this->assertNull((new Astronauta)->edadCalculada());
    }

    public function test_rules_per_step(): void
    {
        $this->assertArrayHasKey('nombre', ReglasExpediente::reglasPaso(1));
        $this->assertArrayNotHasKey('altura_m', ReglasExpediente::reglasPaso(1));
        $this->assertArrayHasKey('linea_base.*', ReglasExpediente::reglasPaso(2));
        $this->assertArrayHasKey('alergias.*.sustancia', ReglasExpediente::reglasPaso(3));
        $this->assertArrayHasKey('aptitud_vuelo', ReglasExpediente::reglasPaso(3));
        $this->assertSame(
            ['contactos', 'contactos.*.nombre', 'contactos.*.parentesco', 'contactos.*.telefono', 'contactos.*.principal'],
            array_keys(ReglasExpediente::reglasPaso(4))
        );
        $this->assertSame(array_keys(ReglasExpediente::reglas()), array_keys(ReglasExpediente::reglasPaso(5)));
    }
}
