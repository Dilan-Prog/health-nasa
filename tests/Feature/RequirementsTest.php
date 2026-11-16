<?php

namespace Tests\Feature;

use App\Models\Requisito;
use Database\Seeders\RequisitosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitosTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_seeder_crea_requisitos_de_cada_tipo(): void
    {
        $this->seed(RequisitosSeeder::class);

        foreach (['norma', 'hrp', 'guia', 'referencia'] as $tipo) {
            $this->assertGreaterThan(0, Requisito::tipo($tipo)->count(), "sin requisitos {$tipo}");
        }
    }

    public function test_solo_hay_un_codigo_y_nada_esta_validado(): void
    {
        $this->seed(RequisitosSeeder::class);

        $codigos = Requisito::whereNotNull('codigo')->pluck('codigo');
        $this->assertSame(['V1 4027'], $codigos->all());
        $this->assertSame(0, Requisito::where('validado', true)->count());
    }

    public function test_el_seeder_es_idempotente(): void
    {
        $this->seed(RequisitosSeeder::class);
        $total = Requisito::count();
        $this->seed(RequisitosSeeder::class);

        $this->assertSame($total, Requisito::count());
    }

    public function test_etiqueta_visible_de_la_fuente(): void
    {
        $this->assertSame('NASA-STD-3001 Vol. 1 Rev. C', (new Requisito(['tipo' => 'norma']))->fuente_etiqueta);
        $this->assertSame('HRP (observación)', (new Requisito(['tipo' => 'hrp']))->fuente_etiqueta);
        $this->assertSame('Guía interna (Datos médicos)', (new Requisito(['tipo' => 'guia']))->fuente_etiqueta);
        $this->assertSame('Referencia clínica', (new Requisito(['tipo' => 'referencia']))->fuente_etiqueta);
    }
}
