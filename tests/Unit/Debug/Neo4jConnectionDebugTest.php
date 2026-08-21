<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit\Debug;

use Barryvdh\Debugbar\LaravelDebugbar;
use Barryvdh\Debugbar\ServiceProvider as DebugbarServiceProvider;
use Laudis\Neo4j\Contracts\ClientInterface;
use Mockery;
use Mockery\MockInterface;
use Neo4j\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Neo4j\Neo4jLaravel\Neo4jConnection;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class Neo4jConnectionDebugTest extends TestCase
{
    private Neo4jConnection $connection;
    private Neo4jQueryCollector $collector;
    /** @var ClientInterface&MockInterface */
    private ClientInterface $client;

    protected function getPackageProviders($app): array
    {
        return [
            Neo4jServiceProvider::class,
            DebugbarServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('debugbar.collectors.neo4j', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(ClientInterface::class);
        $this->collector = new Neo4jQueryCollector();

        // Bind the debugbar and collector to the container
        $this->app->instance('debugbar', new LaravelDebugbar($this->app));
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
        $this->assertEquals(1, $data['nb_statements']);

        $queryData = $data['statements'][0];
        $this->assertEquals($query, $queryData['sql']);
        $this->assertEquals($bindings, $queryData['params']);
        $this->assertEquals(0.1, $queryData['duration']);
        $this->assertEquals('testing', $queryData['connection']);
    }

    public function testLogsQueriesWithNullDuration(): void
    {
        $query = 'MATCH (n:Test) RETURN n';
        $bindings = ['param' => 'value'];

        $this->connection->logQuery($query, $bindings);

        $data = $this->collector->collect();
        $queryData = $data['statements'][0];
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
        $this->assertEquals(1, $data['nb_statements']);

        $queryData = $data['statements'][0];
        $this->assertEquals($query, $queryData['sql']);
        $this->assertEquals($bindings, $queryData['params']);
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
                'Test exception',
                $e->getMessage()
            );
        }

        $data = $this->collector->collect();
        $this->assertEquals(1, $data['nb_statements']);

        $queryData = $data['statements'][0];
        $this->assertEquals($query, $queryData['sql']);
        $this->assertEquals($bindings, $queryData['params']);
        $this->assertIsFloat($queryData['duration']);
        $this->assertEquals('testing', $queryData['connection']);
        $this->assertFalse($queryData['is_success']);
        $this->assertEquals('Test exception', $queryData['error_message']);
        $this->assertEquals(1, $data['nb_failed_statements']);
    }

    public function test_write_captures_cypher_query(): void
    {
        $query = 'CREATE (n:Test {name: $name}) RETURN n';
        $bindings = ['name' => 'Ada'];
        $summary = null;
        $result = new \Laudis\Neo4j\Databags\SummarizedResult($summary);

        $this->client->shouldReceive('writeTransaction')
            ->once()
            ->andReturn($result);

        $this->connection->write($query, $bindings);

        $data = $this->collector->collect();
        $this->assertEquals(1, $data['nb_statements']);
        $this->assertEquals($query, $data['statements'][0]['cypher']);
        $this->assertEquals($bindings, $data['statements'][0]['params']);
        $this->assertTrue($data['statements'][0]['is_success']);
    }

    public function test_run_cypher_captures_through_shared_execution_path(): void
    {
        $query = 'RETURN $value AS v';
        $bindings = ['value' => 42];
        $summary = null;
        $result = new \Laudis\Neo4j\Databags\SummarizedResult($summary);

        $this->client->shouldReceive('run')
            ->once()
            ->with($query, $bindings)
            ->andReturn($result);

        $this->connection->runCypher($query, $bindings);

        $data = $this->collector->collect();
        $this->assertEquals(1, $data['nb_statements']);
        $this->assertEquals($query, $data['statements'][0]['sql']);
        $this->assertEquals($bindings, $data['statements'][0]['params']);
        $this->assertTrue($data['statements'][0]['is_success']);
        $this->assertIsFloat($data['statements'][0]['duration']);
    }

    public function test_capture_continues_when_query_log_disabled(): void
    {
        $this->connection->disableQueryLog();

        $this->client->shouldReceive('readTransaction')
            ->once()
            ->andReturn(['result']);

        $this->connection->select('MATCH (n) RETURN n', []);

        $this->assertSame([], $this->connection->getQueryLog());
        $this->assertEquals(1, $this->collector->collect()['nb_statements']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
