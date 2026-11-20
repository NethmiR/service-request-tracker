<?php

// Base class for every feature and unit test.
// Registers an in memory SQLite connection for tests only, so tests never touch a real database.

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private const string TESTING_CONNECTION = 'sqlite';

    public function createApplication(): Application
    {
        $application = parent::createApplication();

        $application['config']->set('database.connections.'.self::TESTING_CONNECTION, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $application['config']->set('database.default', self::TESTING_CONNECTION);

        return $application;
    }
}
