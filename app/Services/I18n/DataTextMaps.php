<?php

namespace App\Services\I18n;

/**
 * Explicit, versioned Spanish -> English maps for TEXT ALREADY STORED in the database (see App\Console\Commands\I18nDatos).
 *
 * Every pair is the old Spanish text (taken from git history) and the English text the services/config generate
 * today. Matching is EXACT; patterns are used only for system-generated sentences with numbers. There is no
 * automatic translation: what is not listed here is left untouched and reported as "unmapped".
 * Bump VERSION whenever a pair is added or changed.
 */
final class DataTextMaps
{
    public const VERSION = '2026-11-21.1';

    /** evaluaciones.recomendacion: CatalogoRecomendaciones (general + per indicator), crisis, solar event, window rule. */
    public const RECOMENDACIONES = [
        'Valor dentro de lo esperado. Continúa con tu rutina de mediciones.' => 'Value within the expected range. Continue your routine measurements.',
        'Repite la medición en unos minutos, descansa, hidrátate y registra cómo te sientes.' => 'Repeat the measurement in a few minutes, rest, hydrate and log how you feel.',
        'Repite la medición de inmediato. Si se confirma, avisa a un compañero y contacta al flight surgeon.' => 'Repeat the measurement immediately. If confirmed, notify a crew member and contact the Flight Surgeon.',
        'Suspende el ejercicio intenso, respira con calma y repite la medición en reposo.' => 'Stop intense exercise, breathe calmly and repeat the measurement at rest.',
        'Revisa tu última comida y repite la medición; evita azúcares simples hasta confirmar.' => 'Review your last meal and repeat the measurement; avoid simple sugars until confirmed.',
        'Revisa la temperatura del módulo, hidrátate y vigila síntomas de infección.' => 'Check the module temperature, hydrate and watch for signs of infection.',
        'Reposa 5 min sentado, repite la medición en el mismo brazo y bebe agua.' => 'Rest seated for 5 min, repeat the measurement on the same arm and drink water.',
        'Respira profundo y despacio, revisa el sensor y la postura del dedo, y repite la medición.' => 'Breathe deeply and slowly, check the sensor and finger placement, and repeat the measurement.',
        'Revisa tu registro de comidas de la semana y coméntalo en tu próxima consulta de nutrición.' => 'Review your meal log for the week and discuss it at your next nutrition consultation.',
        'Completa hoy tu sesión de carga en el ARED y registra los minutos de ejercicio resistido.' => 'Complete your ARED loading session today and log the minutes of resistive exercise.',
        'Completa la rutina de fuerza de piernas, registra series y cargas y revisa tu dieta con nutrición.' => 'Complete the leg strength routine, log sets and loads, and review your diet with nutrition.',
        'Reduce hoy el tiempo en módulos poco blindados y duerme en la zona blindada.' => 'Reduce your time in poorly shielded modules today and sleep in the shielded area.',
        'Revisa con tierra el plan de EVAs restantes y prioriza las zonas blindadas en tu horario.' => 'Review the remaining EVA plan with the ground and prioritize shielded areas in your schedule.',
        'Ajusta tu horario de descanso y reduce la luz y el ruido antes de dormir.' => 'Adjust your rest schedule and reduce light and noise before sleeping.',
        'Habla con un compañero o programa una llamada de apoyo; cuida tu rutina social.' => 'Talk with a crew member or schedule a support call; maintain your social routine.',
        'Alarma: evento solar proyectado por encima del límite. Ve de inmediato al refugio blindado y avisa a tierra.' => 'Alarm: projected solar event above the limit. Go immediately to the shielded shelter and alert the ground.',
        'Crisis hipertensiva: reposa sentado, repite la medición y contacta de inmediato al flight surgeon. Si hay dolor de pecho, falta de aire o confusión, pide ayuda a un compañero ya.' => 'Hypertensive crisis: rest seated, repeat the measurement and contact the Flight Surgeon immediately. If there is chest pain, shortness of breath or confusion, ask a crew member for help now.',
        'Acción preventiva: evita asignar tareas críticas hasta recuperar el sueño y reprograma lo que se pueda. Protege un bloque de descanso, reduce luz y ruido antes de dormir y comenta el patrón con el médico de vuelo en la próxima ventana.' => 'Preventive action: avoid assigning critical tasks until sleep is recovered and reschedule what you can. Protect a rest block, reduce light and noise before sleep, and discuss the pattern with the Flight Surgeon at the next communication window.',
    ];

