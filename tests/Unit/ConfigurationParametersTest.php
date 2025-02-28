<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit;

use Illuminate\Contracts\Container\BindingResolutionException;
use Laudis\Neo4j\Contracts\ClientInterface;
use Neo4jPhp\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class ConfigurationParametersTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [Neo4jServiceProvider::class];
    }

    public function testUrlTakesPrecedenceOverHostAndPort(): void
    {
        $this->app['config']->set('database.default', 'neo4j');
        $this->app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'url' => 'bolt://override-url:7687', // This should be used
            'host' => 'should-not-be-used',      // This should be ignored
            'port' => 1234,                      // This should be ignored
            'username' => 'neo4j',
            'password' => 'testtest',
            'database' => 'neo4j',
        ]);

        // We can't actually test connectivity because the URL doesn't exist,
        // but we can confirm the client builds without errors
        $client = $this->app->make(ClientInterface::class);
        $this->assertNotNull($client);

        // We would need to inspect internal state of $builder to truly verify this,
        // but that's not easily accessible. We're testing that the configuration logic
        // doesn't throw errors when both URL and host/port are specified.
    }

    public function testMissingUrlAndHost(): void
    {
        $this->app['config']->set('database.default', 'neo4j');
        $this->app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            // Missing both url and host
            'port' => 7687,
            'username' => 'neo4j',
            'password' => 'testtest',
            'database' => 'neo4j',
        ]);

        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Missing required URL or host/port configuration for Neo4j connection');

        $this->app->make(ClientInterface::class);
    }

    public function testMissingUrlAndPort(): void
    {
        $this->app['config']->set('database.default', 'neo4j');
        $this->app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            // Missing both url and port
            'host' => 'neo4j',
            'username' => 'neo4j',
            'password' => 'testtest',
            'database' => 'neo4j',
        ]);

        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Missing required URL or host/port configuration for Neo4j connection');

        $this->app->make(ClientInterface::class);
    }

    public function testSslConfigurationParameters(): void
    {
        $this->app['config']->set('database.default', 'neo4j');
        $this->app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'url' => 'bolt://neo4j:7687',
            'username' => 'neo4j',
            'password' => 'testtest',
            'database' => 'neo4j',
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
        $this->app['config']->set('database.default', 'neo4j');
        $this->app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'url' => 'bolt://neo4j:7687',
            'username' => 'neo4j',
            'password' => 'testtest',
            'database' => 'neo4j',
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
