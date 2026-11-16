<?php

namespace Database\Seeders;

use App\Models\Requisito;
use Illuminate\Database\Seeder;

/**
 * Reference requirements, paraphrased from the project's reference document (faithful English
 * translation of the original Spanish text; NOT verbatim quotes of the standard). Everything is
 * validado=false (example value) until medical review. Only "V1 4027" has a confirmed code.
 *
 * Rows are identified by the stable `clave` (tipo.dominio.slug), never by their text, so the visible
 * text can be reworded or translated without breaking config/trazabilidad.php or config/ventanas.php.
 */
class RequisitosSeeder extends Seeder
{
    private const NORMA_REV = 'NASA-STD-3001 Vol. 1 Rev. C (approved 15 Sep 2023)';
    private const NORMA_URL = 'https://www.nasa.gov/wp-content/uploads/2023/11/nasa-std-3001-vol-1-rev-c-with-signature.pdf';

    public function run(): void
    {
        foreach ($this->filas() as $f) {
            $tipo = $f['tipo'];
            $f += [
                'codigo' => null, 'operador' => null, 'valor' => null,
                'valor_max' => null, 'unidad' => null, 'revision' => null,
                'fuente_url' => null,
            ];
            if ($tipo === 'norma') {
                $f['revision'] = self::NORMA_REV;
                $f['fuente_url'] = self::NORMA_URL;
            }

            Requisito::updateOrCreate(
                ['clave' => $f['clave']],
                $f + ['validado' => false]
            );
        }
    }

    private function fila(string $tipo, string $dominio, string $slug, string $texto, array $x): array
    {
        return ['clave' => "$tipo.$dominio.$slug", 'tipo' => $tipo, 'dominio' => $dominio, 'texto' => $texto] + $x;
    }

    private function n(string $dominio, string $slug, string $texto, array $x = []): array
    {
        return $this->fila('norma', $dominio, $slug, $texto, $x);
    }

    private function h(string $dominio, string $slug, string $texto, array $x = []): array
    {
        return $this->fila('hrp', $dominio, $slug, $texto, $x);
    }

    private function g(string $dominio, string $slug, string $texto, array $x = []): array
    {
        return $this->fila('guia', $dominio, $slug, $texto, $x);
    }

    private function r(string $dominio, string $slug, string $texto, array $x = []): array
    {
        return $this->fila('referencia', $dominio, $slug, $texto, $x);
    }