    /** Indicator labels (panel etiqueta, umbrales nombre, indicadores label: every Spanish spelling used before). */
    public const INDICADORES = [
        'Presión arterial' => 'Blood pressure',
        'Frecuencia cardíaca' => 'Heart rate',
        'Saturación O₂' => 'SpO₂',
        'Saturación de O₂' => 'SpO₂',
        'Glucosa' => 'Glucose',
        'Glucosa en sangre' => 'Glucose',
        'Temperatura' => 'Temperature',
        'Temperatura corporal' => 'Temperature',
        'IMC' => 'BMI',
        'Índice de masa corporal' => 'BMI',
        'Pérdida ósea (cadera)' => 'Bone loss (hip)',
        'Masa muscular' => 'Muscle mass',
        'Radiación diaria' => 'Daily radiation',
        'Dosis acumulada' => 'Cumulative dose',
        'Hidratación' => 'Hydration',
        'Sueño' => 'Sleep',
        'Estrés / cognitivo' => 'Stress / cognitive',
        'Visión (SANS)' => 'Vision (SANS)',
        'Estado de ánimo' => 'Mood',
    ];

    /** Pattern names (config/patrones.php) and the other "indicador" labels that go in an outgoing alert. */
    public const ETIQUETAS_ALERTA = [
        'Posible fiebre' => 'Possible fever',
        'Posible hipoxia' => 'Possible hypoxia',
        'Posible deshidratación' => 'Possible dehydration',
        'Posible estrés agudo' => 'Possible acute stress',
        'Posible irregularidad del pulso (requiere ECG)' => 'Possible pulse irregularity (requires ECG)',
        'Posible falta de sueño' => 'Possible sleep deprivation',
        'Posible fallo de sensor' => 'Possible sensor failure',
        'Autochequeo de síntomas' => 'Symptom self-check',
        'Registro de salud' => 'Health log',
    ];

    /** Escalation levels (Alertas::NIVELES) used in alertas_eventos.mensaje. */
    public const NIVELES = [
        'Autocuidado' => 'Self-care',
        'Comandante' => 'Commander',
        'Flight surgeon' => 'Flight Surgeon',
    ];

    /** Alert management verbs used in alertas_eventos.mensaje ("<indicator>: <verb>"). */
    public const VERBOS = [
        'reconocida' => 'acknowledged',
        'resuelta' => 'resolved',
        'reabierta' => 'reopened',
    ];

    /** Units that were translated (the ones that did not change, such as mmHg, are not listed). */
    public const UNIDADES = [
        'lpm' => 'bpm',
        '%/mes' => '%/month',
        '% misión' => '% of mission',
        'mSv/día' => 'mSv/day',
        'L/día' => 'L/day',
        'h/día' => 'h/day',
        'mSv/año de misión' => 'mSv/mission year',
        'mGy/año' => 'mGy/year',
        'años' => 'years',
        'días' => 'days',
        'días/semana' => 'days/week',
        'alimentos' => 'food items',
        '% de la base' => '% of baseline',
        '% del basal' => '% of baseline',
        '% de la tripulación' => '% of crew',
        '% de pre-misión' => '% of pre-mission',
        '% del requerimiento' => '% of requirement',
        '× peso corporal' => '× body weight',
    ];

    /** requisitos.revision of the standard rows. */
    public const REVISIONES = [
        'NASA-STD-3001 Vol. 1 Rev. C (aprobada 15-sep-2023)' => 'NASA-STD-3001 Vol. 1 Rev. C (approved 15 Sep 2023)',
    ];

    /** astronautas.rol is NOT here: it comes from Database\Seeders\ExpedicionSeeder::ROLES_ANTERIORES. */

