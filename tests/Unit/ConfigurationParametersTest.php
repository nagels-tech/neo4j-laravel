<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit;

use Illuminate\Contracts\Container\BindingResolutionException;
use Laudis\Neo4j\Contracts\ClientInterface;
use Neo4jPhp\Neo4jLaravel\Tests\TestCase;

class ConfigurationParametersTest extends TestCase
{
    public function testUrlTakesPrecedenceOverHostAndPort(): void
    {
        $this->app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'url' => 'bolt://override-url:7687', // This should be used
            'host' => 'should-not-be-used',      // This should be ignored
            'port' => 1234,                      // This should be ignored
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'testtest'),
            'database' => env('NEO4J_DATABASE', 'neo4j'),
        ]);

        // We can't actually test connectivity because the URL doesn't exist,
        // but we can confirm the client builds without errors
        $client = $this->app->make(ClientInterface::class);
        $this->assertNotNull($client);
    }

    public function testMissingUrlAndHost(): void
    {
        $this->app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            // Missing both url and host
            'port' => 7687,
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'testtest'),
            'database' => env('NEO4J_DATABASE', 'neo4j'),
        ]);

        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Missing required URL or host/port configuration for Neo4j connection');

        $this->app->make(ClientInterface::class);
    }

    public function testMissingUrlAndPort(): void
    {
        $this->app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            // Missing both url and port
            'host' => env('NEO4J_HOST', 'neo4j'),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'testtest'),
            'database' => env('NEO4J_DATABASE', 'neo4j'),
        ]);

        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Missing required URL or host/port configuration for Neo4j connection');

        $this->app->make(ClientInterface::class);
    }

    public function testSslConfigurationParameters(): void
    {
        $this->app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'url' => sprintf(
                'bolt://%s:%s',
                env('NEO4J_HOST', 'neo4j'),
                env('NEO4J_PORT', '7687')
            ),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'testtest'),
            'database' => env('NEO4J_DATABASE', 'neo4j'),
            'ssl' => [
                'mode' => 'enable',
                'verify_peer' => false,
            ],
        ]);

        // This test just confirms the SSL configuration is accepted
        $client = $this->app->make(ClientInterface::class);
        $this->assertNotNull($client);
    }

    public function testPoolSizeConfiguration(): void
    {
        $this->app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'url' => sprintf(
                'bolt://%s:%s',
                env('NEO4J_HOST', 'neo4j'),
                env('NEO4J_PORT', '7687')
            ),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'testtest'),
            'database' => env('NEO4J_DATABASE', 'neo4j'),
            'connection' => [
                'max_pool_size' => 50,
                'timeout' => 15,
            ],
        ]);

        // This test just confirms the pool size configuration is accepted
        $client = $this->app->make(ClientInterface::class);
        $this->assertNotNull($client);
    }
}
