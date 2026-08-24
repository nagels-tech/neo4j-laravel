<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit\Debug;

use Barryvdh\Debugbar\LaravelDebugbar;
use Barryvdh\Debugbar\ServiceProvider as DebugbarServiceProvider;
use Neo4j\Neo4jLaravel\Debug\DebugbarAvailability;
use Neo4j\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class Neo4jDebugbarRegistrationTest extends TestCase
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
        $app['config']->set('debugbar.options.neo4j.enabled', true);
    }

    public function test_registers_cypher_collector_on_debugbar(): void
    {
        $this->assertTrue(DebugbarAvailability::shouldRegister($this->app));
        $this->assertTrue($this->app->bound(Neo4jQueryCollector::class));

        /** @var LaravelDebugbar $debugbar */
        $debugbar = $this->app->make('debugbar');

        $this->assertTrue($debugbar->hasCollector('neo4j'));
        $this->assertInstanceOf(Neo4jQueryCollector::class, $debugbar->getCollector('neo4j'));
    }

    public function test_exposes_dedicated_cypher_queries_section_widgets(): void
    {
        $collector = $this->app->make(Neo4jQueryCollector::class);
        $widgets = $collector->getWidgets();

        $this->assertArrayHasKey('neo4j', $widgets);
        $this->assertSame('Cypher Queries', $widgets['neo4j']['title']);
        $this->assertSame('PhpDebugBar.Widgets.SQLQueriesWidget', $widgets['neo4j']['widget']);
        $this->assertSame('neo4j', $widgets['neo4j']['map']);
        $this->assertArrayHasKey('neo4j:badge', $widgets);
        $this->assertArrayHasKey('neo4j:tooltip', $widgets);
    }

    public function test_should_register_and_capture_respect_runtime_disable_flags(): void
    {
        // Boot-time disable is covered by DebugbarDisabledLifecycleTest.
        // These flags also gate capture for the remainder of the request.
        $this->app['config']->set('debugbar.collectors.neo4j', false);

        $this->assertFalse(DebugbarAvailability::shouldRegister($this->app));
        $this->assertFalse(DebugbarAvailability::shouldCapture($this->app));
    }
}
