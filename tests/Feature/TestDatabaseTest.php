<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Guarantees that tests never run against the real database, where the simulation writes live. */
class TestDatabaseTest extends TestCase
{
    public function test_tests_use_the_test_database_not_the_real_one(): void
    {
        $this->assertSame('healts_nasa_test', DB::connection()->getDatabaseName());
        $this->assertSame('log', config('broadcasting.default'));
    }
}
