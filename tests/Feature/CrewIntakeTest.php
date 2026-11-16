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

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class AltaTripulanteTest extends TestCase
{
    use DatabaseTransactions;

    private function mision(): Mision
    {
        return Mision::create(['nombre' => 'Test Alta '.uniqid(), 'entorno' => 'nave', 'inicio_en' => now()->toDateString(), 'duracion_dias' => 100]);
    }

    public function test_no_admite_mas_de_cinco_tripulantes_por_mision(): void
    {
        $m = $this->mision();
        $svc = app(AltaTripulante::class);
        for ($i = 1; $i <= 5; $i++) {
            $svc->registrar($this->minimo(['nombre' => 'Tripulante '.$i]), $m);
        }
        $this->assertTrue(AltaTripulante::cupoLleno($m));

        try {
            $svc->registrar($this->minimo(['nombre' => 'Sexto']), $m);
            $this->fail('Debió rechazar al sexto tripulante.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('nombre', $e->errors());
        }
        $this->assertSame(5, \App\Models\Astronauta::where('mision_id', $m->id)->count());
    }

    private function minimo(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Ana Prueba',
            'fecha_nacimiento' => now()->subYears(35)->subDays(10)->toDateString(),
            'sexo' => 'femenino',
        ], $extra);
    }

    private function completo(array $extra = []): array
    {
        return $this->minimo(array_merge([
            'nacionalidad' => 'Mexicana', 'agencia' => 'AEM', 'rol' => 'Oficial médico',
            'fecha_ingreso' => '2020-01-01', 'estado_servicio' => 'reserva',
            'altura_m' => 1.70, 'peso_kg' => 65, 'grupo_sanguineo' => 'O+', 'evas' => 3,
            'fc_reposo' => 58, 'fc_maxima' => 190, 'presion_basal_sis' => 112, 'presion_basal_dia' => 72,
            'spo2_basal' => 98, 'temperatura_basal' => 36.6, 'glucosa_basal' => 90, 'vo2max' => 48.5,
            'grasa_corporal_pct' => 20.5, 'densidad_osea_tscore' => 0.5, 'horas_sueno_habituales' => 7.5,
            'fumador' => false, 'alcohol' => 'ocasional', 'actividad_fisica_semana_min' => 300,
            'dosis_radiacion_previa_msv' => 12.5,
            'linea_base' => ['presion_arterial' => 112, 'frecuencia_cardiaca' => 58, 'glucosa' => 90],
            'alergias' => [['sustancia' => 'Penicilina', 'reaccion' => 'Urticaria', 'gravedad' => 'moderada']],
            'condiciones' => [['nombre' => 'Asma leve', 'estado' => 'controlada', 'desde' => '2010-05-01', 'notas' => null]],
            'medicamentos' => [['nombre' => 'Vitamina D', 'dosis_texto' => '1000 UI', 'frecuencia' => 'diaria', 'motivo' => 'suplemento', 'desde' => '2022-01-01', 'activo' => true]],
            'antecedentes' => [['parentesco' => 'padre', 'condicion' => 'Hipertensión', 'edad_aparicion' => 55]],
            'aptitud_vuelo' => 'apto_restricciones', 'restricciones' => 'Sin EVA prolongada',
            'ultimo_examen_en' => now()->subMonth()->toDateString(), 'notas' => 'Nota',
            'contactos' => [
                ['nombre' => 'Luis', 'parentesco' => 'pareja', 'telefono' => '+52 55 1234 5678', 'principal' => true],
                ['nombre' => 'Rosa', 'parentesco' => 'madre', 'telefono' => '555-123-456', 'principal' => false],
            ],
        ], $extra));
    }

    public function test_alta_completa_persiste_todo(): void
    {
        $m = $this->mision();
        $a = app(AltaTripulante::class)->registrar($this->completo(), $m);

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

    public function test_alta_minima_usa_defaults(): void
    {
        $a = app(AltaTripulante::class)->registrar($this->minimo(), $this->mision());

        $this->assertSame('activo', $a->estado_servicio);
        $this->assertSame(0, (int) $a->evas);
        $this->assertNotNull($a->token_dispositivo);
        $this->assertSame('pendiente', $a->expediente->aptitud_vuelo);
        $this->assertFalse($a->expediente->fumador);
        $this->assertNull($a->expediente->linea_base);
        $this->assertCount(0, $a->contactos);
    }

    public function test_menor_de_edad_falla(): void
    {
        try {
            app(AltaTripulante::class)->registrar($this->minimo(['fecha_nacimiento' => now()->subYears(17)->toDateString()]), $this->mision());
            $this->fail('Debia fallar');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fecha_nacimiento', $e->errors());
            $this->assertStringContainsString('18', $e->errors()['fecha_nacimiento'][0]);
        }
    }

    public function test_mayor_de_80_falla(): void
    {
        $this->expectException(ValidationException::class);
        app(AltaTripulante::class)->registrar($this->minimo(['fecha_nacimiento' => now()->subYears(82)->toDateString()]), $this->mision());
    }

    public function test_linea_base_con_clave_desconocida_falla(): void
    {
        try {
            app(AltaTripulante::class)->registrar($this->minimo(['linea_base' => ['inventado' => 5]]), $this->mision());
            $this->fail('Debia fallar');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('linea_base', $e->errors());
        }
    }

    public function test_contactos_sin_principal_falla(): void
    {
        try {
            app(AltaTripulante::class)->registrar($this->minimo(['contactos' => [
                ['nombre' => 'Luis', 'parentesco' => 'pareja', 'telefono' => '5551234567', 'principal' => false],
            ]]), $this->mision());
            $this->fail('Debia fallar');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('contactos', $e->errors());
        }
    }

    public function test_altura_fuera_de_rango_falla(): void
    {
        foreach ([1.0, 2.5] as $h) {
            try {
                app(AltaTripulante::class)->registrar($this->minimo(['altura_m' => $h]), $this->mision());
                $this->fail('Debia fallar');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('altura_m', $e->errors());
                $this->assertStringContainsString('1.2', $e->errors()['altura_m'][0]);
            }
        }
    }

    public function test_rollback_si_falla_a_mitad(): void
    {
        $antes = Astronauta::count();
        ContactoEmergencia::creating(fn () => throw new RuntimeException('boom'));

        try {
            app(AltaTripulante::class)->registrar($this->completo(), $this->mision());
            $this->fail('Debia fallar');
        } catch (RuntimeException) {
        }

        ContactoEmergencia::flushEventListeners();
        $this->assertSame($antes, Astronauta::count());
        $this->assertSame(0, Astronauta::where('nombre', 'Ana Prueba')->count());
    }

    public function test_actualizar_reemplaza_hijos_y_conserva_ausentes(): void
    {
        $svc = app(AltaTripulante::class);
        $a = $svc->registrar($this->completo(), $this->mision());

        $a = $svc->actualizar($a, [
            'peso_kg' => 70,
            'fecha_nacimiento' => now()->subYears(40)->subDay()->toDateString(),
            'alergias' => [
                ['sustancia' => 'Polen', 'gravedad' => 'leve'],
                ['sustancia' => 'Nueces', 'gravedad' => 'grave'],
            ],
            'contactos' => [],
            'aptitud_vuelo' => 'apto',
        ]);

        $this->assertSame(70.0, $a->peso_kg);
        $this->assertSame(40, $a->edad);
        $this->assertSame('apto', $a->expediente->aptitud_vuelo);
        $this->assertSame(['Polen', 'Nueces'], $a->alergias->pluck('sustancia')->all());
        $this->assertCount(0, $a->contactos);
        $this->assertCount(1, $a->condiciones, 'lo ausente no se toca');
        $this->assertSame('Ana Prueba', $a->nombre);
        $this->assertSame(1, $a->expediente()->count());
    }

    public function test_actualizar_valida(): void
    {
        $svc = app(AltaTripulante::class);
        $a = $svc->registrar($this->minimo(), $this->mision());

        $this->expectException(ValidationException::class);
        $svc->actualizar($a, ['peso_kg' => 500]);
    }

    public function test_completitud(): void
    {
        $svc = app(AltaTripulante::class);
        $m = $this->mision();

        $min = $svc->registrar($this->minimo(), $m);
        $c = $svc->completitud($min);
        $this->assertLessThan(30, $c['porcentaje']);
        $this->assertContains('Línea base personal', $c['faltantes']);
        $this->assertContains('Contacto de emergencia principal', $c['faltantes']);

        $full = $svc->registrar($this->completo(['nombre' => 'Bruno']), $m);
        $c = $svc->completitud($full);
        $this->assertSame(100, $c['porcentaje']);
        $this->assertSame([], $c['faltantes']);
    }

    public function test_imc_y_edad_calculada(): void
    {
        $a = new Astronauta(['altura_m' => 1.80, 'peso_kg' => 81, 'edad' => 29]);
        $this->assertSame(25.0, $a->imc());
        $this->assertSame(29, $a->edadCalculada(), 'sin fecha usa edad');

        $a->fecha_nacimiento = now()->subYears(33)->subDays(5);
        $this->assertSame(33, $a->edadCalculada());

        $this->assertNull((new Astronauta)->imc());
        $this->assertNull((new Astronauta)->edadCalculada());
    }

    public function test_reglas_por_paso(): void
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
