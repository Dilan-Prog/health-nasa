<?php

namespace Tests\Feature;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\LineaBase;
use App\Models\Mision;
use App\Models\Requisito;
use App\Models\Rutina;
use App\Models\RutinaEjecucion;
use App\Services\Salud\CatalogoRecomendaciones;
use App\Services\Salud\Estado;
use App\Services\Salud\EvaluadorEstado;
use App\Services\Salud\ProcesadorMediciones;
use App\Services\Salud\ReglasEscalamiento;
use App\Services\Salud\ResolvedorRequisito;
use Database\Seeders\RequisitosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Nivel 2: radiación por entorno, dosis de carrera, presión por bandas, temperatura por salto y care_level.
 * Todos los números son valores de ejemplo (no validados). DatabaseTransactions: no toca otros datos.
 */
class UmbralesNivel2Test extends TestCase
{
    use DatabaseTransactions;

    private const GCR_LIBRE = 'GCR en espacio libre';
    private const GCR_SUPERFICIE = 'GCR en superficie planetaria (sistemas habitables de más de 60 días)';
    private const CARRERA = 'Dosis efectiva de carrera';

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->seed(RequisitosSeeder::class);
        ResolvedorRequisito::olvidar();
    }

    private function astronauta(string $entorno = 'estacion', ?int $care = null): Astronauta
    {
        $mision = $care ? Mision::create(['nombre' => 'M-N2-'.uniqid(), 'entorno' => $entorno, 'care_level' => $care]) : null;

        return Astronauta::create(['nombre' => 'N2', 'mision' => 'T-N2', 'entorno' => $entorno, 'mision_id' => $mision?->id]);
    }

    private function registrar(Astronauta $a, string $clave, array $valores, $instante = null)
    {
        return ProcesadorMediciones::make()->registrar($a, $clave, $valores, 'manual', $instante, false, false);
    }

    private function textoRequisito($evaluacion): ?string
    {
        return $evaluacion->requisito_id ? Requisito::find($evaluacion->requisito_id)->texto : null;
    }

    // ---------------------------------------------------------------- radiación diaria

    public static function radiacionDiaria(): array
    {
        return [
            'estación 1.03 normal' => ['estacion', 1.03, 'normal', self::GCR_LIBRE],
            'estación 1.04 precaución (80 %)' => ['estacion', 1.04, 'precaucion', self::GCR_LIBRE],
            'estación 1.29 precaución' => ['estacion', 1.29, 'precaucion', self::GCR_LIBRE],
            'estación 1.30 crítico (límite)' => ['estacion', 1.30, 'critico', self::GCR_LIBRE],
            'nave 1.00 normal' => ['nave', 1.00, 'normal', self::GCR_LIBRE],
            'nave 1.35 crítico' => ['nave', 1.35, 'critico', self::GCR_LIBRE],
            'base lunar 0.71 normal' => ['base_lunar', 0.71, 'normal', self::GCR_SUPERFICIE],
            'base lunar 0.72 precaución' => ['base_lunar', 0.72, 'precaucion', self::GCR_SUPERFICIE],
            'base lunar 0.90 crítico (límite)' => ['base_lunar', 0.90, 'critico', self::GCR_SUPERFICIE],
            'base marciana 0.95 crítico' => ['base_marciana', 0.95, 'critico', self::GCR_SUPERFICIE],
            // el mismo 1.00 es normal en estación y crítico en superficie
            'base marciana 1.00 crítico' => ['base_marciana', 1.00, 'critico', self::GCR_SUPERFICIE],
        ];
    }

    /** @dataProvider radiacionDiaria */
    public function test_radiacion_diaria_por_entorno(string $entorno, float $valor, string $estado, string $requisito): void
    {
        $ev = $this->registrar($this->astronauta($entorno), 'radiacion_diaria', ['valor' => $valor]);

        $this->assertSame($estado, $ev->estado);
        $this->assertSame($requisito, $this->textoRequisito($ev));
    }

    public function test_alerta_critica_de_radiacion_lleva_requisito_y_revision(): void
    {
        $ev = $this->registrar($this->astronauta('base_marciana'), 'radiacion_diaria', ['valor' => 0.95]);

        $carga = AlertaSaliente::where('evaluacion_id', $ev->id)->firstOrFail()->carga;
        $this->assertSame(self::GCR_SUPERFICIE, $carga['requisito']);
        $this->assertStringContainsString('NASA-STD-3001 Vol. 1 Rev. C', $carga['revision']);
        $this->assertSame('norma', $carga['tipo_fuente']);
    }

    public function test_evaluador_sin_entorno_usa_el_limite_mas_estricto(): void
    {
        $e = new EvaluadorEstado(config('umbrales'));

        $this->assertSame(Estado::Critico, $e->evaluar('radiacion_diaria', ['valor' => 0.9]));
        $this->assertSame(Estado::Critico, $e->evaluar('radiacion_diaria', ['valor' => 0.9], ['entorno' => 'desconocido']));
        $this->assertSame(Estado::Normal, $e->evaluar('radiacion_diaria', ['valor' => 1.0], ['entorno' => 'estacion']));
    }

    // ---------------------------------------------------------------- dosis acumulada

    public static function dosisAcumulada(): array
    {
        return [
            '479 normal' => [479.0, 'normal'],
            '480 precaución (80 % de 600)' => [480.0, 'precaucion'],
            '599 precaución' => [599.0, 'precaucion'],
            '600 crítico (límite de carrera)' => [600.0, 'critico'],
            '650 crítico' => [650.0, 'critico'],
        ];
    }

    /** @dataProvider dosisAcumulada */
    public function test_dosis_acumulada_contra_600_msv(float $valor, string $estado): void
    {
        $ev = $this->registrar($this->astronauta(), 'dosis_acumulada', ['valor' => $valor]);

        $this->assertSame($estado, $ev->estado);
        $this->assertSame(self::CARRERA, $this->textoRequisito($ev));
    }

    // ---------------------------------------------------------------- evento solar (solo regla y texto)

    public function test_proyeccion_de_evento_solar_sobre_250_es_critica_con_refugio(): void
    {
        $e = new EvaluadorEstado(config('umbrales'));

        $this->assertNull($e->eventoSolar(250.0));
        $r = $e->eventoSolar(250.1);
        $this->assertSame(Estado::Critico, $r['estado']);
        $this->assertSame('alarma y refugio blindado', $r['accion']);
        $this->assertStringContainsString('refugio blindado', CatalogoRecomendaciones::EVENTO_SOLAR);
    }

    // ---------------------------------------------------------------- presión arterial por bandas

    public static function presionBandas(): array
    {
        $pa = fn (string $t) => $t;

        return [
            'normal 115/75' => [115, 75, 'Normal', 'normal', $pa('Normal: menor a 120/80 mmHg')],
            'normal 119/79' => [119, 79, 'Normal', 'normal', $pa('Normal: menor a 120/80 mmHg')],
            'elevada 125/75' => [125, 75, 'Elevada', 'precaucion', $pa('Elevada: sistólica 120–129 y diastólica menor a 80 mmHg')],
            'elevada 129/79' => [129, 79, 'Elevada', 'precaucion', $pa('Elevada: sistólica 120–129 y diastólica menor a 80 mmHg')],
            'HTA1 por sistólica 135/78' => [135, 78, 'Hipertensión 1', 'precaucion', $pa('Hipertensión 1: sistólica 130–139 u diastólica 80–89 mmHg')],
            'HTA1 por diastólica 118/85' => [118, 85, 'Hipertensión 1', 'precaucion', $pa('Hipertensión 1: sistólica 130–139 u diastólica 80–89 mmHg')],
            'HTA2 por sistólica 140/85' => [140, 85, 'Hipertensión 2', 'critico', $pa('Hipertensión 2: sistólica ≥ 140 o diastólica ≥ 90 mmHg')],
            'HTA2 por diastólica 125/90' => [125, 90, 'Hipertensión 2', 'critico', $pa('Hipertensión 2: sistólica ≥ 140 o diastólica ≥ 90 mmHg')],
            'crisis por sistólica 185/100' => [185, 100, 'Crisis hipertensiva', 'critico', $pa('Crisis: mayor a 180/120 mmHg')],
            'crisis por diastólica 150/125' => [150, 125, 'Crisis hipertensiva', 'critico', $pa('Crisis: mayor a 180/120 mmHg')],
        ];
    }

    /** @dataProvider presionBandas */
    public function test_presion_arterial_por_bandas(int $sis, int $dia, string $banda, string $estado, string $requisito): void
    {
        $valores = ['sistolica' => $sis, 'diastolica' => $dia];
        $this->assertSame($banda, (new EvaluadorEstado(config('umbrales')))->banda('presion_arterial', $valores));

        $ev = $this->registrar($this->astronauta(), 'presion_arterial', $valores);

        $this->assertSame($estado, $ev->estado);
        $this->assertSame($requisito, $this->textoRequisito($ev));
    }

    public function test_crisis_hipertensiva_contacta_a_tierra_con_su_texto(): void
    {
        $ev = $this->registrar($this->astronauta(), 'presion_arterial', ['sistolica' => 190, 'diastolica' => 110]);

        $this->assertSame('contactar_tierra', $ev->accion);
        $this->assertStringContainsString('Crisis hipertensiva', $ev->recomendacion);
        $this->assertSame(1, AlertaSaliente::where('evaluacion_id', $ev->id)->count());
    }

    public function test_banda_es_null_en_indicadores_sin_bandas(): void
    {
        $this->assertNull((new EvaluadorEstado(config('umbrales')))->banda('glucosa', ['valor' => 90]));
    }

    // ---------------------------------------------------------------- temperatura por salto

    public static function temperaturaConBasePrevuelo(): array
    {
        // base pre-vuelo 36.6 °C; saltos de ejemplo 0.6 (precaución) y 1.2 (crítico)
        return [
            'sin salto 36.9' => [36.9, false, 'normal'],
            'salto 0.6 -> precaución' => [37.2, false, 'precaucion'],
            'salto 1.2 -> crítico' => [37.8, false, 'critico'],
            'salto 1.2 tras ejercicio -> no alerta' => [37.8, true, 'normal'],
            'salto 0.6 tras ejercicio -> no alerta' => [37.2, true, 'normal'],
        ];
    }

    /** @dataProvider temperaturaConBasePrevuelo */
    public function test_temperatura_por_salto_sobre_base_prevuelo(float $valor, bool $ejercicio, string $estado): void
    {
        $a = $this->astronauta();
        LineaBase::create(['astronauta_id' => $a->id, 'metrica' => 'temperatura_basal', 'valor' => 36.6]);
        if ($ejercicio) {
            $this->ejercicioHecho($a, now()->subMinutes(30));
        }

        $ev = $this->registrar($a, 'temperatura', ['valor' => $valor]);

        $this->assertSame($estado, $ev->estado);
        $this->assertNotNull($ev->requisito_id);
        $this->assertSame('temperatura', Requisito::find($ev->requisito_id)->dominio);
    }

    public function test_ejercicio_fuera_de_la_ultima_hora_no_evita_la_alerta(): void
    {
        $a = $this->astronauta();
        LineaBase::create(['astronauta_id' => $a->id, 'metrica' => 'temperatura_basal', 'valor' => 36.6]);
        $this->ejercicioHecho($a, now()->subMinutes(120));

        $this->assertSame('critico', $this->registrar($a, 'temperatura', ['valor' => 37.8])->estado);
    }

    public function test_ejercicio_de_otro_astronauta_o_que_no_es_ejercicio_no_cuenta(): void
    {
        $a = $this->astronauta();
        LineaBase::create(['astronauta_id' => $a->id, 'metrica' => 'temperatura_basal', 'valor' => 36.6]);
        $this->ejercicioHecho($this->astronauta(), now()->subMinutes(10));
        $this->ejercicioHecho($a, now()->subMinutes(10), 'bien');

        $this->assertSame('critico', $this->registrar($a, 'temperatura', ['valor' => 37.8])->estado);
    }

    public function test_el_ejercicio_no_oculta_una_temperatura_baja(): void
    {
        $a = $this->astronauta();
        LineaBase::create(['astronauta_id' => $a->id, 'metrica' => 'temperatura_basal', 'valor' => 36.6]);
        $this->ejercicioHecho($a, now()->subMinutes(10));

        $this->assertSame('critico', $this->registrar($a, 'temperatura', ['valor' => 35.3])->estado);
    }

    public static function temperaturaSinBasePrevuelo(): array
    {
        // historial móvil de 5 mediciones a 36.5 °C
        return [
            'sin salto 36.8' => [36.8, false, 'normal'],
            'salto 0.8 -> precaución' => [37.3, false, 'precaucion'],
            'salto 1.5 -> crítico' => [38.0, false, 'critico'],
            'salto 1.5 tras ejercicio -> no alerta' => [38.0, true, 'normal'],
        ];
    }

    /** @dataProvider temperaturaSinBasePrevuelo */
    public function test_temperatura_por_salto_sobre_base_movil(float $valor, bool $ejercicio, string $estado): void
    {
        $a = $this->astronauta();
        foreach (range(1, 5) as $i) {
            $a->mediciones()->create(['indicador' => 'temperatura', 'valores' => ['valor' => 36.5], 'medido_en' => now()->subDays($i), 'origen' => 'manual']);
        }
        if ($ejercicio) {
            $this->ejercicioHecho($a, now()->subMinutes(20));
        }

        $this->assertSame($estado, $this->registrar($a, 'temperatura', ['valor' => $valor])->estado);
    }

    public function test_temperatura_sin_ninguna_base_usa_el_rango_absoluto_como_respaldo(): void
    {
        $a = $this->astronauta();

        $this->assertSame('normal', $this->registrar($a, 'temperatura', ['valor' => 36.8])->estado);
        $this->assertSame('critico', $this->registrar($a, 'temperatura', ['valor' => 38.6])->estado);
    }

    private function ejercicioHecho(Astronauta $a, $cuando, string $kind = 'ej'): void
    {
        $r = Rutina::create([
            'astronauta_id' => $a->id, 'nombre' => 'Cinta', 'tipo' => 'Cardio', 'icono' => 'pedal_bike', 'kind' => $kind,
            'hora' => '08:00', 'cantidad' => 30, 'dias' => [1, 2, 3, 4, 5, 6, 7],
        ]);
        RutinaEjecucion::create(['rutina_id' => $r->id, 'fecha' => $cuando->toDateString(), 'estado' => 'done', 'hecha_en' => $cuando]);
    }

    // ---------------------------------------------------------------- hidratación (sin cambio de regla)

    public function test_hidratacion_2_0_sigue_en_precaucion_como_su_regla_actual(): void
    {
        $e = new EvaluadorEstado(config('umbrales'));
        $this->assertSame(Estado::Precaucion, $e->evaluar('hidratacion', ['valor' => 2.0]));

        $ev = $this->registrar($this->astronauta(), 'hidratacion', ['valor' => 2.0]);

        $this->assertSame('precaucion', $ev->estado);
        $this->assertSame('Hidratación diaria baja', $this->textoRequisito($ev));
    }

    public function test_pulso_conserva_sus_umbrales(): void
    {
        $e = new EvaluadorEstado(config('umbrales'));

        $this->assertSame(Estado::Normal, $e->evaluar('frecuencia_cardiaca', ['valor' => 100]));
        $this->assertSame(Estado::Precaucion, $e->evaluar('frecuencia_cardiaca', ['valor' => 120]));
        $this->assertSame(Estado::Critico, $e->evaluar('frecuencia_cardiaca', ['valor' => 121]));
    }

    public function test_indicador_sin_requisito_mapeado_guarda_null(): void
    {
        $this->assertNull($this->registrar($this->astronauta(), 'glucosa', ['valor' => 90])->requisito_id);
    }

    // ---------------------------------------------------------------- care_level

    public static function careLevels(): array
    {
        // nivel, retardo, a_bordo esperado, videoconsulta esperada (acción crítica)
        return [
            'nivel 3, sin retardo' => [3, 0, false, true],
            'nivel 3, retardo alto' => [3, 20, true, true],
            'nivel 4, sin retardo' => [4, 0, true, false],
            'nivel 5, sin retardo' => [5, 0, true, false],
            'nivel 5, retardo alto' => [5, 20, true, false],
        ];
    }

    /** @dataProvider careLevels */
    public function test_care_level_en_las_reglas_de_escalamiento(int $nivel, int $retardo, bool $aBordo, bool $video): void
    {
        $d = (new ReglasEscalamiento())->decidir(Estado::Critico, 'estable', $retardo, $nivel);

        $this->assertSame(ReglasEscalamiento::CONTACTAR_TIERRA, $d['accion']);
        $this->assertTrue($d['encolar_tierra']);
        $this->assertSame($aBordo, $d['a_bordo']);
        $this->assertSame($video, $d['videoconsulta']);
    }

    public function test_care_level_por_defecto_es_3_y_normal_no_hace_nada(): void
    {
        $r = new ReglasEscalamiento();

        $this->assertSame($r->decidir(Estado::Critico, 'estable', 0), $r->decidir(Estado::Critico, 'estable', 0, 3));
        $d = $r->decidir(Estado::Normal, 'estable', 0, 5);
        $this->assertFalse($d['a_bordo']);
        $this->assertFalse($d['videoconsulta']);
    }

    public function test_critico_con_care_level_4_sigue_encolando_el_aviso_a_tierra(): void
    {
        $ev = $this->registrar($this->astronauta('estacion', 4), 'dosis_acumulada', ['valor' => 610]);

        $this->assertSame('critico', $ev->estado);
        $this->assertSame(1, AlertaSaliente::where('evaluacion_id', $ev->id)->count());
    }

    public function test_alerta_a_tierra_lleva_requisito_y_revision(): void
    {
        $ev = $this->registrar($this->astronauta(), 'dosis_acumulada', ['valor' => 610]);

        $carga = AlertaSaliente::where('evaluacion_id', $ev->id)->firstOrFail()->carga;
        $this->assertSame(self::CARRERA, $carga['requisito']);
        $this->assertStringContainsString('Rev. C', $carga['revision']);
        $this->assertSame('norma', $carga['tipo_fuente']);
        $this->assertNotNull($ev->requisito_id);
    }
}
