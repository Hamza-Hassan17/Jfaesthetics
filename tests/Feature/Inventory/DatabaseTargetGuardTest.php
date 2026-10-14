<?php

namespace Tests\Feature\Inventory;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RefreshDatabase wipes whatever database the suite points at. This guard
 * fails the run before any wiping test if the suite ever resolves to the
 * app's real database (e.g. a misconfigured phpunit file).
 */
class DatabaseTargetGuardTest extends TestCase
{
    public function test_the_suite_never_runs_against_the_app_database()
    {
        $database = DB::connection()->getDatabaseName();

        $this->assertTrue(
            $database === ':memory:' || str_ends_with($database, '_check') || str_ends_with($database, '_test'),
            "Refusing to run tests against '{$database}'."
        );
    }
}
