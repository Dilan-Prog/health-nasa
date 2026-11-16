<?php

namespace Tests\Feature;

use App\Models\AlertaEvento;
use App\Models\AlertaGestion;
use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\CasoClinico;
use App\Models\ConsultaSintomas;
use App\Models\ConsultaTierra;
use App\Models\Evaluacion;
use App\Models\Medicion;
use App\Services\I18n\DataTextMaps;
use App\Services\Salud\CatalogoRecomendaciones;
use App\Services\Salud\Estado;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never deletes the local database data. Runs against the test database only. */
class I18nDataCommandTest extends TestCase
{
    use DatabaseTransactions;

    private const REC_ES = 'Repite la medición en unos minutos, descansa, hidrátate y registra cómo te sientes.';
    private const REC_EN = 'Repeat the measurement in a few minutes, rest, hydrate and log how you feel.';
    private const REC_DESCONOCIDA = 'Esta recomendación antigua no está en el mapa.';

    private Astronauta $a;
    private int $evaluacionId;
    private int $evaluacionDesconocidaId;
    private int $eventoId;
    private int $gestionId;
    private int $consultaId;
    private int $salienteId;
    private int $casoId;
    private int $requisitoId;
    private int $requisitoPropioId;
    private int $medicionId;
    private int $sintomasId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('healts_nasa_test', DB::connection()->getDatabaseName(), 'These tests must only run on the test database.');

        $this->a = Astronauta::create(['nombre' => 'Elena Moreau', 'mision' => 'T-1', 'entorno' => 'estacion']);
        DB::table('astronautas')->where('id', $this->a->id)->update(['rol' => 'Comandante']);

        $m = Medicion::create(['astronauta_id' => $this->a->id, 'indicador' => 'frecuencia_cardiaca', 'valores' => ['valor' => 130],
            'medido_en' => now(), 'origen' => 'manual', 'calidad_motivo' => 'Señal plana: 4 lecturas idénticas seguidas (72).']);
        $this->medicionId = $m->id;
        $this->evaluacionId = Evaluacion::create(['medicion_id' => $m->id, 'estado' => 'precaucion', 'tendencia' => 'estable',
            'accion' => 'repetir', 'recomendacion' => self::REC_ES])->id;
        $this->evaluacionDesconocidaId = Evaluacion::create(['medicion_id' => $m->id, 'estado' => 'precaucion', 'tendencia' => 'estable',
            'accion' => 'repetir', 'recomendacion' => self::REC_DESCONOCIDA])->id;

        $this->eventoId = AlertaEvento::create(['astronauta_id' => $this->a->id, 'indicador' => 'frecuencia_cardiaca',
            'mensaje' => 'Frecuencia cardíaca escalada a Flight surgeon', 'icono' => 'trending_up', 'severidad' => 'crit'])->id;
        $this->gestionId = AlertaGestion::create(['astronauta_id' => $this->a->id, 'indicador' => 'frecuencia_cardiaca', 'medido_en' => now(),
            'severidad' => 'crit', 'valor' => '130 lpm', 'razon' => '130 lpm supera tu máximo de 100', 'estado' => 'nueva', 'nivel' => 0])->id;
        $this->consultaId = ConsultaTierra::create(['astronauta_id' => $this->a->id, 'tipo' => 'msg', 'titulo' => 'Mensaje · frecuencia cardíaca y presión arterial',
            'nota' => 'Me siento mareado desde ayer', 'indicadores' => ['frecuencia_cardiaca', 'presion_arterial'], 'etapa' => 0, 'fallida' => false])->id;
        $this->salienteId = AlertaSaliente::create(['astronauta_id' => $this->a->id, 'estado' => 'pendiente', 'carga' => [
            'astronauta' => 'Elena Moreau', 'indicador' => 'Frecuencia cardíaca', 'valor' => '130 lpm', 'motivo' => '130 lpm supera tu máximo de 100',
            'requisito' => 'Dosis efectiva de carrera', 'revision' => 'NASA-STD-3001 Vol. 1 Rev. C (aprobada 15-sep-2023)',
            'dato_sospechoso' => 'Señal plana: 4 lecturas idénticas seguidas (72).', 'estado' => 'critico', 'accion' => 'contactar_tierra',
            'sintomas' => ['dolor de cabeza intenso'], 'nota' => 'Me duele la cabeza desde esta mañana',
        ]])->id;
        $this->casoId = CasoClinico::create(['astronauta_id' => $this->a->id, 'patron' => 'fiebre', 'severidad' => 'precaucion',
            'evidencia' => ['ventana_min' => 60, 'senales' => [['indicador' => 'temperatura', 'estado' => 'precaucion']]], 'estado' => 'abierto', 'detectado_en' => now()])->id;
        $this->sintomasId = ConsultaSintomas::create(['astronauta_id' => $this->a->id, 'texto' => 'Tengo dolor de cabeza y mareo', 'urgencia' => 'observacion'])->id;

