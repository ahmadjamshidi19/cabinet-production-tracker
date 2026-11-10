<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private const TESTING_DATABASE = 'app_testing';

    /**
     * Runs before any trait such as RefreshDatabase touches the database, so a
     * misconfigured environment fails fast instead of wiping development data.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($database !== self::TESTING_DATABASE) {
            throw new RuntimeException(sprintf(
                'Refusing to run tests against "%s". Expected "%s".',
                $database,
                self::TESTING_DATABASE,
            ));
        }
    }
}
