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
        // The resolver static cache survives between tests, but the rows are rolled back with the transaction.
        ResolvedorRequisito::olvidar();
    }
}
