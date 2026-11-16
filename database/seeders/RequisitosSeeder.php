<?php

namespace Database\Seeders;

use App\Models\Requisito;
use Illuminate\Database\Seeder;

/**
 * Requisitos de referencia. Todo queda validado=false (valor de ejemplo)
 * hasta revisión médica. Solo "V1 4027" tiene código confirmado.
 */
class RequisitosSeeder extends Seeder
{
    private const NORMA_REV = 'NASA-STD-3001 Vol. 1 Rev. C (aprobada 15-sep-2023)';
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
                ['texto' => $f['texto'], 'dominio' => $f['dominio'], 'tipo' => $tipo],
                $f + ['validado' => false]
            );
        }
    }

    private function n(string $dominio, string $texto, array $x = []): array
    {
        return ['tipo' => 'norma', 'dominio' => $dominio, 'texto' => $texto] + $x;
    }

    private function h(string $dominio, string $texto, array $x = []): array
    {
        return ['tipo' => 'hrp', 'dominio' => $dominio, 'texto' => $texto] + $x;
    }

    private function g(string $dominio, string $texto, array $x = []): array
    {
        return ['tipo' => 'guia', 'dominio' => $dominio, 'texto' => $texto] + $x;
    }

    private function r(string $dominio, string $texto, array $x = []): array
    {
        return ['tipo' => 'referencia', 'dominio' => $dominio, 'texto' => $texto] + $x;
    }

    private function filas(): array
    {
        return [
            // ---- norma ----
            $this->n('radiacion', 'Dosis efectiva de carrera', ['operador' => '<', 'valor' => 600, 'unidad' => 'mSv']),
            $this->n('radiacion', 'Dosis efectiva por evento de partículas solares', ['operador' => '<', 'valor' => 250, 'unidad' => 'mSv']),
            $this->n('radiacion', 'GCR en espacio libre', ['operador' => '<', 'valor' => 1.3, 'unidad' => 'mSv/día']),
            $this->n('radiacion', 'GCR en superficie planetaria (sistemas habitables de más de 60 días)', ['operador' => '<', 'valor' => 0.9, 'unidad' => 'mSv/día']),
            $this->n('radiacion', 'Tecnologías nucleares', ['operador' => '<', 'valor' => 20, 'unidad' => 'mSv/año de misión']),
            $this->n('radiacion', 'Cristalino: 1000 (30 días) / 2000 (1 año) / 4000 (carrera) mGy-Eq', ['unidad' => 'mGy-Eq']),
            $this->n('radiacion', 'Piel: 1500 (30 días) / 3000 (1 año) / 6000 (carrera) mGy-Eq', ['unidad' => 'mGy-Eq']),
            $this->n('radiacion', 'Órganos formadores de sangre: 250 (30 días) / 500 (1 año) / no aplica (carrera) mGy-Eq', ['unidad' => 'mGy-Eq']),
            $this->n('radiacion', 'Sistema circulatorio: 250 (30 días) / 500 (1 año) / 1000 (carrera) mGy-Eq', ['unidad' => 'mGy-Eq']),
            $this->n('radiacion', 'Sistema nervioso central: 500 (30 días) / 1000 (1 año) / 1500 (carrera) mGy', ['unidad' => 'mGy']),
            $this->n('radiacion', 'Partículas Z ≥ 10: límite anual', ['operador' => '<=', 'valor' => 100, 'unidad' => 'mGy/año']),
            $this->n('radiacion', 'Partículas Z ≥ 10: límite de carrera', ['operador' => '<=', 'valor' => 250, 'unidad' => 'mGy']),
            $this->n('hueso', 'DMO de cadera y columna respecto al valor pre-misión', ['codigo' => 'V1 4027', 'operador' => '>=', 'valor' => 95, 'unidad' => '% de pre-misión']),
            $this->n('hueso', 'DMO de cuello femoral respecto al valor pre-misión', ['operador' => '>=', 'valor' => 90, 'unidad' => '% de pre-misión']),
            $this->n('aerobico', 'VO₂máx para EVA en microgravedad', ['operador' => '>=', 'valor' => 32.9, 'unidad' => 'ml/min/kg']),
            $this->n('aerobico', 'VO₂máx para EVA en superficie', ['operador' => '>=', 'valor' => 36.5, 'unidad' => 'ml/min/kg']),
            $this->n('aerobico', 'Capacidad aeróbica respecto a la pre-misión', ['operador' => '>=', 'valor' => 80, 'unidad' => '% de pre-misión']),
            $this->n('fuerza', 'Fuerza respecto a los valores basales', ['operador' => '>=', 'valor' => 80, 'unidad' => '% del basal']),
            $this->n('fuerza', 'Peso muerto pre-misión: 1.0 (EVA microgravedad) / 1.3 (EVA superficie) / 1.6 (egreso sin asistencia) veces el peso corporal', ['unidad' => '× peso corporal']),
            $this->n('fuerza', 'Press de banca pre-misión: 0.7 (EVA microgravedad) / 0.8 (EVA superficie) / 1.0 (egreso sin asistencia) veces el peso corporal', ['unidad' => '× peso corporal']),
            $this->n('nutricion', 'Cubrir los requerimientos calculados', ['operador' => '>=', 'valor' => 100, 'unidad' => '% del requerimiento']),
            $this->n('sueno', 'Deben existir límites definidos de sueño (requisito cualitativo, sin número)', ['operador' => 'cualitativo']),
            $this->n('carga_trabajo', 'Deben existir límites definidos de carga de trabajo (requisito cualitativo, sin número)', ['operador' => 'cualitativo']),
            $this->n('conducta', 'Deben existir límites definidos de salud conductual (requisito cualitativo, sin número)', ['operador' => 'cualitativo']),
            $this->n('sensoriomotor', 'Deben existir límites definidos sensoriomotores (requisito cualitativo, sin número)', ['operador' => 'cualitativo']),
            $this->n('operativo', 'Mínimo de CMO por vehículo', ['operador' => '>=', 'valor' => 2, 'unidad' => 'CMO']),
            $this->n('operativo', 'Vitamina D y DXA pre-misión', ['operador' => 'cada', 'valor' => 3, 'unidad' => 'años']),

            // ---- hrp (solo contexto y proyección, nunca alertan) ----
            $this->h('hueso', 'Pérdida ósea observada', ['operador' => 'rango', 'valor' => 1, 'valor_max' => 1.5, 'unidad' => '%/mes']),
            $this->h('aerobico', 'Pérdida aeróbica media observada', ['operador' => 'rango', 'valor' => 10, 'valor_max' => 12, 'unidad' => '%']),
            $this->h('aerobico', 'Proporción de la tripulación que pierde más de 20 % de capacidad aeróbica', ['operador' => 'rango', 'valor' => 12, 'valor_max' => 38, 'unidad' => '% de la tripulación']),
            $this->h('sueno', 'Sueño real observado en órbita', ['operador' => 'rango', 'valor' => 6, 'valor_max' => 6.5, 'unidad' => 'h']),
            $this->h('sueno', 'Horas de sueño asignadas', ['operador' => '=', 'valor' => 8.5, 'unidad' => 'h']),
            $this->h('nutricion', 'Alimentos disponibles a bordo', ['operador' => '>', 'valor' => 200, 'unidad' => 'alimentos']),

            // ---- guia (documento "Datos médicos") ----
            $this->g('aerobico', 'VO₂máx bajo el 80 % de la base', ['operador' => '<', 'valor' => 80, 'unidad' => '% de la base']),
            $this->g('aerobico', 'VO₂máx absoluto bajo el mínimo', ['operador' => '<', 'valor' => 32.9, 'unidad' => 'ml/min/kg']),
            $this->g('fuerza', 'Fuerza bajo el 80 % de la base', ['operador' => '<', 'valor' => 80, 'unidad' => '% de la base']),
            $this->g('nutricion', 'Calorías bajo el 90 % del requerimiento durante 3 días seguidos', ['operador' => '<', 'valor' => 90, 'unidad' => '% del requerimiento']),
            $this->g('hidratacion', 'Hidratación diaria baja', ['operador' => '<', 'valor' => 2.0, 'unidad' => 'L/día']),
            $this->g('vision', 'SANS: aumento del grosor retiniano', ['operador' => '>=', 'valor' => 20, 'unidad' => 'µm']),
            $this->g('vision', 'SANS: cambio hipermetrópico', ['operador' => '>=', 'valor' => 0.50, 'unidad' => 'D']),
            $this->g('sueno', 'PVT con lapsos mayores a 500 ms (severidad preventiva)', ['operador' => '>', 'valor' => 500, 'unidad' => 'ms']),
            $this->g('sueno', 'Sueño menor a 6 h por más de 48 h (severidad preventiva)', ['operador' => '<', 'valor' => 6, 'unidad' => 'h']),
            $this->g('ejercicio', 'Carga máxima del ARED', ['operador' => '<=', 'valor' => 272, 'unidad' => 'kg']),
            $this->g('ejercicio', 'Ejercicio diario', ['operador' => '=', 'valor' => 2.5, 'unidad' => 'h/día']),
            $this->g('ejercicio', 'Ejercicio: 6–7 días por semana', ['operador' => 'rango', 'valor' => 6, 'valor_max' => 7, 'unidad' => 'días/semana']),
            $this->g('ejercicio', 'T2 y CEVIS: 30–45 min con intervalos HIIT', ['operador' => 'rango', 'valor' => 30, 'valor_max' => 45, 'unidad' => 'min']),
            $this->g('posvuelo', 'Duración del programa posvuelo', ['operador' => '=', 'valor' => 45, 'unidad' => 'días']),

            // ---- referencia clínica ----
            $this->r('presion_arterial', 'Normal: menor a 120/80 mmHg', ['operador' => '<', 'unidad' => 'mmHg']),
            $this->r('presion_arterial', 'Elevada: sistólica 120–129 y diastólica menor a 80 mmHg', ['operador' => 'rango', 'unidad' => 'mmHg']),
            $this->r('presion_arterial', 'Hipertensión 1: sistólica 130–139 u diastólica 80–89 mmHg', ['operador' => 'rango', 'unidad' => 'mmHg']),
            $this->r('presion_arterial', 'Hipertensión 2: sistólica ≥ 140 o diastólica ≥ 90 mmHg', ['operador' => '>=', 'unidad' => 'mmHg']),
            $this->r('presion_arterial', 'Crisis: mayor a 180/120 mmHg', ['operador' => '>', 'unidad' => 'mmHg']),
            $this->r('pulso', 'Pulso de los Apolo (rango observado)', ['operador' => 'rango', 'valor' => 66, 'valor_max' => 144, 'unidad' => 'lpm']),
            $this->r('pulso', 'Pulso de los Apolo en el descenso (media aproximada)', ['operador' => '~', 'valor' => 96, 'unidad' => 'lpm']),
            $this->r('pulso', 'Pulso de los Apolo en el ascenso (rango)', ['operador' => 'rango', 'valor' => 70, 'valor_max' => 109, 'unidad' => 'lpm']),
            $this->r('temperatura', 'Temperatura central en órbita: de ~37.0 a ~38.0 °C tras ~2.5 meses; > 40 °C posible en ejercicio. La alerta es un salto sobre su propia base', ['operador' => 'rango', 'valor' => 37.0, 'valor_max' => 38.0, 'unidad' => '°C']),
            $this->r('sueno', 'Sueño recomendado', ['operador' => 'rango', 'valor' => 7, 'valor_max' => 9, 'unidad' => 'h']),
        ];
    }
}
