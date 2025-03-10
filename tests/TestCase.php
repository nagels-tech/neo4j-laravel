<?php

namespace Neo4jPhp\Neo4jLaravel\Tests;

use Neo4jPhp\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            Neo4jServiceProvider::class,
            \Barryvdh\Debugbar\ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Configure Neo4j connection for testing
        $app['config']->set('database.default', 'neo4j');
        $app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'url' => env('NEO4J_URL', 'bolt://localhost:7687'),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'password'),
        ]);

        // Enable debugbar for testing
        $app['config']->set('debugbar.enabled', true);
    }
}
