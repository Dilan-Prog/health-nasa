<?php

namespace Tests\Feature;

use App\Contracts\Orientador;
use App\Livewire\MedicoFlotante;
use App\Models\Astronauta;
use App\Models\CasoClinico;
use App\Models\ConsultaSintomas;
use App\Models\RegistroSalud;
use App\Models\ZonaDolor;
use App\Services\Ia\OrientadorConversacional;
use Illuminate\Support\Facades\DB;
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
        $this->get('/')->assertOk()->assertSeeLivewire(MedicoFlotante::class)->assertSee('aria-label="Flight Surgeon"', false);
        $this->get('/acceso')->assertOk()->assertDontSee('aria-label="Flight Surgeon"', false);
    }

    public function test_muestra_aviso_etiquetas_y_los_datos_del_caso(): void
    {
        $this->caso($this->a, 'critico');
        Livewire::test(MedicoFlotante::class)
            ->assertSee('Flight Surgeon · Local AI (simulated)')
            ->assertSee('SIMULATED')
            ->assertSee('LOCAL AI AND N8N: COMING SOON')
            ->assertSee('FOR '.mb_strtoupper($this->a->nombre).' · GUIDES, DOES NOT DIAGNOSE')
            ->assertSee(config('ia.aviso', MedicoFlotante::AVISO))
            ->assertSee('Posible patrón hipoxia')
            ->assertSee('aria-label="Posible patrón hipoxia #'.$this->a->id.', CRITICAL"', false)
            ->assertSee('WHAT TO DO')
            ->assertSee('SOURCE · Norma de prueba')
            ->assertSee('7-DAY HISTORY')
            ->assertSee('AHORA 92 %', false)
            ->assertSee('RULE CONFIDENCE · SIMULATED')
            ->assertSee('Got it')->assertSee('Alert ground')->assertSee('Open Flight Surgeon');
    }

    public function test_circulo_etiqueta_accesible_e_insignia(): void
    {
        $this->caso($this->a, 'critico');
        Livewire::test(MedicoFlotante::class)
            ->assertSee('aria-label="Flight Surgeon, 1 unread, critical case"', false)
            ->assertSee('data-pulso', false)
            ->assertSee('aria-controls="mf-dialogo"', false);
    }

    public function test_banda_de_escalado_empeoro_y_evidencia(): void
    {
        $this->fake->extra = ['detectadoEn' => '10:42', 'escaladoEn' => '10:43', 'empeoro' => true, 'evidencia' => 'SpO₂ 89 (CRÍTICO) · VENTANA 10 MIN', 'icono' => 'air'];
        $this->caso($this->a, 'critico');
        Livewire::test(MedicoFlotante::class)
            ->assertSee('DETECTED 10:42 UTC')->assertSee('WORSENED')
            ->assertSee('Automatically escalated to ground via the alert route · 10:43 UTC')
            ->assertSee('EVIDENCE · SpO₂ 89 (CRÍTICO) · VENTANA 10 MIN')
            ->assertSee('Alert sent to ground');
    }

    public function test_sin_banda_ni_empeoro_si_no_hay_datos_o_es_precaucion(): void
    {
        $this->fake->extra = ['escaladoEn' => '10:43'];
        $this->caso($this->a, 'precaucion');
        Livewire::test(MedicoFlotante::class)->assertDontSee('Automatically escalated to ground')->assertDontSee('WORSENED')->assertDontSee('EVIDENCE ·')->assertSee('Avisar a tierra');
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
            ->assertSee('Hide details')->assertSee('Show details');
    }

    public function test_expediente_vacio_y_con_auditoria(): void
    {
        $primero = $this->caso($this->a);
        Livewire::test(MedicoFlotante::class)->assertSee('No related history')->assertSee('No treatments recorded')->assertDontSee('Record access logged');

        $primero->delete();
        $this->fake->extra = ['auditadoEn' => '10:42', 'expedienteRelevante' => [['ALERGIA', 'Penicilina'], 'Asma en la infancia']];
        $this->caso($this->a, 'critico');
        Livewire::test(MedicoFlotante::class)
            ->assertSee('ALERGIA')->assertSee('Penicilina')->assertSee('Asma en la infancia')
            ->assertSee('Record access logged in audit trail · 10:42 UTC')
            ->assertDontSee('No related history');
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
            ->assertSee('0.78')->assertSee('SOURCES · MEDICAL REVIEW: PENDIENTE')->assertSee('Ficha de prueba')->assertSee('help');
    }

    public function test_el_boton_de_aviso_cambia_a_enviado_y_queda_deshabilitado(): void
    {
        $caso = $this->caso($this->a);
        $c = Livewire::test(MedicoFlotante::class)->assertSee('Alert ground')->assertDontSee('Alert sent to ground');
        $c->call('avisarATierra', $caso->id)->assertSee('Alert sent to ground')->assertSeeHtml('disabled');
    }

    public function test_vacio_con_tripulante(): void
    {
        Livewire::test(MedicoFlotante::class)->assertSee('No open cases for '.$this->a->nombre)->assertSee('check_circle');
    }

    public function test_sin_caso_no_abre_ni_hay_insignia(): void
    {
        Livewire::test(MedicoFlotante::class)->assertSet('abrirAlCargar', false)->assertDontSee('mf-insignia')->assertSee('No open cases');
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
        Livewire::test(MedicoFlotante::class)->call('avisarATierra', $caso->id)->assertSee('Alert sent to ground');
        $this->assertDatabaseHas('consultas_tierra', ['astronauta_id' => $this->a->id, 'tipo' => 'msg']);
    }

    public function test_sin_tripulante_pide_elegir_uno(): void
    {
        Astronauta::query()->delete();
        Livewire::test(MedicoFlotante::class)->assertSee('Select a crew member');
    }

    // ---------- Orientador conversacional (texto libre) ----------

    /** Enlaza un OrientadorConversacional con colaboradores falsos: la respuesta y las entidades las fija cada prueba. */
    private function fakeConversacional(array $respuesta = [], ?array $sintomas = null): void
    {
        $sintomas ??= [['clave' => 'mareo', 'etiqueta' => 'Mareo', 'region' => null, 'lado' => null, 'calidad' => null, 'intensidad' => null, 'duracion_min' => null, 'confianza' => 90]];
        $analisis = ['entendido' => $sintomas !== [], 'confianza' => 88, 'sintomas' => $sintomas, 'negados' => ['fiebre'], 'banderas_rojas' => []];
        $base = [
            'urgencia' => 'autocuidado', 'alcance' => true, 'conclusion' => 'Posible mareo por adaptación.',
            'hipotesis' => [['titulo' => 'Posible mareo por adaptación espacial', 'confianza' => 72, 'por_que' => ['Llevas 3 días en órbita'], 'sintomas' => ['mareo'], 'ficha' => 'mareo', 'origen' => 'documento']],
            'acciones' => [['id' => 'x1', 'texto' => 'Reposa unos minutos con la cabeza quieta', 'fuente' => ['etiqueta' => 'Ficha mareo', 'texto' => 't', 'origen' => 'ejemplo_simulado']]],
            'panel_vigente' => true,
            'botiquin_opciones' => [['id' => 7, 'nombre' => 'Antiemético de prueba', 'grupo' => 'Mareo por adaptación', 'nota' => 'Consúltalo', 'contraindicaciones' => null, 'aviso_medicacion' => null]],
            'seguimiento' => [['texto' => 'Vuelve a medir tu pulso', 'indicador' => 'frecuencia_cardiaca']],
            'escalar_si' => ['Si aparece dolor en el pecho'], 'escalar_ahora' => false, 'medidas_inmediatas' => [],
            'eva_proxima' => null, 'datos_usados' => [['etiqueta' => 'Indicadores', 'detalle' => 'Pulso de las últimas 24 h']],
            'fuentes' => [['etiqueta' => 'NASA-STD de prueba', 'texto' => 'x', 'origen' => 'documento']],
        ];
        $this->app->bind(OrientadorConversacional::class, fn () => new OrientadorConversacional([
            'analizar' => fn (string $t) => $analisis + ['texto' => $t],
            'desdeEntidades' => fn (array $s) => ['sintomas' => $s, 'entendido' => $s !== []] + $analisis,
            'contexto' => fn () => [],
            'accionesPanel' => fn () => ['p1'],
            'razonar' => fn () => $respuesta + $base,
        ]));
    }

    public function test_enviar_texto_muestra_la_respuesta_completa(): void
    {
        $this->fakeConversacional();
        Livewire::test(MedicoFlotante::class)
            ->set('texto', 'Estoy mareado')->call('enviar')
            ->assertSet('texto', '')
            ->assertSee('Guidance on what you describe')
            ->assertSee('1 · WHAT I UNDERSTOOD')->assertSee('Estoy mareado')->assertSee('aria-label="Remove Mareo"', false)->assertSee('DENIED · Fever')
            ->assertSee('Urgency: SELF-CARE')
            ->assertSee('3 · HYPOTHESES TO RULE OUT')->assertSee('Posible mareo por adaptación espacial')->assertSee('0.72')->assertSee('Llevas 3 días en órbita')
            ->assertSee('4 · WHAT TO DO NOW')->assertSee('What the Panel shows still applies')->assertSee('in addition to what you already see in the Panel')
            ->assertSee('Reposa unos minutos con la cabeza quieta')
            ->assertSee('5 · FROM THE MEDICAL KIT')->assertSee('Medical kit options to discuss with the flight surgeon. Contraindications not verified. No doses.')->assertSee('Antiemético de prueba')
            ->assertSee('6 · FOLLOW-UP')->assertSee('Log a measurement')->assertSee(route('registrar', ['indicador' => 'frecuencia_cardiaca']), false)
            ->assertSee('7 · WHEN TO ESCALATE')->assertSee('Si aparece dolor en el pecho')
            ->assertSee('8 · DATA I USED')->assertSee('Pulso de las últimas 24 h')
            ->assertSee('9 · SOURCES')->assertSee('Document · NASA-STD de prueba')
            ->assertSee('It helped')->assertSee('It did not help')->assertSee('Save to my log')->assertSee('Unchanged')->assertSee('Worsened')
            ->assertSee('Alert ground')->assertDontSee('Alert ground now')
            ->assertSee(config('ia.aviso', MedicoFlotante::AVISO));
        $this->assertDatabaseHas('consultas_sintomas', ['astronauta_id' => $this->a->id, 'urgencia' => 'autocuidado']);
    }

    public function test_emergencia_muestra_avisar_ahora_y_oculta_el_botiquin(): void
    {
        // El razonador devuelve opciones de botiquín por error: la interfaz no las muestra en emergencia.
        $this->fakeConversacional(['urgencia' => 'emergencia', 'escalar_ahora' => true, 'medidas_inmediatas' => ['Detén la actividad y siéntate']], [['clave' => 'dolor', 'etiqueta' => 'Dolor punzante · pecho izquierdo']]);
        $c = Livewire::test(MedicoFlotante::class)->set('texto', 'Siento un dolor punzante en el pecho izquierdo')->call('enviar')
            ->assertSee('Urgency: EMERGENCY')->assertSee('Alert ground now')->assertSee('Detén la actividad y siéntate')
            ->assertDontSee('FROM THE MEDICAL KIT')->assertDontSee('Antiemético de prueba')->assertDontSee('Medical kit options');
        $c->call('avisarConsulta')->assertSee('Alert sent to ground');
        $this->assertDatabaseHas('consultas_tierra', ['astronauta_id' => $this->a->id, 'tipo' => 'msg']);
    }

    public function test_en_emergencia_el_banner_va_antes_que_entendi(): void
    {
        $this->fakeConversacional(['urgencia' => 'emergencia', 'escalar_ahora' => true]);
        $html = Livewire::test(MedicoFlotante::class)->set('texto', 'dolor')->call('enviar')->html();
        $this->assertLessThan(strpos($html, '1 · WHAT I UNDERSTOOD'), strpos($html, 'Urgencia: EMERGENCIA'));
    }

    public function test_el_texto_con_html_se_escapa(): void
    {
        $this->fakeConversacional();
        $c = Livewire::test(MedicoFlotante::class)->set('texto', '<script>alert(1)</script> <b>me mareo</b>')->call('enviar');
        $c->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<b>me mareo</b>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_marcar_util_se_guarda(): void
    {
        $this->fakeConversacional();
        $c = Livewire::test(MedicoFlotante::class)->set('texto', 'Estoy mareado')->call('enviar')->call('marcarUtil', true);
        $this->assertTrue(ConsultaSintomas::where('astronauta_id', $this->a->id)->latest('id')->first()->util);
        $c->call('marcarUtil', false);
        $this->assertFalse(ConsultaSintomas::where('astronauta_id', $this->a->id)->latest('id')->first()->util);
    }

    public function test_sin_tripulante_no_hay_caja_ni_se_guarda_nada(): void
    {
        $this->fakeConversacional();
        Astronauta::query()->delete();
        Livewire::test(MedicoFlotante::class)->assertSee('Select a crew member')->assertDontSee('TELL ME HOW YOU FEEL')
            ->set('texto', 'Estoy mareado')->call('enviar')->assertDontSee('Guidance on what you describe');
        $this->assertSame(0, ConsultaSintomas::count());
    }

    public function test_limite_de_caracteres_y_contador(): void
    {
        $this->fakeConversacional();
        config(['orientador.max_caracteres' => 30]);
        Livewire::test(MedicoFlotante::class)
            ->assertSee('maxlength="30"', false)->assertSee('/ 30')
            ->set('texto', str_repeat('a', 31))->call('enviar')
            ->assertHasErrors('texto')->assertSee('The text exceeds the limit of 30 characters.')
            ->set('texto', '   ')->call('enviar')->assertHasErrors('texto')->assertSee('Write how you feel.');
        $this->assertSame(0, ConsultaSintomas::count());
    }

    public function test_texto_cifrado_en_la_bd(): void
    {
        $this->fakeConversacional();
        Livewire::test(MedicoFlotante::class)->set('texto', 'Estoy mareado hoy')->call('enviar');
        $crudo = DB::table('consultas_sintomas')->where('astronauta_id', $this->a->id)->value('texto');
        $this->assertStringNotContainsString('mareado', $crudo);
        $this->assertSame('Estoy mareado hoy', ConsultaSintomas::where('astronauta_id', $this->a->id)->first()->texto);
    }

    public function test_sin_entender_lo_dice_y_ofrece_reformular(): void
    {
        $this->fakeConversacional(['urgencia' => 'observacion'], []);
        Livewire::test(MedicoFlotante::class)->set('texto', 'asdf qwer')->call('enviar')
            ->assertSee('I did not recognize any symptom')->assertSee('I could not interpret your message')->assertSee('Add symptom')
            ->assertDontSee('3 · HYPOTHESES')->assertDontSee('Unchanged');
    }

    public function test_fuera_de_alcance(): void
    {
        $this->fakeConversacional(['alcance' => false], []);
        Livewire::test(MedicoFlotante::class)->set('texto', '¿Quién ganó el partido?')->call('enviar')->assertSee('I can only guide you on symptoms');
    }

    public function test_quitar_y_anadir_sintomas_vuelve_a_analizar_desde_entidades(): void
    {
        $this->fakeConversacional();
        $c = Livewire::test(MedicoFlotante::class)->set('texto', 'Estoy mareado')->call('enviar');
        $c->set('nuevoSintoma', 'nauseas')->set('nuevaZona', 'abdomen')->set('nuevoLado', 'derecho')->call('anadirSintoma')
            ->assertSee('Nausea · abdomen right');
        $ultima = ConsultaSintomas::where('astronauta_id', $this->a->id)->latest('id')->first();
        $this->assertCount(2, $ultima->entidades['sintomas']);
        $this->assertSame('Estoy mareado', $ultima->texto);

        $c->call('quitarSintoma', 0);
        $ultima = ConsultaSintomas::where('astronauta_id', $this->a->id)->latest('id')->first();
        $this->assertCount(1, $ultima->entidades['sintomas']);
        $this->assertSame('nauseas', $ultima->entidades['sintomas'][0]['clave']);
    }

    public function test_reevaluar_y_guardar_en_registro(): void
    {
        $this->fakeConversacional();
        $c = Livewire::test(MedicoFlotante::class)->set('texto', 'Estoy mareado')->call('enviar')->call('reevaluar', 'empeoro');
        $this->assertSame(2, ConsultaSintomas::where('astronauta_id', $this->a->id)->count());
        $c->call('guardarEnRegistro')->assertSee('Saved to your log');
        $this->assertDatabaseHas('registros_salud', ['astronauta_id' => $this->a->id, 'origen' => 'manual']);
    }

    public function test_ofrece_las_zonas_de_dolor_registradas_como_contexto(): void
    {
        $reg = RegistroSalud::create(['astronauta_id' => $this->a->id, 'origen' => 'manual', 'registrado_en' => now()]);
        $z = ZonaDolor::create(['registro_id' => $reg->id, 'astronauta_id' => $this->a->id, 'musculo' => 'triceps_der', 'intensidad' => 7, 'tipo' => 'Punzante', 'desde' => 'Hoy']);
        Livewire::test(MedicoFlotante::class)->assertSee('YOUR LOGGED AREAS')->assertSee('7/10')
            ->call('usarZona', $z->id)->assertSet('texto', 'Me duele '.mb_strtolower(ZonaDolor::nombre('triceps_der')).', dolor punzante');
    }

    public function test_no_muestra_ni_actua_sobre_la_consulta_de_otro_tripulante(): void
    {
        $this->fakeConversacional();
        $ajena = ConsultaSintomas::create(['astronauta_id' => $this->otro->id, 'texto' => 'secreto ajeno', 'entidades' => ['sintomas' => []], 'urgencia' => 'observacion', 'respuesta' => []]);
        Livewire::test(MedicoFlotante::class)->set('consultaId', $ajena->id)->assertDontSee('secreto ajeno')->assertDontSee('Guidance on what you describe')
            ->call('marcarUtil', true);
        $this->assertNull($ajena->fresh()->util);
    }

    public function test_los_casos_siguen_funcionando_junto_a_la_caja_de_texto(): void
    {
        $this->caso($this->a, 'critico');
        Livewire::test(MedicoFlotante::class)->assertSee('Posible patrón hipoxia')->assertSee('TELL ME HOW YOU FEEL')->assertSee('I feel dizzy')->assertSee('It hurts…')->assertSee('Short of breath');
    }
}
