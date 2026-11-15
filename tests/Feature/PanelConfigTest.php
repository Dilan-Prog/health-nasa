<?php

namespace Tests\Feature;

use App\Support\MusculosCuerpo;
use Tests\TestCase;

class PanelConfigTest extends TestCase
{
    private const CLAVES = [
        'presion_arterial', 'frecuencia_cardiaca', 'saturacion_o2', 'glucosa', 'temperatura',
        'imc', 'perdida_osea', 'masa_muscular', 'radiacion_diaria', 'dosis_acumulada',
    ];

    public function test_diez_indicadores_en_orden_y_todos_existen_en_umbrales(): void
    {
        $this->assertSame(self::CLAVES, array_keys(config('panel.indicadores')));
        foreach (self::CLAVES as $clave) {
            $this->assertArrayHasKey($clave, config('umbrales.indicadores'));
        }
        $this->assertSame(408, config('panel.orbita_km'));
    }

    public function test_cada_indicador_tiene_los_campos_del_contrato(): void
    {
        foreach (config('panel.indicadores') as $clave => $i) {
            foreach (['etiqueta', 'icono', 'unidad', 'rango', 'zona', 'musculos', 'acciones', 'base'] as $campo) {
                $this->assertNotEmpty($i[$campo], "{$clave}.{$campo}");
            }
            $this->assertIsBool($i['perdida'], "{$clave}.perdida");
            $this->assertGreaterThanOrEqual(2, count($i['acciones']));
            $this->assertLessThanOrEqual(3, count($i['acciones']));
        }
    }

    public function test_todos_los_musculos_existen_en_la_silueta(): void
    {
        $validos = MusculosCuerpo::ids();
        foreach (config('panel.indicadores') as $clave => $i) {
            foreach ($i['musculos'] as $id) {
                $this->assertContains($id, $validos, "{$clave}: músculo '{$id}' no existe en MusculosCuerpo");
            }
        }
    }
}
