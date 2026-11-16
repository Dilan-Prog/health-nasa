<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Garantiza que los tests nunca corran contra la base real, donde la simulación escribe en vivo. */
class BaseDePruebasTest extends TestCase
{
    public function test_los_tests_usan_la_base_de_pruebas_y_no_la_real(): void
    {
        $this->assertSame('healts_nasa_test', DB::connection()->getDatabaseName());
        $this->assertSame('log', config('broadcasting.default'));
    }
}