    private function filas(): array
    {
        return [
            // ---- norma (standard) ----
            $this->n('radiacion', 'dosis_carrera', 'Career effective dose', ['operador' => '<', 'valor' => 600, 'unidad' => 'mSv']),
            $this->n('radiacion', 'dosis_evento_spe', 'Effective dose per solar particle event', ['operador' => '<', 'valor' => 250, 'unidad' => 'mSv']),
            $this->n('radiacion', 'gcr_espacio_libre', 'GCR in free space', ['operador' => '<', 'valor' => 1.3, 'unidad' => 'mSv/day']),
            $this->n('radiacion', 'gcr_superficie', 'GCR on a planetary surface (habitable systems of more than 60 days)', ['operador' => '<', 'valor' => 0.9, 'unidad' => 'mSv/day']),
            $this->n('radiacion', 'nucleares', 'Nuclear technologies', ['operador' => '<', 'valor' => 20, 'unidad' => 'mSv/mission year']),
            $this->n('radiacion', 'cristalino', 'Lens: 1,000 (30 days) / 2,000 (1 year) / 4,000 (career) mGy-Eq', ['unidad' => 'mGy-Eq']),
            $this->n('radiacion', 'piel', 'Skin: 1,500 (30 days) / 3,000 (1 year) / 6,000 (career) mGy-Eq', ['unidad' => 'mGy-Eq']),
            $this->n('radiacion', 'organos_sangre', 'Blood-forming organs: 250 (30 days) / 500 (1 year) / not applicable (career) mGy-Eq', ['unidad' => 'mGy-Eq']),
            $this->n('radiacion', 'sistema_circulatorio', 'Circulatory system: 250 (30 days) / 500 (1 year) / 1,000 (career) mGy-Eq', ['unidad' => 'mGy-Eq']),
            $this->n('radiacion', 'sistema_nervioso_central', 'Central nervous system: 500 (30 days) / 1,000 (1 year) / 1,500 (career) mGy', ['unidad' => 'mGy']),
            $this->n('radiacion', 'particulas_z_anual', 'Z ≥ 10 particles: annual limit', ['operador' => '<=', 'valor' => 100, 'unidad' => 'mGy/year']),
            $this->n('radiacion', 'particulas_z_carrera', 'Z ≥ 10 particles: career limit', ['operador' => '<=', 'valor' => 250, 'unidad' => 'mGy']),
            $this->n('hueso', 'dmo_cadera_columna', 'Hip and spine BMD relative to the pre-mission value', ['codigo' => 'V1 4027', 'operador' => '>=', 'valor' => 95, 'unidad' => '% of pre-mission']),
            $this->n('hueso', 'dmo_cuello_femoral', 'Femoral neck BMD relative to the pre-mission value', ['operador' => '>=', 'valor' => 90, 'unidad' => '% of pre-mission']),
            $this->n('aerobico', 'vo2max_eva_microgravedad', 'VO₂max for EVA in microgravity', ['operador' => '>=', 'valor' => 32.9, 'unidad' => 'ml/min/kg']),
            $this->n('aerobico', 'vo2max_eva_superficie', 'VO₂max for EVA on a planetary surface', ['operador' => '>=', 'valor' => 36.5, 'unidad' => 'ml/min/kg']),
            $this->n('aerobico', 'capacidad_vs_premision', 'Aerobic capacity relative to pre-mission', ['operador' => '>=', 'valor' => 80, 'unidad' => '% of pre-mission']),
            $this->n('fuerza', 'fuerza_vs_basal', 'Strength relative to baseline values', ['operador' => '>=', 'valor' => 80, 'unidad' => '% of baseline']),
            $this->n('fuerza', 'peso_muerto', 'Pre-mission deadlift: 1.0 (EVA in microgravity) / 1.3 (EVA on a surface) / 1.6 (unassisted egress) times body weight', ['unidad' => '× body weight']),
            $this->n('fuerza', 'press_banca', 'Pre-mission bench press: 0.7 (EVA in microgravity) / 0.8 (EVA on a surface) / 1.0 (unassisted egress) times body weight', ['unidad' => '× body weight']),
            $this->n('nutricion', 'requerimientos', 'Meet the calculated requirements', ['operador' => '>=', 'valor' => 100, 'unidad' => '% of requirement']),
            $this->n('sueno', 'limites', 'Defined sleep limits must exist (qualitative requirement, no number)', ['operador' => 'cualitativo']),
            $this->n('carga_trabajo', 'limites', 'Defined workload limits must exist (qualitative requirement, no number)', ['operador' => 'cualitativo']),
            $this->n('conducta', 'limites', 'Defined behavioral health limits must exist (qualitative requirement, no number)', ['operador' => 'cualitativo']),
            $this->n('sensoriomotor', 'limites', 'Defined sensorimotor limits must exist (qualitative requirement, no number)', ['operador' => 'cualitativo']),
            $this->n('operativo', 'cmo_por_vehiculo', 'Minimum number of CMOs per vehicle', ['operador' => '>=', 'valor' => 2, 'unidad' => 'CMO']),
            $this->n('operativo', 'vitamina_d_dxa', 'Pre-mission vitamin D and DXA', ['operador' => 'cada', 'valor' => 3, 'unidad' => 'years']),

            // ---- hrp (context and projection only, never trigger alerts) ----
            $this->h('hueso', 'perdida_observada', 'Observed bone loss', ['operador' => 'rango', 'valor' => 1, 'valor_max' => 1.5, 'unidad' => '%/month']),
            $this->h('aerobico', 'perdida_media', 'Observed mean aerobic loss', ['operador' => 'rango', 'valor' => 10, 'valor_max' => 12, 'unidad' => '%']),
            $this->h('aerobico', 'proporcion_tripulacion', 'Share of the crew losing more than 20 % of aerobic capacity', ['operador' => 'rango', 'valor' => 12, 'valor_max' => 38, 'unidad' => '% of crew']),
            $this->h('sueno', 'observado_orbita', 'Actual sleep observed in orbit', ['operador' => 'rango', 'valor' => 6, 'valor_max' => 6.5, 'unidad' => 'h']),
            $this->h('sueno', 'horas_asignadas', 'Scheduled sleep hours', ['operador' => '=', 'valor' => 8.5, 'unidad' => 'h']),
            $this->h('nutricion', 'alimentos_disponibles', 'Food items available on board', ['operador' => '>', 'valor' => 200, 'unidad' => 'food items']),

            // ---- guia (project guide, "Medical data" document) ----
            $this->g('aerobico', 'vo2max_bajo_80', 'VO₂max below 80 % of baseline', ['operador' => '<', 'valor' => 80, 'unidad' => '% of baseline']),
            $this->g('aerobico', 'vo2max_bajo_minimo', 'Absolute VO₂max below the minimum', ['operador' => '<', 'valor' => 32.9, 'unidad' => 'ml/min/kg']),
            $this->g('fuerza', 'bajo_80', 'Strength below 80 % of baseline', ['operador' => '<', 'valor' => 80, 'unidad' => '% of baseline']),
            $this->g('nutricion', 'calorias_bajo_90', 'Calories below 90 % of requirement for 3 consecutive days', ['operador' => '<', 'valor' => 90, 'unidad' => '% of requirement']),
            $this->g('hidratacion', 'diaria_baja', 'Low daily hydration', ['operador' => '<', 'valor' => 2.0, 'unidad' => 'L/day']),
            $this->g('vision', 'grosor_retiniano', 'SANS: increased retinal thickness', ['operador' => '>=', 'valor' => 20, 'unidad' => 'µm']),
            $this->g('vision', 'hipermetropico', 'SANS: hyperopic shift', ['operador' => '>=', 'valor' => 0.50, 'unidad' => 'D']),
            $this->g('sueno', 'pvt_lapsos', 'PVT with lapses longer than 500 ms (preventive severity)', ['operador' => '>', 'valor' => 500, 'unidad' => 'ms']),
            $this->g('sueno', 'menor_6h_48h', 'Sleep under 6 h for more than 48 h (preventive severity)', ['operador' => '<', 'valor' => 6, 'unidad' => 'h']),
            $this->g('ejercicio', 'carga_ared', 'Maximum ARED load', ['operador' => '<=', 'valor' => 272, 'unidad' => 'kg']),
            $this->g('ejercicio', 'diario', 'Daily exercise', ['operador' => '=', 'valor' => 2.5, 'unidad' => 'h/day']),
            $this->g('ejercicio', 'dias_semana', 'Exercise: 6–7 days per week', ['operador' => 'rango', 'valor' => 6, 'valor_max' => 7, 'unidad' => 'days/week']),
            $this->g('ejercicio', 't2_cevis', 'T2 and CEVIS: 30–45 min with HIIT intervals', ['operador' => 'rango', 'valor' => 30, 'valor_max' => 45, 'unidad' => 'min']),
            $this->g('posvuelo', 'duracion', 'Post-flight program duration', ['operador' => '=', 'valor' => 45, 'unidad' => 'days']),

            // ---- referencia (clinical reference) ----
            $this->r('presion_arterial', 'normal', 'Normal: below 120/80 mmHg', ['operador' => '<', 'unidad' => 'mmHg']),
            $this->r('presion_arterial', 'elevada', 'Elevated: systolic 120–129 and diastolic below 80 mmHg', ['operador' => 'rango', 'unidad' => 'mmHg']),
            $this->r('presion_arterial', 'hipertension_1', 'Hypertension stage 1: systolic 130–139 or diastolic 80–89 mmHg', ['operador' => 'rango', 'unidad' => 'mmHg']),
            $this->r('presion_arterial', 'hipertension_2', 'Hypertension stage 2: systolic ≥ 140 or diastolic ≥ 90 mmHg', ['operador' => '>=', 'unidad' => 'mmHg']),
            $this->r('presion_arterial', 'crisis', 'Crisis: above 180/120 mmHg', ['operador' => '>', 'unidad' => 'mmHg']),
            $this->r('pulso', 'apolo_rango', 'Apollo astronaut heart rate (observed range)', ['operador' => 'rango', 'valor' => 66, 'valor_max' => 144, 'unidad' => 'bpm']),
            $this->r('pulso', 'apolo_descenso', 'Apollo astronaut heart rate during descent (approximate mean)', ['operador' => '~', 'valor' => 96, 'unidad' => 'bpm']),
            $this->r('pulso', 'apolo_ascenso', 'Apollo astronaut heart rate during ascent (range)', ['operador' => 'rango', 'valor' => 70, 'valor_max' => 109, 'unidad' => 'bpm']),
            $this->r('temperatura', 'central_orbita', 'Core temperature in orbit: from ~37.0 to ~38.0 °C after ~2.5 months; > 40 °C possible during exercise. The alert is a jump over the individual baseline', ['operador' => 'rango', 'valor' => 37.0, 'valor_max' => 38.0, 'unidad' => '°C']),
            $this->r('sueno', 'recomendado', 'Recommended sleep', ['operador' => 'rango', 'valor' => 7, 'valor_max' => 9, 'unidad' => 'h']),
        ];
    }
}