    /**
     * Original Spanish requisitos.texto by stable `clave` (copied from the add_clave_to_requisitos migration, which
     * is the only place that knows them). The English text comes from RequisitosSeeder, by the same `clave`.
     */
    public const REQUISITOS_TEXTO_ANTERIOR = [
        'norma.radiacion.dosis_carrera' => 'Dosis efectiva de carrera',
        'norma.radiacion.dosis_evento_spe' => 'Dosis efectiva por evento de partículas solares',
        'norma.radiacion.gcr_espacio_libre' => 'GCR en espacio libre',
        'norma.radiacion.gcr_superficie' => 'GCR en superficie planetaria (sistemas habitables de más de 60 días)',
        'norma.radiacion.nucleares' => 'Tecnologías nucleares',
        'norma.radiacion.cristalino' => 'Cristalino: 1000 (30 días) / 2000 (1 año) / 4000 (carrera) mGy-Eq',
        'norma.radiacion.piel' => 'Piel: 1500 (30 días) / 3000 (1 año) / 6000 (carrera) mGy-Eq',
        'norma.radiacion.organos_sangre' => 'Órganos formadores de sangre: 250 (30 días) / 500 (1 año) / no aplica (carrera) mGy-Eq',
        'norma.radiacion.sistema_circulatorio' => 'Sistema circulatorio: 250 (30 días) / 500 (1 año) / 1000 (carrera) mGy-Eq',
        'norma.radiacion.sistema_nervioso_central' => 'Sistema nervioso central: 500 (30 días) / 1000 (1 año) / 1500 (carrera) mGy',
        'norma.radiacion.particulas_z_anual' => 'Partículas Z ≥ 10: límite anual',
        'norma.radiacion.particulas_z_carrera' => 'Partículas Z ≥ 10: límite de carrera',
        'norma.hueso.dmo_cadera_columna' => 'DMO de cadera y columna respecto al valor pre-misión',
        'norma.hueso.dmo_cuello_femoral' => 'DMO de cuello femoral respecto al valor pre-misión',
        'norma.aerobico.vo2max_eva_microgravedad' => 'VO₂máx para EVA en microgravedad',
        'norma.aerobico.vo2max_eva_superficie' => 'VO₂máx para EVA en superficie',
        'norma.aerobico.capacidad_vs_premision' => 'Capacidad aeróbica respecto a la pre-misión',
        'norma.fuerza.fuerza_vs_basal' => 'Fuerza respecto a los valores basales',
        'norma.fuerza.peso_muerto' => 'Peso muerto pre-misión: 1.0 (EVA microgravedad) / 1.3 (EVA superficie) / 1.6 (egreso sin asistencia) veces el peso corporal',
        'norma.fuerza.press_banca' => 'Press de banca pre-misión: 0.7 (EVA microgravedad) / 0.8 (EVA superficie) / 1.0 (egreso sin asistencia) veces el peso corporal',
        'norma.nutricion.requerimientos' => 'Cubrir los requerimientos calculados',
        'norma.sueno.limites' => 'Deben existir límites definidos de sueño (requisito cualitativo, sin número)',
        'norma.carga_trabajo.limites' => 'Deben existir límites definidos de carga de trabajo (requisito cualitativo, sin número)',
        'norma.conducta.limites' => 'Deben existir límites definidos de salud conductual (requisito cualitativo, sin número)',
        'norma.sensoriomotor.limites' => 'Deben existir límites definidos sensoriomotores (requisito cualitativo, sin número)',
        'norma.operativo.cmo_por_vehiculo' => 'Mínimo de CMO por vehículo',
        'norma.operativo.vitamina_d_dxa' => 'Vitamina D y DXA pre-misión',
        'hrp.hueso.perdida_observada' => 'Pérdida ósea observada',
        'hrp.aerobico.perdida_media' => 'Pérdida aeróbica media observada',
        'hrp.aerobico.proporcion_tripulacion' => 'Proporción de la tripulación que pierde más de 20 % de capacidad aeróbica',
        'hrp.sueno.observado_orbita' => 'Sueño real observado en órbita',
        'hrp.sueno.horas_asignadas' => 'Horas de sueño asignadas',
        'hrp.nutricion.alimentos_disponibles' => 'Alimentos disponibles a bordo',
        'guia.aerobico.vo2max_bajo_80' => 'VO₂máx bajo el 80 % de la base',
        'guia.aerobico.vo2max_bajo_minimo' => 'VO₂máx absoluto bajo el mínimo',
        'guia.fuerza.bajo_80' => 'Fuerza bajo el 80 % de la base',
        'guia.nutricion.calorias_bajo_90' => 'Calorías bajo el 90 % del requerimiento durante 3 días seguidos',
        'guia.hidratacion.diaria_baja' => 'Hidratación diaria baja',
        'guia.vision.grosor_retiniano' => 'SANS: aumento del grosor retiniano',
        'guia.vision.hipermetropico' => 'SANS: cambio hipermetrópico',
        'guia.sueno.pvt_lapsos' => 'PVT con lapsos mayores a 500 ms (severidad preventiva)',
        'guia.sueno.menor_6h_48h' => 'Sueño menor a 6 h por más de 48 h (severidad preventiva)',
        'guia.ejercicio.carga_ared' => 'Carga máxima del ARED',
        'guia.ejercicio.diario' => 'Ejercicio diario',
        'guia.ejercicio.dias_semana' => 'Ejercicio: 6–7 días por semana',
        'guia.ejercicio.t2_cevis' => 'T2 y CEVIS: 30–45 min con intervalos HIIT',
        'guia.posvuelo.duracion' => 'Duración del programa posvuelo',
        'referencia.presion_arterial.normal' => 'Normal: menor a 120/80 mmHg',
        'referencia.presion_arterial.elevada' => 'Elevada: sistólica 120–129 y diastólica menor a 80 mmHg',
        'referencia.presion_arterial.hipertension_1' => 'Hipertensión 1: sistólica 130–139 u diastólica 80–89 mmHg',
        'referencia.presion_arterial.hipertension_2' => 'Hipertensión 2: sistólica ≥ 140 o diastólica ≥ 90 mmHg',
        'referencia.presion_arterial.crisis' => 'Crisis: mayor a 180/120 mmHg',
        'referencia.pulso.apolo_rango' => 'Pulso de los Apolo (rango observado)',
        'referencia.pulso.apolo_descenso' => 'Pulso de los Apolo en el descenso (media aproximada)',
        'referencia.pulso.apolo_ascenso' => 'Pulso de los Apolo en el ascenso (rango)',
        'referencia.temperatura.central_orbita' => 'Temperatura central en órbita: de ~37.0 a ~38.0 °C tras ~2.5 meses; > 40 °C posible en ejercicio. La alerta es un salto sobre su propia base',
        'referencia.sueno.recomendado' => 'Sueño recomendado',
    ];

