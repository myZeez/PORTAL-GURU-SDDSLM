<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to boot against anything but the throwaway in-memory database. A stale
     * bootstrap/cache/config.php silently ignores phpunit.xml, and RefreshDatabase would
     * then wipe the real MySQL database.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = $app['config']['database.default'];

        if ($connection !== 'sqlite' || $app['config']["database.connections.{$connection}.database"] !== ':memory:') {
            throw new RuntimeException('Tests are not running on the in-memory SQLite database. Run "php artisan optimize:clear" and try again.');
        }

        return $app;
    }
}
