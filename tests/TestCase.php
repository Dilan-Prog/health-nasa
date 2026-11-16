<?php

namespace Tests;

use App\Services\Salud\ResolvedorRequisito;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        // La caché estática del resolvedor sobrevive entre tests, pero las filas se revierten con la transacción.
        ResolvedorRequisito::olvidar();
    }
}
