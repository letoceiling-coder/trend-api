<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'mysql') {
            $db = config('database.connections.mysql.database', '');
            if ($db !== 'trend_api_test') {
                $this->fail('Safety: tests with MySQL must use DB_DATABASE=trend_api_test. Current: ' . ($db ?: '(empty)'));
            }
        }
    }
}
