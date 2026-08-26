<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // Docker Compose injects the live MySQL connection as an operating-system
        // environment variable. Force every test back to isolated, in-memory
        // services before RefreshDatabase and the other test traits are started.
        $app['config']->set([
            'app.env' => 'testing',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
            'mail.default' => 'array',
        ]);

        $app['db']->purge();

        return $app;
    }
}
