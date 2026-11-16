<?php

namespace Tests\Feature;

use App\Support\MusculosCuerpo;
use Tests\TestCase;

class DashboardConfigTest extends TestCase
{
    private const KEYS = [
        'presion_arterial', 'frecuencia_cardiaca', 'saturacion_o2', 'glucosa', 'temperatura',
        'imc', 'perdida_osea', 'masa_muscular', 'radiacion_diaria', 'dosis_acumulada',
    ];

    public function test_ten_indicators_in_order_and_all_exist_in_thresholds(): void
    {
        $this->assertSame(self::KEYS, array_keys(config('panel.indicadores')));
        foreach (self::KEYS as $key) {
            $this->assertArrayHasKey($key, config('umbrales.indicadores'));
        }
        $this->assertSame(408, config('panel.orbita_km'));
    }

    public function test_every_indicator_has_the_contract_fields(): void
    {
        foreach (config('panel.indicadores') as $key => $i) {
            foreach (['etiqueta', 'icono', 'unidad', 'rango', 'zona', 'musculos', 'acciones', 'base'] as $field) {
                $this->assertNotEmpty($i[$field], "{$key}.{$field}");
            }
            $this->assertIsBool($i['perdida'], "{$key}.perdida");
            $this->assertGreaterThanOrEqual(2, count($i['acciones']));
            $this->assertLessThanOrEqual(3, count($i['acciones']));
        }
    }

    public function test_all_muscles_exist_in_the_silhouette(): void
    {
        $valid = MusculosCuerpo::ids();
        foreach (config('panel.indicadores') as $key => $i) {
            foreach ($i['musculos'] as $id) {
                $this->assertContains($id, $valid, "{$key}: muscle '{$id}' does not exist in MusculosCuerpo");
            }
        }
    }
}