    /**
     * Sentence patterns with numbers generated by ResumenTripulante::razon(): [spanish regex, english sprintf template].
     * Anchored, exact wording. Captured "<value> <unit>" parts go through valorConUnidad().
     */
    public const RAZONES = [
        ['/^Pérdida de (.+): supera tu límite de (.+)$/u', 'Loss of %1$s: exceeds your limit of %2$s'],
        ['/^Pérdida de (.+): cerca de tu límite de (.+)$/u', 'Loss of %1$s: near your limit of %2$s'],
        ['/^(.+) mSv: (\d+) % de tu límite de misión \((.+) mSv\)$/u', '%1$s mSv: %2$s %% of your mission limit (%3$s mSv)'],
        ['/^(.+) supera tu máximo de (.+)$/u', '%1$s exceeds your maximum of %2$s'],
        ['/^(.+) está por debajo de tu mínimo de (.+)$/u', '%1$s is below your minimum of %2$s'],
        ['/^(.+) está cerca de tu límite de (.+)$/u', '%1$s is near your limit of %2$s'],
    ];

    /** mediciones.calidad_motivo (CalidadSenal) and the carga.dato_sospechoso copy of it. */
    public const MOTIVOS_CALIDAD = [
        ['/^Salto brusco: (.+) frente a (.+) en la lectura anterior \(máximo plausible (.+)\)\.$/u', 'Abrupt jump: %1$s vs %2$s in the previous reading (maximum plausible %3$s).'],
        ['/^Señal plana: (\d+) lecturas idénticas seguidas \((.+)\)\.$/u', 'Flat signal: %1$s identical consecutive readings (%2$s).'],
    ];

    /** Translates a trailing unit of "<value> <unit>" (exact unit match). Anything else is returned untouched. */
    public static function valorConUnidad(string $texto): string
    {
        foreach (self::UNIDADES as $es => $en) {
            if (str_ends_with($texto, ' '.$es)) {
                return substr($texto, 0, -strlen($es)).$en;
            }
        }

        return $texto;
    }

    /** Indicator label as ConsultasTierra::titulo() writes it inside a title ("Message · blood pressure and heart rate"). */
    public static function etiquetaTitulo(string $etiqueta, bool $espanol): string
    {
        $e = trim(preg_replace('/\s*\(.*?\)/u', '', $etiqueta));
        if ($espanol) {
            return mb_strtolower($e);
        }

        return preg_match('/^\p{Lu}\p{Ll}/u', $e) ? mb_strtolower(mb_substr($e, 0, 1)).mb_substr($e, 1) : $e;
    }

    /**
     * Old consultation-title fragment (lowercased Spanish indicator label) => English fragment.
     *
     * @return array<string, string>
     */
    public static function fragmentosTitulo(): array
    {
        $out = [];
        foreach (self::INDICADORES as $es => $en) {
            $out[self::etiquetaTitulo($es, true)] = self::etiquetaTitulo($en, false);
        }

        return $out;
    }
}
