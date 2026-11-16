<?php

namespace Tests\Feature;

use App\Contracts\Orientador;
use App\Models\Alergia;
use App\Models\AntecedenteFamiliar;
use App\Models\Astronauta;
use App\Models\AuditoriaAcceso;
use App\Models\CasoClinico;
use App\Models\CondicionMedica;
use App\Models\ConsultaTierra;
use App\Models\MedicamentoTripulante;
use App\Models\Mision;
use App\Models\OrientacionFeedback;
use App\Models\User;
use App\Services\Ia\FiltroSeguridad;
use App\Services\Ia\Orientacion;
use App\Services\Ia\OrientadorProximamente;
use App\Services\Ia\OrientadorSimulado;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class OrientadorSimuladoTest extends TestCase
{
    use DatabaseTransactions;

    private const PATRONES = ['fiebre', 'hipoxia', 'deshidratacion', 'estres', 'arritmia', 'falta_sueno', 'fallo_sensor'];

    private function astronauta(string $nombre = 'Ana OS'): Astronauta
    {
        $m = Mision::create(['nombre' => 'Misión OS '.$nombre, 'entorno' => 'estacion', 'inicio_en' => now()->subDays(10), 'duracion_dias' => 30]);

        return Astronauta::create(['mision_id' => $m->id, 'nombre' => $nombre, 'mision' => 'OS', 'entorno' => 'estacion']);
    }

    private function caso(Astronauta $a, string $patron = 'hipoxia', string $sev = 'precaucion'): CasoClinico
    {
        return CasoClinico::create(['astronauta_id' => $a->id, 'patron' => $patron, 'severidad' => $sev, 'evidencia' => [], 'estado' => 'abierto', 'detectado_en' => now()]);
    }

    private function orientar(CasoClinico $caso): Orientacion
    {
        return app(Orientador::class)->orientar($caso);
    }

    private function indicadoresDe(string $patron): array
    {
        $out = [];
        foreach ((array) config("patrones.$patron.indicadores", []) as $k => $v) {
            $out[] = is_string($k) ? $k : (string) (is_array($v) ? ($v['indicador'] ?? '') : $v);
        }

        return $out;
    }

    public static function patrones(): array
    {
        return array_combine(self::PATRONES, array_map(fn ($p) => [$p], self::PATRONES));
    }

    #[DataProvider('patrones')]
    public function test_cada_patron_devuelve_una_orientacion_coherente(string $patron): void
    {
        $a = $this->astronauta();
        $o = $this->orientar($this->caso($a, $patron, 'critico'));

        $this->assertSame($patron, $o->patron);
        $this->assertSame($a->id, $o->astronautaId);
        $this->assertSame('critico', $o->severidad);
        $this->assertSame('simulado', $o->origen);
        $this->assertSame('pendiente', $o->revisionMedica);
        $this->assertSame((int) config("patrones.$patron.confianza"), $o->confianza);
        $this->assertNotSame('', $o->titulo);
        $this->assertStringContainsString('Hipótesis', $o->hallazgo);
        $this->assertStringContainsString('No es un diagnóstico', $o->hallazgo);
        $this->assertNotEmpty($o->acciones);
        $this->assertStringContainsString('médico de vuelo', implode(' ', array_column($o->acciones, 'texto')));
        $this->assertNull((new FiltroSeguridad())->revisarTodos(array_merge([$o->titulo, $o->hallazgo], array_column($o->acciones, 'texto'))));

        foreach ($o->historial7d as $h) {
            $this->assertCount(7, $h['serie']);
            $this->assertArrayHasKey('actual', $h);
            $this->assertArrayHasKey('base', $h);
        }
        $esperados = array_intersect($this->indicadoresDe($patron), array_keys(config('indicadores.marcadores')));
        $this->assertCount(count($esperados), $o->historial7d, 'una serie diaria por indicador del patrón');
    }

    public function test_el_texto_no_usa_lenguaje_de_diagnostico_en_ningun_patron(): void
    {
        $a = $this->astronauta();
        foreach (self::PATRONES as $p) {
            $o = $this->orientar($this->caso($a, $p));
            $todo = mb_strtolower($o->titulo.' '.$o->hallazgo.' '.implode(' ', array_column($o->acciones, 'texto')));
            $this->assertDoesNotMatchRegularExpression('/\b(tienes|padeces|sufres)\b/u', $todo, $p);
            $this->assertStringNotContainsString('diagnóstico:', $todo, $p);
        }
    }

    public function test_las_acciones_de_la_guia_llevan_su_fuente(): void
    {
        $o = $this->orientar($this->caso($this->astronauta(), 'hipoxia'));

        $guia = array_filter($o->acciones, fn ($x) => ! str_starts_with($x['texto'], 'Pendiente de fuente') && $x['texto'] !== OrientadorSimulado::CONFIRMAR);
        $this->assertNotEmpty($guia);
        foreach ($guia as $accion) {
            $this->assertIsArray($accion['fuente'], $accion['texto']);
            $this->assertNotSame('', (string) ($accion['fuente']['etiqueta'] ?? ''));
        }
        $this->assertNotEmpty($o->fuentes);
    }

    public function test_sin_antecedentes_ni_tratamientos_lo_dice(): void
    {
        $o = $this->orientar($this->caso($this->astronauta(), 'hipoxia'));

        $this->assertSame([OrientadorSimulado::SIN_ANTECEDENTES], $o->expedienteRelevante);
        $this->assertSame([OrientadorSimulado::SIN_TRATAMIENTOS], $o->tratamientos);
    }

    public function test_muestra_solo_el_expediente_pertinente_y_audita_el_acceso(): void
    {
        $a = $this->astronauta();
        CondicionMedica::create(['astronauta_id' => $a->id, 'nombre' => 'Asma leve', 'estado' => 'controlada']);
        CondicionMedica::create(['astronauta_id' => $a->id, 'nombre' => 'Migraña', 'estado' => 'activa']);
        AntecedenteFamiliar::create(['astronauta_id' => $a->id, 'parentesco' => 'madre', 'condicion' => 'EPOC']);
        Alergia::create(['astronauta_id' => $a->id, 'sustancia' => 'Polen', 'reaccion' => 'rinitis', 'gravedad' => 'leve']);

        $o = $this->orientar($this->caso($a, 'hipoxia'));
        $texto = implode(' | ', $o->expedienteRelevante);

        $this->assertStringContainsString('Asma leve', $texto);
        $this->assertStringContainsString('EPOC', $texto);
        $this->assertStringNotContainsString('Migraña', $texto);
        $this->assertStringNotContainsString('Polen', $texto);
        $this->assertTrue(AuditoriaAcceso::where('accion', 'ver')->where('recurso', 'expediente')->where('astronauta_id', $a->id)->exists());
    }

    public function test_no_muestra_datos_de_otro_tripulante(): void
    {
        $a = $this->astronauta('Ana OS');
        $b = $this->astronauta('Beto OS');
        CondicionMedica::create(['astronauta_id' => $b->id, 'nombre' => 'Asma de Beto', 'estado' => 'activa']);
        MedicamentoTripulante::create(['astronauta_id' => $b->id, 'nombre' => 'Salbutamol de Beto', 'activo' => true]);
        ConsultaTierra::create(['astronauta_id' => $b->id, 'tipo' => 'msg', 'titulo' => 'Consulta de Beto', 'indicadores' => ['saturacion_o2']]);
        OrientacionFeedback::create(['astronauta_id' => $b->id, 'indicador' => 'saturacion_o2', 'voto' => 'up']);

        $o = $this->orientar($this->caso($a, 'hipoxia'));
        $todo = json_encode([$o->expedienteRelevante, $o->tratamientos, $o->hallazgo], JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('Beto', $todo);
        $this->assertSame([OrientadorSimulado::SIN_ANTECEDENTES], $o->expedienteRelevante);
        $this->assertSame([OrientadorSimulado::SIN_TRATAMIENTOS], $o->tratamientos);
    }

    public function test_la_medicacion_registrada_es_contexto_etiquetado_y_no_activa_el_filtro(): void
    {
        $a = $this->astronauta();
        MedicamentoTripulante::create(['astronauta_id' => $a->id, 'nombre' => 'Ibuprofeno', 'dosis_texto' => '400 mg', 'motivo' => 'dolor lumbar', 'activo' => true]);
        MedicamentoTripulante::create(['astronauta_id' => $a->id, 'nombre' => 'Antiguo', 'activo' => false]);

        $o = $this->orientar($this->caso($a, 'fiebre'));

        $this->assertSame(['Medicación registrada: Ibuprofeno (motivo: dolor lumbar)'], $o->tratamientos);
        $this->assertStringNotContainsString('400', implode(' ', $o->tratamientos), 'la dosis registrada no se muestra');
        $this->assertStringNotContainsString('Antiguo', implode(' ', $o->tratamientos));
        $this->assertStringNotContainsString('Aviso', $o->hallazgo, 'no cayó al texto del catálogo');
        $this->assertNull((new FiltroSeguridad())->revisar($o->tratamientos[0]));
    }

    public function test_el_historial_de_tratamientos_matiza_el_texto_sin_recomendar_farmacos(): void
    {
        $a = $this->astronauta();
        ConsultaTierra::create(['astronauta_id' => $a->id, 'tipo' => 'msg', 'titulo' => 'Saturación baja', 'indicadores' => ['saturacion_o2'], 'etapa' => 3, 'respuesta' => 'ok']);
        OrientacionFeedback::create(['astronauta_id' => $a->id, 'indicador' => 'saturacion_o2', 'voto' => 'up']);

        $o = $this->orientar($this->caso($a, 'hipoxia'));

        $this->assertStringContainsString('avisaste a tierra', $o->hallazgo);
        $this->assertStringContainsString('te ayudó', $o->hallazgo);
        $this->assertStringContainsString('Consulta a tierra «Saturación baja»', implode(' | ', $o->tratamientos));
        $this->assertStringContainsString('útil', implode(' | ', $o->tratamientos));
    }

    public function test_con_login_activo_no_muestra_el_expediente_de_quien_no_es_visible(): void
    {
        config(['mision.exigir_login' => true]);
        $a = $this->astronauta('Ana OS');
        $b = $this->astronauta('Beto OS');
        $user = User::create(['name' => 'Beto', 'email' => 'beto-os@prueba.test', 'password' => 'clave-de-prueba-123', 'rol' => 'tripulante']);
        $b->forceFill(['user_id' => $user->id])->save();
        CondicionMedica::create(['astronauta_id' => $a->id, 'nombre' => 'Asma de Ana', 'estado' => 'activa']);
        MedicamentoTripulante::create(['astronauta_id' => $a->id, 'nombre' => 'Medicina de Ana', 'activo' => true]);

        $this->actingAs($user);
        $o = $this->orientar($this->caso($a, 'hipoxia'));

        $this->assertSame([OrientadorSimulado::SIN_ACCESO], $o->expedienteRelevante);
        $this->assertStringNotContainsString('Medicina de Ana', implode(' ', $o->tratamientos));
        $this->assertFalse(AuditoriaAcceso::where('recurso', 'expediente')->where('astronauta_id', $a->id)->exists());
    }

    public function test_si_el_filtro_rechaza_devuelve_el_texto_del_catalogo_con_el_aviso(): void
    {
        $this->app->bind(FiltroSeguridad::class, fn () => new class extends FiltroSeguridad {
            public function revisarTodos(array $textos): ?string
            {
                return self::DOSIS;
            }
        });

        $o = $this->orientar($this->caso($this->astronauta(), 'hipoxia'));

        $this->assertStringContainsString('texto por reglas del catálogo', $o->hallazgo);
        $this->assertStringContainsString((string) config('ia.aviso'), $o->hallazgo);
        $fuentes = $o->fuentes;
        $this->assertSame('Aviso', end($fuentes)['etiqueta']);
        $acciones = $o->acciones;
        $this->assertSame(OrientadorSimulado::CONFIRMAR, end($acciones)['texto']);
    }

    public function test_el_binding_elige_la_implementacion_por_config(): void
    {
        config(['ia.modo' => 'simulado']);
        $this->assertInstanceOf(OrientadorSimulado::class, app(Orientador::class));

        config(['ia.modo' => 'ia_local']);
        $ia = app(Orientador::class);
        $this->assertInstanceOf(OrientadorProximamente::class, $ia);

        try {
            $ia->orientar($this->caso($this->astronauta(), 'hipoxia'));
            $this->fail('Debía lanzar RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame('Próximamente', $e->getMessage());
        }

        config(['ia.modo' => 'otro']);
        $this->assertInstanceOf(OrientadorSimulado::class, app(Orientador::class), 'modo desconocido = simulado');
    }

    public function test_config_ia_tiene_el_aviso_fijo_y_la_etiqueta(): void
    {
        $this->assertSame('Esta es una versión simulada de una IA local. Puede cometer errores. Todo es simulado, a partir de la simulación de los tripulantes.', config('ia.aviso'));
        $this->assertSame('IA local y n8n: Próximamente', config('ia.etiquetas.proximamente'));
    }
}
