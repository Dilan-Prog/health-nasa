<?php

namespace Tests\Feature;

use App\Contracts\Orientador;
use App\Livewire\MedicoFlotante;
use App\Models\Astronauta;
use App\Models\CasoClinico;
use App\Models\Mision;
use App\Services\Ia\Orientacion;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. El Orientador se fakea. */
class MedicoFlotanteTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    private Astronauta $otro;

    private object $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        [$this->a, $this->otro] = Astronauta::where('mision_id', $mision->id)->orderBy('id')->take(2)->get()->all();
        TripulanteActual::fijar($this->a->id);

        $this->fake = new class implements Orientador
        {
            public int $llamadas = 0;

            /** Campos de Orientacion que cada prueba sobrescribe (nombres del constructor). */
            public array $extra = [];

            public function orientar(CasoClinico $caso): Orientacion
            {
                $this->llamadas++;

                return new Orientacion(...array_merge([
                    'casoId' => $caso->id, 'astronautaId' => $caso->astronauta_id, 'patron' => $caso->patron, 'severidad' => $caso->severidad,
                    'titulo' => 'Posible patrón '.$caso->patron.' #'.$caso->astronauta_id, 'hallazgo' => 'Los valores podrían sugerir un patrón de prueba.',
                    'acciones' => [['texto' => 'Descansa y repite la medición', 'fuente' => ['tipo' => 'norma', 'etiqueta' => 'Norma de prueba', 'texto' => 'x']]],
                    'historial7d' => [['indicador' => 'spo2', 'etiqueta' => 'SpO₂', 'unidad' => '%', 'serie' => [98, 97, 96, 95, 94, 93, 92], 'actual' => '92', 'base' => '98']],
                    'expedienteRelevante' => [], 'tratamientos' => [],
                    'fuentes' => [['etiqueta' => 'Ficha de prueba', 'texto' => 'texto']], 'confianza' => 78,
                ], $this->extra));
            }
        };
        $this->app->instance(Orientador::class, $this->fake);
    }

    private function caso(Astronauta $a, string $sev = 'precaucion', string $estado = 'abierto'): CasoClinico
    {
        return CasoClinico::create([
            'astronauta_id' => $a->id, 'patron' => 'hipoxia', 'severidad' => $sev, 'evidencia' => [], 'estado' => $estado,
            'detectado_en' => now(), 'cerrado_en' => $estado === 'cerrado' ? now() : null,
        ]);
    }

    public function test_aparece_en_el_layout_y_no_en_el_login(): void
    {
        $this->get('/')->assertOk()->assertSeeLivewire(MedicoFlotante::class)->assertSee('aria-label="Médico de vuelo"', false);
        $this->get('/acceso')->assertOk()->assertDontSee('aria-label="Médico de vuelo"', false);
    }

    public function test_muestra_aviso_etiquetas_y_los_datos_del_caso(): void
    {
        $this->caso($this->a, 'critico');
        Livewire::test(MedicoFlotante::class)
            ->assertSee('Médico de vuelo · IA local (simulada)')
            ->assertSee('SIMULADO')
            ->assertSee('IA LOCAL Y N8N: PRÓXIMAMENTE')
            ->assertSee('PARA '.mb_strtoupper($this->a->nombre).' · ORIENTA, NO DIAGNOSTICA')
            ->assertSee(config('ia.aviso', MedicoFlotante::AVISO))
            ->assertSee('Posible patrón hipoxia')
            ->assertSee('aria-label="Posible patrón hipoxia #'.$this->a->id.', CRÍTICO"', false)
            ->assertSee('QUÉ HACER')
            ->assertSee('FUENTE · Norma de prueba')
            ->assertSee('HISTORIAL 7 DÍAS')
            ->assertSee('AHORA 92 %', false)
            ->assertSee('CONFIANZA DE LA REGLA · SIMULADA')
            ->assertSee('Entendido')->assertSee('Avisar a tierra')->assertSee('Abrir Médico de vuelo');
    }

    public function test_circulo_etiqueta_accesible_e_insignia(): void
    {
        $this->caso($this->a, 'critico');
        Livewire::test(MedicoFlotante::class)
            ->assertSee('aria-label="Médico de vuelo, 1 sin leer, caso crítico"', false)
            ->assertSee('data-pulso', false)
            ->assertSee('aria-controls="mf-dialogo"', false);
    }

    public function test_banda_de_escalado_empeoro_y_evidencia(): void
    {
        $this->fake->extra = ['detectadoEn' => '10:42', 'escaladoEn' => '10:43', 'empeoro' => true, 'evidencia' => 'SpO₂ 89 (CRÍTICO) · VENTANA 10 MIN', 'icono' => 'air'];
        $this->caso($this->a, 'critico');
        Livewire::test(MedicoFlotante::class)
            ->assertSee('DETECTADO 10:42 UTC')->assertSee('EMPEORÓ')
            ->assertSee('Escalado a tierra automáticamente por la ruta de alertas · 10:43 UTC')
            ->assertSee('EVIDENCIA · SpO₂ 89 (CRÍTICO) · VENTANA 10 MIN')
            ->assertSee('Aviso enviado a tierra');
    }

    public function test_sin_banda_ni_empeoro_si_no_hay_datos_o_es_precaucion(): void
    {
        $this->fake->extra = ['escaladoEn' => '10:43'];
        $this->caso($this->a, 'precaucion');
        Livewire::test(MedicoFlotante::class)->assertDontSee('Escalado a tierra automáticamente')->assertDontSee('EMPEORÓ')->assertDontSee('EVIDENCIA ·')->assertSee('Avisar a tierra');
    }

    public function test_bloque_de_detalle_colapsable_abierto_solo_en_el_mas_reciente(): void
    {
        $viejo = $this->caso($this->a);
        $nuevo = $this->caso($this->a, 'critico');
        Livewire::test(MedicoFlotante::class)
            ->assertSee('id="mf-detalle-'.$nuevo->id.'"', false)
            ->assertSee('aria-controls="mf-detalle-'.$viejo->id.'"', false)
            ->assertSee('x-data="{ det: true }"', false)
            ->assertSee('x-data="{ det: false }"', false)
            ->assertSee('Ocultar detalle')->assertSee('Ver detalle');
    }

    public function test_expediente_vacio_y_con_auditoria(): void
    {
        $primero = $this->caso($this->a);
        Livewire::test(MedicoFlotante::class)->assertSee('Sin antecedentes relacionados')->assertSee('Sin tratamientos registrados')->assertDontSee('Acceso al expediente registrado');

        $primero->delete();
        $this->fake->extra = ['auditadoEn' => '10:42', 'expedienteRelevante' => [['ALERGIA', 'Penicilina'], 'Asma en la infancia']];
        $this->caso($this->a, 'critico');
        Livewire::test(MedicoFlotante::class)
            ->assertSee('ALERGIA')->assertSee('Penicilina')->assertSee('Asma en la infancia')
            ->assertSee('Acceso al expediente registrado en auditoría · 10:42 UTC')
            ->assertDontSee('Sin antecedentes relacionados');
    }

    public function test_tratamientos_con_nota_y_matiz_y_confianza_con_coma(): void
    {
        $this->fake->extra = [
            'tratamientos' => [['CONSULTA A TIERRA · 3 NOV', 'Presión arterial por encima de tu rango', 'Marcaste «me ayudó»'], ['etiqueta' => 'MEDICACIÓN REGISTRADA', 'texto' => 'Suplemento de vitamina D']],
            'matiz' => 'La última vez avisaste a tierra y te ayudó.',
        ];
        $this->caso($this->a);
        Livewire::test(MedicoFlotante::class)
            ->assertSee('CONSULTA A TIERRA · 3 NOV')->assertSee('Marcaste «me ayudó»')->assertSee('MEDICACIÓN REGISTRADA')
            ->assertSee('La última vez avisaste a tierra y te ayudó.')
            ->assertSee('0,78')->assertSee('FUENTES · REVISIÓN MÉDICA: PENDIENTE')->assertSee('Ficha de prueba')->assertSee('help');
    }

    public function test_el_boton_de_aviso_cambia_a_enviado_y_queda_deshabilitado(): void
    {
        $caso = $this->caso($this->a);
        $c = Livewire::test(MedicoFlotante::class)->assertSee('Avisar a tierra')->assertDontSee('Aviso enviado a tierra');
        $c->call('avisarATierra', $caso->id)->assertSee('Aviso enviado a tierra')->assertSeeHtml('disabled');
    }

    public function test_vacio_con_tripulante(): void
    {
        Livewire::test(MedicoFlotante::class)->assertSee('Sin casos abiertos para '.$this->a->nombre)->assertSee('check_circle');
    }

    public function test_sin_caso_no_abre_ni_hay_insignia(): void
    {
        Livewire::test(MedicoFlotante::class)->assertSet('abrirAlCargar', false)->assertDontSee('mf-insignia')->assertSee('Sin casos abiertos');
    }

    public function test_abre_automaticamente_con_un_caso_nuevo_y_solo_una_vez(): void
    {
        $this->caso($this->a);
        Livewire::test(MedicoFlotante::class)->assertSet('abrirAlCargar', true);
        // La sesión recuerda que ya se mostró: al recargar la página no se reabre.
        Livewire::test(MedicoFlotante::class)->assertSet('abrirAlCargar', false);
    }

    public function test_el_poll_abre_con_un_caso_nuevo_y_no_repite(): void
    {
        $c = Livewire::test(MedicoFlotante::class)->assertSet('abrirAlCargar', false);
        $this->caso($this->a);
        $c->call('comprobar')->assertDispatched('medico-flotante-abrir');
        $c->call('comprobar')->assertNotDispatched('medico-flotante-abrir');
    }

    public function test_el_descarte_persiste_en_la_sesion(): void
    {
        $caso = $this->caso($this->a);
        Livewire::test(MedicoFlotante::class)->call('entender', $caso->id)->assertDontSee('Posible patrón hipoxia');
        Livewire::test(MedicoFlotante::class)->assertDontSee('Posible patrón hipoxia')->assertSet('abrirAlCargar', false);
    }

    public function test_reabre_al_escalar_a_critico_aunque_se_haya_descartado(): void
    {
        $caso = $this->caso($this->a, 'precaucion');
        $c = Livewire::test(MedicoFlotante::class)->call('entender', $caso->id);
        $caso->update(['severidad' => 'critico']);
        $c->call('comprobar')->assertDispatched('medico-flotante-abrir')->assertSee('Posible patrón hipoxia');
    }

    public function test_reabre_al_escalar_a_critico_si_solo_se_cerro_el_rectangulo(): void
    {
        $caso = $this->caso($this->a, 'precaucion');
        $c = Livewire::test(MedicoFlotante::class)->assertSet('abrirAlCargar', true);
        $caso->update(['severidad' => 'critico']);
        $c->call('comprobar')->assertDispatched('medico-flotante-abrir', critico: true, casoId: $caso->id);
    }

    public function test_no_muestra_datos_de_otro_tripulante(): void
    {
        $this->caso($this->otro, 'critico');
        $c = Livewire::test(MedicoFlotante::class)->assertDontSee('#'.$this->otro->id)->assertSet('abrirAlCargar', false);
        $c->call('comprobar')->assertNotDispatched('medico-flotante-abrir');
        $this->assertSame(0, $this->fake->llamadas);
        // Ni siquiera se puede actuar sobre un caso ajeno.
        $c->call('avisarATierra', CasoClinico::where('astronauta_id', $this->otro->id)->value('id'));
        $this->assertDatabaseMissing('consultas_tierra', ['astronauta_id' => $this->otro->id]);
    }

    public function test_el_poll_no_llama_al_orientador_si_no_hay_casos_nuevos(): void
    {
        $this->caso($this->a);
        $c = Livewire::test(MedicoFlotante::class);
        $this->assertSame(1, $this->fake->llamadas);
        $c->call('comprobar')->call('comprobar')->call('comprobar');
        $this->assertSame(1, $this->fake->llamadas);
        // Un caso nuevo sí: solo se orienta el nuevo.
        $this->caso($this->a, 'critico');
        $c->call('comprobar');
        $this->assertSame(2, $this->fake->llamadas);
    }

    public function test_avisar_a_tierra_reutiliza_la_consulta_existente(): void
    {
        $caso = $this->caso($this->a, 'critico');
        Livewire::test(MedicoFlotante::class)->call('avisarATierra', $caso->id)->assertSee('Aviso enviado a tierra');
        $this->assertDatabaseHas('consultas_tierra', ['astronauta_id' => $this->a->id, 'tipo' => 'msg']);
    }

    public function test_sin_tripulante_pide_elegir_uno(): void
    {
        Astronauta::query()->delete();
        Livewire::test(MedicoFlotante::class)->assertSee('Elige un tripulante');
    }
}
