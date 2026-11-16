<?php

namespace Tests\Feature;

use App\Models\Requisito;
use Database\Seeders\RequisitosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_requirements_of_each_type(): void
    {
        $this->seed(RequisitosSeeder::class);

        foreach (['norma', 'hrp', 'guia', 'referencia'] as $type) {
            $this->assertGreaterThan(0, Requisito::tipo($type)->count(), "no requirements of type {$type}");
        }
    }

    public function test_only_one_code_exists_and_nothing_is_validated(): void
    {
        $this->seed(RequisitosSeeder::class);

        $codes = Requisito::whereNotNull('codigo')->pluck('codigo');
        $this->assertSame(['V1 4027'], $codes->all());
        $this->assertSame(0, Requisito::where('validado', true)->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RequisitosSeeder::class);
        $count = Requisito::count();
        $this->seed(RequisitosSeeder::class);

        $this->assertSame($count, Requisito::count());
    }

    public function test_source_label_is_displayed(): void
    {
        $this->assertSame('NASA-STD-3001 Vol. 1 Rev. C', (new Requisito(['tipo' => 'norma']))->fuente_etiqueta);
        $this->assertSame('HRP (observation)', (new Requisito(['tipo' => 'hrp']))->fuente_etiqueta);
        $this->assertSame('Internal guide (Medical data)', (new Requisito(['tipo' => 'guia']))->fuente_etiqueta);
        $this->assertSame('Clinical reference', (new Requisito(['tipo' => 'referencia']))->fuente_etiqueta);
    }
}
