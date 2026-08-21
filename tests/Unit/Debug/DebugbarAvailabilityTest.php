<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit\Debug;

use Barryvdh\Debugbar\LaravelDebugbar;
use Barryvdh\Debugbar\ServiceProvider as DebugbarServiceProvider;
use Neo4j\Neo4jLaravel\Debug\DebugbarAvailability;
use Neo4j\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class DebugbarAvailabilityTest extends TestCase
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
        $app['config']->set('debugbar.enabled', true);
        $app['config']->set('debugbar.collectors.neo4j', true);
    }

    public function test_detects_debugbar_package_when_installed(): void
    {
        $this->assertTrue(DebugbarAvailability::isPackagePresent());
        $this->assertTrue(class_exists(LaravelDebugbar::class));
        $this->assertTrue(class_exists(DebugbarServiceProvider::class));
    }

    public function test_should_register_when_package_present_and_not_disabled(): void
    {
        $this->app['config']->set('debugbar.options.neo4j.enabled', null);

        $this->assertTrue(DebugbarAvailability::shouldRegister($this->app));
    }

    public function test_should_not_register_when_explicitly_disabled(): void
    {
        $this->app['config']->set('debugbar.options.neo4j.enabled', false);

        $this->assertFalse(DebugbarAvailability::shouldRegister($this->app));
    }

    public function test_should_register_when_explicitly_enabled(): void
    {
        $this->app['config']->set('debugbar.options.neo4j.enabled', true);

        $this->assertTrue(DebugbarAvailability::shouldRegister($this->app));
    }

    public function test_is_bound_when_debugbar_provider_loaded(): void
    {
        $this->assertTrue(DebugbarAvailability::isBound($this->app));
    }

    public function test_should_capture_when_collector_is_registered(): void
    {
        $this->assertTrue($this->app->bound(Neo4jQueryCollector::class));
        $this->assertTrue(DebugbarAvailability::shouldCapture($this->app));
        $this->assertInstanceOf(Neo4jQueryCollector::class, $this->app->make(Neo4jQueryCollector::class));
    }
}
