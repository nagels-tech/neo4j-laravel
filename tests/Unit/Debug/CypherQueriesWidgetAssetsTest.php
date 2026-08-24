<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit\Debug;

use Barryvdh\Debugbar\ServiceProvider as DebugbarServiceProvider;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class CypherQueriesWidgetAssetsTest extends TestCase
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
        $app['config']->set('app.debug', true);
        $app['config']->set('debugbar.enabled', true);
        $app['config']->set('debugbar.collectors.neo4j', true);
        $app['config']->set('database.default', 'neo4j');
        $app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'url' => 'bolt://localhost:7687',
            'username' => 'neo4j',
            'password' => 'x',
            'database' => 'neo4j',
        ]);
    }

    public function test_debugbar_js_assets_include_laravel_queries_widget_definition(): void
    {
        $debugbar = $this->app->make('debugbar');
        $debugbar->enable();
        $this->assertTrue($debugbar->hasCollector('neo4j'));

        $paths = $debugbar->getJavascriptRenderer()->getAssets('js');
        $foundLaravelQueriesWidget = false;
        $sqlqueriesCssPresent = false;

        foreach ($debugbar->getJavascriptRenderer()->getAssets('css') as $path) {
            if (is_string($path) && str_contains($path, 'sqlqueries') && is_file($path)) {
                $sqlqueriesCssPresent = true;
            }
        }

        foreach ($paths as $path) {
            if (! is_string($path) || ! is_file($path)) {
                continue;
            }
            if (str_contains(file_get_contents($path), 'LaravelQueriesWidget')) {
                $foundLaravelQueriesWidget = true;
            }
        }

        $this->assertTrue($foundLaravelQueriesWidget, 'LaravelQueriesWidget must be in Debugbar JS assets');
        $this->assertTrue($sqlqueriesCssPresent, 'sqlqueries widget.css must be present for Cypher panel styling');
    }
}
