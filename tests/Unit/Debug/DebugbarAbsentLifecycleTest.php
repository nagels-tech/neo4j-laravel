<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit\Debug;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Mockery;
use Neo4j\Neo4jLaravel\Debug\DebugbarAvailability;
use Neo4j\Neo4jLaravel\Debug\Neo4jDebugServiceProvider;
use Neo4j\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Neo4j\Neo4jLaravel\Neo4jConnection;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Service-provider lifecycle when Debugbar is not registered on the app.
 * barryvdh/laravel-debugbar may still be present in require-dev; the binding is absent.
 */
class DebugbarAbsentLifecycleTest extends TestCase
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
    }

    public function test_does_not_register_debug_provider_or_collector_without_debugbar_binding(): void
    {
        $this->assertFalse(DebugbarAvailability::isBound($this->app));
        $this->assertFalse($this->app->bound(Neo4jQueryCollector::class));
        $this->assertNull($this->app->getProvider(Neo4jDebugServiceProvider::class));
        $this->assertFalse(DebugbarAvailability::shouldCapture($this->app));
    }

    public function test_neo4j_connection_works_without_debugbar(): void
    {
        $client = Mockery::mock(ClientInterface::class);
        $summary = null;
        $result = new SummarizedResult($summary);
        $client->shouldReceive('readTransaction')->once()->andReturn($result);
        $this->app->instance(ClientInterface::class, $client);

        /** @var Neo4jConnection $connection */
        $connection = $this->app->make('db')->connection('neo4j');
        $this->assertInstanceOf(Neo4jConnection::class, $connection);

        $connection->select('MATCH (n) RETURN n', []);

        $this->assertCount(1, $connection->getQueryLog());
        $this->assertSame('MATCH (n) RETURN n', $connection->getQueryLog()[0]['cypher']);
        $this->assertFalse($this->app->bound(Neo4jQueryCollector::class));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
