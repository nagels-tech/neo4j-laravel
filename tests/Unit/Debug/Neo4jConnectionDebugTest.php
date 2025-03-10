<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit\Debug;

use Laudis\Neo4j\Contracts\ClientInterface;
use Mockery;
use Mockery\MockInterface;
use Neo4jPhp\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Neo4jPhp\Neo4jLaravel\Neo4jConnection;
use Orchestra\Testbench\TestCase;

class Neo4jConnectionDebugTest extends TestCase
{
    private Neo4jConnection $connection;
    private Neo4jQueryCollector $collector;
    /** @var ClientInterface&MockInterface */
    private ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(ClientInterface::class);
        $this->collector = new Neo4jQueryCollector();

        // Bind the collector to the container
        $this->app->instance(Neo4jQueryCollector::class, $this->collector);

        $this->connection = new Neo4jConnection(
            $this->client,
            'neo4j',
            '',
            ['name' => 'testing']
        );
    }

    public function testLogsQueriesWhenDebugbarIsAvailable(): void
    {
        $query = 'MATCH (n:Test) RETURN n';
        $bindings = ['param' => 'value'];

        $this->connection->logQuery($query, $bindings, 0.1);

        $data = $this->collector->collect();
        $this->assertEquals(1, $data['nb_queries']);

        $queryData = $data['queries'][0];
        $this->assertEquals($query, $queryData['query']);
        $this->assertEquals($bindings, $queryData['parameters']);
        $this->assertEquals(0.1, $queryData['duration']);
        $this->assertEquals('testing', $queryData['connection']);
    }

    public function testLogsQueriesWithNullDuration(): void
    {
        $query = 'MATCH (n:Test) RETURN n';
        $bindings = ['param' => 'value'];

        $this->connection->logQuery($query, $bindings);

        $data = $this->collector->collect();
        $queryData = $data['queries'][0];
        $this->assertNull($queryData['duration']);
        $this->assertNull($queryData['duration_str']);
    }

    public function testRunQueryCallbackLogsQueries(): void
    {
        $query = 'MATCH (n:Test) RETURN n';
        $bindings = ['param' => 'value'];

        $this->client->shouldReceive('readTransaction')
            ->once()
            ->with(\Mockery::on(function ($callback) use ($query, $bindings) {
                return true; // We can't easily verify the callback
            }))
            ->andReturn(['result']);

        $result = $this->connection->select($query, $bindings);

        $data = $this->collector->collect();
        $this->assertEquals(1, $data['nb_queries']);

        $queryData = $data['queries'][0];
        $this->assertEquals($query, $queryData['query']);
        $this->assertEquals($bindings, $queryData['parameters']);
        $this->assertIsFloat($queryData['duration']);
        $this->assertEquals('testing', $queryData['connection']);
    }

    public function testRunQueryCallbackLogsQueriesOnException(): void
    {
        $query = 'MATCH (n:Test) RETURN n';
        $bindings = ['param' => 'value'];

        $this->client->shouldReceive('readTransaction')
            ->once()
            ->with(\Mockery::on(function ($callback) use ($query, $bindings) {
                return true; // We can't easily verify the callback
            }))
            ->andThrow(new \Exception('Test exception'));

        try {
            $this->connection->select($query, $bindings);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals(
                'Test exception (Connection: testing, SQL: MATCH (n:Test) RETURN n)',
                $e->getMessage()
            );
        }

        $data = $this->collector->collect();
        $this->assertEquals(1, $data['nb_queries']);

        $queryData = $data['queries'][0];
        $this->assertEquals($query, $queryData['query']);
        $this->assertEquals($bindings, $queryData['parameters']);
        $this->assertIsFloat($queryData['duration']);
        $this->assertEquals('testing', $queryData['connection']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
