<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit\Debug;

use Barryvdh\Debugbar\LaravelDebugbar;
use Barryvdh\Debugbar\ServiceProvider as DebugbarServiceProvider;
use Neo4j\Neo4jLaravel\Debug\DebugbarAvailability;
use Neo4j\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Host app has Debugbar installed but debugbar.enabled=false before boot.
 */
class DebugbarGloballyDisabledLifecycleTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            Neo4jServiceProvider::class,
            DebugbarServiceProvider::class,
        ];
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
        $app['config']->set('debugbar.enabled', false);
        $app['config']->set('debugbar.collectors.neo4j', true);
        $app['config']->set('debugbar.options.neo4j.enabled', true);
    }

    public function test_does_not_register_cypher_collector_when_debugbar_disabled(): void
    {
        $this->assertTrue(DebugbarAvailability::isBound($this->app));
        $this->assertFalse(DebugbarAvailability::shouldRegister($this->app));
        $this->assertFalse(DebugbarAvailability::shouldCapture($this->app));
        $this->assertFalse($this->app->bound(Neo4jQueryCollector::class));

        /** @var LaravelDebugbar $debugbar */
        $debugbar = $this->app->make('debugbar');
        $this->assertFalse($debugbar->hasCollector('neo4j'));
    }
}
