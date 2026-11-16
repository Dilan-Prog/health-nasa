<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Adds a stable, language-independent key (`clave`) to `requisitos`. Existing rows are updated in place
 | (never deleted or recreated: `evaluaciones.requisito_id` points at them). The mapping uses the ORIGINAL
 | Spanish tipo + dominio + texto, so it only matches rows seeded before the interface was translated.
 */
return new class extends Migration
{
    /** @var array<int, array{0:string,1:string,2:string,3:string}> tipo, dominio, original texto, clave */
    private const MAPA = [
            ['norma', 'radiacion', 'Dosis efectiva de carrera', 'norma.radiacion.dosis_carrera'],
            ['norma', 'radiacion', 'Dosis efectiva por evento de partículas solares', 'norma.radiacion.dosis_evento_spe'],
            ['norma', 'radiacion', 'GCR en espacio libre', 'norma.radiacion.gcr_espacio_libre'],
            ['norma', 'radiacion', 'GCR en superficie planetaria (sistemas habitables de más de 60 días)', 'norma.radiacion.gcr_superficie'],
            ['norma', 'radiacion', 'Tecnologías nucleares', 'norma.radiacion.nucleares'],
            ['norma', 'radiacion', 'Cristalino: 1000 (30 días) / 2000 (1 año) / 4000 (carrera) mGy-Eq', 'norma.radiacion.cristalino'],
            ['norma', 'radiacion', 'Piel: 1500 (30 días) / 3000 (1 año) / 6000 (carrera) mGy-Eq', 'norma.radiacion.piel'],
            ['norma', 'radiacion', 'Órganos formadores de sangre: 250 (30 días) / 500 (1 año) / no aplica (carrera) mGy-Eq', 'norma.radiacion.organos_sangre'],
            ['norma', 'radiacion', 'Sistema circulatorio: 250 (30 días) / 500 (1 año) / 1000 (carrera) mGy-Eq', 'norma.radiacion.sistema_circulatorio'],
            ['norma', 'radiacion', 'Sistema nervioso central: 500 (30 días) / 1000 (1 año) / 1500 (carrera) mGy', 'norma.radiacion.sistema_nervioso_central'],
            ['norma', 'radiacion', 'Partículas Z ≥ 10: límite anual', 'norma.radiacion.particulas_z_anual'],
            ['norma', 'radiacion', 'Partículas Z ≥ 10: límite de carrera', 'norma.radiacion.particulas_z_carrera'],
            ['norma', 'hueso', 'DMO de cadera y columna respecto al valor pre-misión', 'norma.hueso.dmo_cadera_columna'],
            ['norma', 'hueso', 'DMO de cuello femoral respecto al valor pre-misión', 'norma.hueso.dmo_cuello_femoral'],
            ['norma', 'aerobico', 'VO₂máx para EVA en microgravedad', 'norma.aerobico.vo2max_eva_microgravedad'],
            ['norma', 'aerobico', 'VO₂máx para EVA en superficie', 'norma.aerobico.vo2max_eva_superficie'],
            ['norma', 'aerobico', 'Capacidad aeróbica respecto a la pre-misión', 'norma.aerobico.capacidad_vs_premision'],
            ['norma', 'fuerza', 'Fuerza respecto a los valores basales', 'norma.fuerza.fuerza_vs_basal'],
            ['norma', 'fuerza', 'Peso muerto pre-misión: 1.0 (EVA microgravedad) / 1.3 (EVA superficie) / 1.6 (egreso sin asistencia) veces el peso corporal', 'norma.fuerza.peso_muerto'],
            ['norma', 'fuerza', 'Press de banca pre-misión: 0.7 (EVA microgravedad) / 0.8 (EVA superficie) / 1.0 (egreso sin asistencia) veces el peso corporal', 'norma.fuerza.press_banca'],
            ['norma', 'nutricion', 'Cubrir los requerimientos calculados', 'norma.nutricion.requerimientos'],
            ['norma', 'sueno', 'Deben existir límites definidos de sueño (requisito cualitativo, sin número)', 'norma.sueno.limites'],
            ['norma', 'carga_trabajo', 'Deben existir límites definidos de carga de trabajo (requisito cualitativo, sin número)', 'norma.carga_trabajo.limites'],
            ['norma', 'conducta', 'Deben existir límites definidos de salud conductual (requisito cualitativo, sin número)', 'norma.conducta.limites'],
            ['norma', 'sensoriomotor', 'Deben existir límites definidos sensoriomotores (requisito cualitativo, sin número)', 'norma.sensoriomotor.limites'],
            ['norma', 'operativo', 'Mínimo de CMO por vehículo', 'norma.operativo.cmo_por_vehiculo'],
            ['norma', 'operativo', 'Vitamina D y DXA pre-misión', 'norma.operativo.vitamina_d_dxa'],
            ['hrp', 'hueso', 'Pérdida ósea observada', 'hrp.hueso.perdida_observada'],
            ['hrp', 'aerobico', 'Pérdida aeróbica media observada', 'hrp.aerobico.perdida_media'],
            ['hrp', 'aerobico', 'Proporción de la tripulación que pierde más de 20 % de capacidad aeróbica', 'hrp.aerobico.proporcion_tripulacion'],
            ['hrp', 'sueno', 'Sueño real observado en órbita', 'hrp.sueno.observado_orbita'],
            ['hrp', 'sueno', 'Horas de sueño asignadas', 'hrp.sueno.horas_asignadas'],
            ['hrp', 'nutricion', 'Alimentos disponibles a bordo', 'hrp.nutricion.alimentos_disponibles'],
            ['guia', 'aerobico', 'VO₂máx bajo el 80 % de la base', 'guia.aerobico.vo2max_bajo_80'],
            ['guia', 'aerobico', 'VO₂máx absoluto bajo el mínimo', 'guia.aerobico.vo2max_bajo_minimo'],
            ['guia', 'fuerza', 'Fuerza bajo el 80 % de la base', 'guia.fuerza.bajo_80'],
            ['guia', 'nutricion', 'Calorías bajo el 90 % del requerimiento durante 3 días seguidos', 'guia.nutricion.calorias_bajo_90'],
            ['guia', 'hidratacion', 'Hidratación diaria baja', 'guia.hidratacion.diaria_baja'],
            ['guia', 'vision', 'SANS: aumento del grosor retiniano', 'guia.vision.grosor_retiniano'],
            ['guia', 'vision', 'SANS: cambio hipermetrópico', 'guia.vision.hipermetropico'],
            ['guia', 'sueno', 'PVT con lapsos mayores a 500 ms (severidad preventiva)', 'guia.sueno.pvt_lapsos'],
            ['guia', 'sueno', 'Sueño menor a 6 h por más de 48 h (severidad preventiva)', 'guia.sueno.menor_6h_48h'],
            ['guia', 'ejercicio', 'Carga máxima del ARED', 'guia.ejercicio.carga_ared'],
            ['guia', 'ejercicio', 'Ejercicio diario', 'guia.ejercicio.diario'],
            ['guia', 'ejercicio', 'Ejercicio: 6–7 días por semana', 'guia.ejercicio.dias_semana'],
            ['guia', 'ejercicio', 'T2 y CEVIS: 30–45 min con intervalos HIIT', 'guia.ejercicio.t2_cevis'],
            ['guia', 'posvuelo', 'Duración del programa posvuelo', 'guia.posvuelo.duracion'],
            ['referencia', 'presion_arterial', 'Normal: menor a 120/80 mmHg', 'referencia.presion_arterial.normal'],
            ['referencia', 'presion_arterial', 'Elevada: sistólica 120–129 y diastólica menor a 80 mmHg', 'referencia.presion_arterial.elevada'],
            ['referencia', 'presion_arterial', 'Hipertensión 1: sistólica 130–139 u diastólica 80–89 mmHg', 'referencia.presion_arterial.hipertension_1'],
            ['referencia', 'presion_arterial', 'Hipertensión 2: sistólica ≥ 140 o diastólica ≥ 90 mmHg', 'referencia.presion_arterial.hipertension_2'],
            ['referencia', 'presion_arterial', 'Crisis: mayor a 180/120 mmHg', 'referencia.presion_arterial.crisis'],
            ['referencia', 'pulso', 'Pulso de los Apolo (rango observado)', 'referencia.pulso.apolo_rango'],
            ['referencia', 'pulso', 'Pulso de los Apolo en el descenso (media aproximada)', 'referencia.pulso.apolo_descenso'],
            ['referencia', 'pulso', 'Pulso de los Apolo en el ascenso (rango)', 'referencia.pulso.apolo_ascenso'],
            ['referencia', 'temperatura', 'Temperatura central en órbita: de ~37.0 a ~38.0 °C tras ~2.5 meses; > 40 °C posible en ejercicio. La alerta es un salto sobre su propia base', 'referencia.temperatura.central_orbita'],
            ['referencia', 'sueno', 'Sueño recomendado', 'referencia.sueno.recomendado'],
    ];

    public function up(): void
    {
        Schema::table('requisitos', function (Blueprint $table) {
            $table->string('clave', 100)->nullable()->after('id');
        });

        $sinMapear = $this->asignarClaves();

        if ($sinMapear !== []) {
            // Undo the column so the migration can be retried after fixing the rows.
            Schema::table('requisitos', function (Blueprint $table) {
                $table->dropColumn('clave');
            });
            throw new RuntimeException(
                'add_clave_to_requisitos: cannot map these requisitos rows to a stable key (text differs from the original seed): '
                .implode('; ', $sinMapear)
            );
        }

        Schema::table('requisitos', function (Blueprint $table) {
            $table->unique('clave');
        });
    }

    /**
     * Assigns `clave` to every existing row by UPDATE (ids are preserved). Returns the rows that could not be
     * mapped (empty when everything was mapped). Public so it can be tested without altering the schema.
     *
     * @return array<int, string>
     */
    public function asignarClaves(): array
    {
        $sinMapear = [];
        foreach (DB::table('requisitos')->get(['id', 'tipo', 'dominio', 'texto']) as $fila) {
            $clave = null;
            foreach (self::MAPA as [$tipo, $dominio, $texto, $k]) {
                if ($fila->tipo === $tipo && $fila->dominio === $dominio && $fila->texto === $texto) {
                    $clave = $k;
                    break;
                }
            }
            if ($clave === null) {
                $sinMapear[] = "#{$fila->id} {$fila->tipo}|{$fila->dominio}|{$fila->texto}";
                continue;
            }
            DB::table('requisitos')->where('id', $fila->id)->update(['clave' => $clave]);
        }

        return $sinMapear;
    }

    /** Original (Spanish) seed rows and their keys: tipo, dominio, texto, clave. */
    public function mapa(): array
    {
        return self::MAPA;
    }

    public function down(): void
    {
        Schema::table('requisitos', function (Blueprint $table) {
            $table->dropUnique(['clave']);
            $table->dropColumn('clave');
        });
    }
};
