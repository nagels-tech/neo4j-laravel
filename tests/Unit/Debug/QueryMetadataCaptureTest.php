<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit\Debug;

use Illuminate\Database\Events\QueryExecuted;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Mockery;
use Mockery\MockInterface;
use Neo4j\Neo4jLaravel\Neo4jConnection;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class QueryMetadataCaptureTest extends TestCase
{
    private Neo4jConnection $connection;

    /** @var ClientInterface&MockInterface */
    private ClientInterface $client;

    /** @var list<QueryExecuted> */
    private array $executed = [];

    protected function getPackageProviders($app): array
    {
        return [Neo4jServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(ClientInterface::class);
        $this->executed = [];

        $this->connection = new Neo4jConnection(
            $this->client,
            'movies',
            '',
            ['name' => 'neo4j_primary']
        );
        $this->connection->setEventDispatcher($this->app['events']);

        $this->app['events']->listen(QueryExecuted::class, function (QueryExecuted $event): void {
            $this->executed[] = $event;
        });
    }

    public function test_successful_execution_records_full_metadata(): void
    {
        $cypher = 'MATCH (m:Movie {title: $title}) RETURN m';
        $params = ['title' => 'The Matrix'];
        $summary = null;
        $result = new SummarizedResult($summary);

        $this->client->shouldReceive('readTransaction')
            ->once()
            ->andReturn($result);

        $this->connection->select($cypher, $params);

        $this->assertCount(1, $this->executed);
        $this->assertSame($cypher, $this->executed[0]->sql);
        $this->assertSame($params, $this->executed[0]->bindings);
        $this->assertIsFloat($this->executed[0]->time);

        $log = $this->connection->getQueryLog()[0];
        $this->assertSame($cypher, $log['cypher']);
        $this->assertSame($params, $log['params']);
        $this->assertSame('neo4j_primary', $log['connection_name']);
        $this->assertSame('movies', $log['database']);
        $this->assertSame('neo4j', $log['driver']);
        $this->assertSame('ok', $log['status']);
        $this->assertTrue($log['successful']);
        $this->assertNull($log['error_message']);
    }

    public function test_failed_execution_records_error_status_and_message(): void
    {
        $cypher = 'INVALID CYPHER';
        $params = ['x' => 1];
        $exception = new \RuntimeException('Invalid input');

        $this->client->shouldReceive('writeTransaction')
            ->once()
            ->andThrow($exception);

        try {
            $this->connection->write($cypher, $params);
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
            $this->assertSame('Invalid input', $e->getMessage());
        }

        $this->assertCount(1, $this->executed);
        $log = $this->connection->getQueryLog()[0];

        $this->assertSame($cypher, $log['cypher']);
        $this->assertSame($params, $log['params']);
        $this->assertSame('neo4j_primary', $log['connection_name']);
        $this->assertSame('movies', $log['database']);
        $this->assertSame('error', $log['status']);
        $this->assertFalse($log['successful']);
        $this->assertSame('Invalid input', $log['error_message']);
    }

    public function test_read_failure_propagates_same_exception_after_capture(): void
    {
        $cypher = 'MATCH (n) RETURN n';
        $params = [];
        $exception = new \LogicException('read boom');

        $this->client->shouldReceive('readTransaction')
            ->once()
            ->andThrow($exception);

        try {
            $this->connection->select($cypher, $params);
            $this->fail('Expected exception was not thrown');
        } catch (\LogicException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertCount(1, $this->executed);
        $this->assertSame($cypher, $this->executed[0]->sql);
        $log = $this->connection->getQueryLog()[0];
        $this->assertSame('error', $log['status']);
        $this->assertSame('read boom', $log['error_message']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
