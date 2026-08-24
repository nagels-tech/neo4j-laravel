<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit\Debug;

use Barryvdh\Debugbar\LaravelDebugbar;
use Barryvdh\Debugbar\ServiceProvider as DebugbarServiceProvider;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Mockery;
use Mockery\MockInterface;
use Neo4j\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Neo4j\Neo4jLaravel\Neo4jConnection;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class QueryMetadataCaptureTest extends TestCase
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
        $app['config']->set('debugbar.enabled', true);
        $app['config']->set('debugbar.options.neo4j.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(ClientInterface::class);
        $this->collector = new Neo4jQueryCollector();
        $this->app->instance('debugbar', new LaravelDebugbar($this->app));
        $this->app->instance(Neo4jQueryCollector::class, $this->collector);

        $this->connection = new Neo4jConnection(
            $this->client,
            'movies',
            '',
            ['name' => 'neo4j_primary']
        );
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

        $entry = $this->collector->collect()['statements'][0];
        $log = $this->connection->getQueryLog()[0];

        $this->assertSame($cypher, $entry['cypher']);
        $this->assertSame($cypher, $entry['sql']);
        $this->assertEquals($params, (array) $entry['params']);
        $this->assertSame($params, $entry['bindings']);
        $this->assertIsFloat($entry['duration']);
        $this->assertNotNull($entry['duration_str']);
        $this->assertSame('neo4j_primary', $entry['connection']);
        $this->assertSame('movies', $entry['database']);
        $this->assertSame(['Database' => 'movies'], $entry['hints']);
        $this->assertSame('ok', $entry['status']);
        $this->assertTrue($entry['is_success']);
        $this->assertNull($entry['error_message']);
        $this->assertNull($entry['error_code']);
        $this->assertSame('query', $entry['type']);
        $this->assertFalse($entry['slow']);

        // Widget-facing shape on the Debugbar dataset (what LaravelQueriesWidget maps).
        $this->app->instance(Neo4jQueryCollector::class, $this->collector);
        $debugbar = new LaravelDebugbar($this->app);
        $debugbar->addCollector($this->collector);
        $dataset = $debugbar->getData()['neo4j']['statements'][0];
        $this->assertSame($cypher, $dataset['sql']);
        $this->assertSame($params, $dataset['bindings']);
        $this->assertSame('neo4j_primary', $dataset['connection']);
        $this->assertSame(['Database' => 'movies'], $dataset['hints']);
        $this->assertNotNull($dataset['duration_str']);
        $this->assertTrue($dataset['is_success']);
        $this->assertSame('ok', $dataset['status']);

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

        $this->client->shouldReceive('writeTransaction')
            ->once()
            ->andThrow(new \RuntimeException('Invalid input'));

        try {
            $this->connection->write($cypher, $params);
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Invalid input', $e->getMessage());
        }

        $entry = $this->collector->collect()['statements'][0];
        $log = $this->connection->getQueryLog()[0];

        $this->assertSame($cypher, $entry['cypher']);
        $this->assertEquals($params, (array) $entry['params']);
        $this->assertSame('neo4j_primary', $entry['connection']);
        $this->assertSame('movies', $entry['database']);
        $this->assertSame(['Database' => 'movies'], $entry['hints']);
        $this->assertSame('error', $entry['status']);
        $this->assertFalse($entry['is_success']);
        $this->assertSame('', $entry['error_code']);
        $this->assertSame('Invalid input', $entry['error_message']);
        $this->assertIsFloat($entry['duration']);
        $this->assertNotNull($entry['duration_str']);

        $this->assertSame('error', $log['status']);
        $this->assertFalse($log['successful']);
        $this->assertSame('Invalid input', $log['error_message']);
        $this->assertSame('movies', $log['database']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
