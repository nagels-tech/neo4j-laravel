<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit\Debug;

use Neo4j\Neo4jLaravel\Debug\DebugbarAvailability;
use Neo4j\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Exercises detection paths when Laravel Debugbar is not registered on the app,
 * even if the package classes exist in vendor (require-dev).
 */
class DebugbarAvailabilityWithoutBindingTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [Neo4jServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'neo4j');
        $app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'url' => 'bolt://localhost:7687',
            'username' => 'neo4j',
            'password' => 'x',
            'database' => 'neo4j',
        ]);
        // Disable Neo4j Debugbar registration even though the package is installed as require-dev.
        $app['config']->set('debugbar.collectors.neo4j', false);
        $app['config']->set('debugbar.options.neo4j.enabled', false);
    }

    public function test_package_may_be_present_without_integration_enabled(): void
    {
        $this->assertTrue(DebugbarAvailability::isPackagePresent());
        $this->assertFalse(DebugbarAvailability::shouldRegister($this->app));
    }

    public function test_should_not_capture_when_debugbar_not_bound(): void
    {
        $this->assertFalse(DebugbarAvailability::isBound($this->app));
        $this->assertFalse($this->app->bound(Neo4jQueryCollector::class));
        $this->assertFalse(DebugbarAvailability::shouldCapture($this->app));
    }
}