        // Requirement rows as they were seeded BEFORE the translation (Spanish text, unit and revision), by stable clave.
        DB::table('requisitos')->updateOrInsert(['clave' => 'norma.radiacion.gcr_espacio_libre'], [
            'texto' => 'GCR en espacio libre', 'dominio' => 'radiacion', 'tipo' => 'norma', 'unidad' => 'mSv/día',
            'revision' => 'NASA-STD-3001 Vol. 1 Rev. C (aprobada 15-sep-2023)', 'operador' => '<', 'valor' => 1.3,
        ]);
        $this->requisitoId = (int) DB::table('requisitos')->where('clave', 'norma.radiacion.gcr_espacio_libre')->value('id');
        $this->requisitoPropioId = DB::table('requisitos')->insertGetId([
            'clave' => 'test.propio.fila', 'texto' => 'Texto propio que nadie tradujo', 'dominio' => 'radiacion', 'tipo' => 'norma',
        ]);
    }

    private function ejecutar(bool $confirmar): string
    {
        Artisan::call('i18n:datos', $confirmar ? ['--confirmar' => true] : []);

        return Artisan::output();
    }

    private function conteos(): array
    {
        $out = [];
        foreach (['evaluaciones', 'alertas_eventos', 'alertas_gestion', 'consultas_tierra', 'alertas_salientes', 'casos_clinicos', 'requisitos', 'astronautas', 'mediciones'] as $t) {
            $out[$t] = DB::table($t)->count();
        }

        return $out;
    }

    private function foto(): array
    {
        return [
            DB::table('evaluaciones')->orderBy('id')->get()->toArray(),
            DB::table('alertas_eventos')->orderBy('id')->get()->toArray(),
            DB::table('alertas_gestion')->orderBy('id')->get()->toArray(),
            DB::table('consultas_tierra')->orderBy('id')->get()->toArray(),
            DB::table('alertas_salientes')->orderBy('id')->get()->toArray(),
            DB::table('requisitos')->orderBy('id')->get()->toArray(),
            DB::table('astronautas')->orderBy('id')->get()->toArray(),
            DB::table('mediciones')->orderBy('id')->get()->toArray(),
        ];
    }

    public function test_simulation_reports_and_changes_nothing(): void
    {
        $antes = $this->foto();

        $salida = $this->ejecutar(false);

        $this->assertEquals($antes, $this->foto(), 'Simulation must not write anything.');
        $this->assertStringContainsString('simulation: nothing was written', $salida);
        $this->assertStringContainsString('evaluaciones', $salida);
        $this->assertStringContainsString('recomendacion', $salida);
        $this->assertStringContainsString('Repite la medición en unos minutos', $salida); // example before
        $this->assertStringContainsString('Repeat the measurement in a few minutes', $salida); // example after
        $this->assertStringContainsString('astronautas', $salida);
        $this->assertStringContainsString('requisitos', $salida);
        $this->assertStringContainsString('alertas_salientes', $salida);
        $this->assertStringContainsString('botiquin_items', $salida); // documented as not touched
        $this->assertStringContainsString('misiones.entorno', $salida); // documented as a key, not touched
    }

    public function test_confirm_updates_in_place_keeping_ids(): void
    {
        $conteos = $this->conteos();

        $this->ejecutar(true);

        $this->assertSame($conteos, $this->conteos(), 'No row may be created or deleted.');

        $this->assertSame(self::REC_EN, DB::table('evaluaciones')->where('id', $this->evaluacionId)->value('recomendacion'));
        $this->assertSame('Commander', DB::table('astronautas')->where('id', $this->a->id)->value('rol'));
        $this->assertSame('Flat signal: 4 identical consecutive readings (72).', DB::table('mediciones')->where('id', $this->medicionId)->value('calidad_motivo'));
        $this->assertSame('Heart rate escalated to Flight Surgeon', DB::table('alertas_eventos')->where('id', $this->eventoId)->value('mensaje'));
        $g = DB::table('alertas_gestion')->where('id', $this->gestionId)->first();
        $this->assertSame('130 bpm', $g->valor);
        $this->assertSame('130 bpm exceeds your maximum of 100', $g->razon);
        $this->assertSame('Message · heart rate and blood pressure', DB::table('consultas_tierra')->where('id', $this->consultaId)->value('titulo'));

        $carga = json_decode(DB::table('alertas_salientes')->where('id', $this->salienteId)->value('carga'), true);
        $this->assertSame('Heart rate', $carga['indicador']);
        $this->assertSame('130 bpm', $carga['valor']);
        $this->assertSame('130 bpm exceeds your maximum of 100', $carga['motivo']);
        $this->assertSame('Career effective dose', $carga['requisito']);
        $this->assertSame('NASA-STD-3001 Vol. 1 Rev. C (approved 15 Sep 2023)', $carga['revision']);
        $this->assertSame('Flat signal: 4 identical consecutive readings (72).', $carga['dato_sospechoso']);
        $this->assertSame('critico', $carga['estado']); // keys and stored values stay

        $r = DB::table('requisitos')->where('id', $this->requisitoId)->first();
        $this->assertSame('GCR in free space', $r->texto);
        $this->assertSame('mSv/day', $r->unidad);
        $this->assertSame('NASA-STD-3001 Vol. 1 Rev. C (approved 15 Sep 2023)', $r->revision);
        $this->assertSame('norma.radiacion.gcr_espacio_libre', $r->clave);
        $this->assertSame('radiacion', $r->dominio); // dominio is a lookup key
    }

    public function test_second_pass_changes_nothing(): void
    {
        $this->ejecutar(true);
        $despues = $this->foto();

        $salida = $this->ejecutar(true);

        $this->assertEquals($despues, $this->foto());
        $this->assertStringContainsString('Updated values: 0', $salida);
        $this->assertStringContainsString('Nothing to change', $salida);
    }

    public function test_user_written_text_is_left_intact(): void
    {
        $this->ejecutar(true);

        $this->assertSame('Me siento mareado desde ayer', DB::table('consultas_tierra')->where('id', $this->consultaId)->value('nota'));
        $this->assertSame('Elena Moreau', DB::table('astronautas')->where('id', $this->a->id)->value('nombre'));
        $carga = json_decode(DB::table('alertas_salientes')->where('id', $this->salienteId)->value('carga'), true);
        $this->assertSame('Me duele la cabeza desde esta mañana', $carga['nota']);
        $this->assertSame(['dolor de cabeza intenso'], $carga['sintomas']);
        $this->assertSame('Elena Moreau', $carga['astronauta']);
        $this->assertSame('Tengo dolor de cabeza y mareo', ConsultaSintomas::find($this->sintomasId)->texto);
        $this->assertSame('fiebre', CasoClinico::find($this->casoId)->patron);
        $this->assertSame(60, CasoClinico::find($this->casoId)->evidencia['ventana_min']);
    }

    public function test_unmapped_texts_are_listed_and_left_intact(): void
    {
        $salida = $this->ejecutar(true);

        $this->assertStringContainsString('Unmapped', $salida);
        $this->assertStringContainsString(self::REC_DESCONOCIDA, $salida);
        $this->assertStringContainsString('Texto propio que nadie tradujo', $salida);
        $this->assertSame(self::REC_DESCONOCIDA, DB::table('evaluaciones')->where('id', $this->evaluacionDesconocidaId)->value('recomendacion'));
        $this->assertSame('Texto propio que nadie tradujo', DB::table('requisitos')->where('id', $this->requisitoPropioId)->value('texto'));
    }

    public function test_every_recommendation_in_the_map_is_what_the_service_generates_today(): void
    {
        $actuales = [];
        $catalogo = new CatalogoRecomendaciones();
        $claves = array_keys(config('umbrales.indicadores'));
        foreach ($claves as $clave) {
            foreach ([Estado::Normal, Estado::Precaucion, Estado::Critico] as $estado) {
                $actuales[] = $catalogo->para($clave, $estado);
            }
        }
        $actuales[] = $catalogo->para('presion_arterial', Estado::Critico, 'crisis');
        $actuales[] = CatalogoRecomendaciones::EVENTO_SOLAR;
        $actuales[] = config('ventanas.reglas.sueno_corto.recomendacion');

        foreach (DataTextMaps::RECOMENDACIONES as $es => $en) {
            $this->assertContains($en, $actuales, "The map points to an English text the service no longer generates: {$en}");
            $this->assertNotSame($es, $en);
        }
        // and the reverse: everything the service generates has an old Spanish counterpart in the map
        foreach (array_unique($actuales) as $en) {
            $this->assertContains($en, DataTextMaps::RECOMENDACIONES, "Missing Spanish source for: {$en}");
        }
    }
}
