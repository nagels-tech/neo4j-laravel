<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit\Debug;

use Barryvdh\Debugbar\ServiceProvider as DebugbarServiceProvider;
use Neo4j\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class Neo4jQueryCollectorTest extends TestCase
{
    private Neo4jQueryCollector $collector;

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

        $this->collector = new Neo4jQueryCollector();
    }

    public function testCollectorName(): void
    {
        $this->assertEquals('neo4j', $this->collector->getName());
    }

    public function testAddQuery(): void
    {
        $query = 'MATCH (n:Test) RETURN n';
        $parameters = ['param' => 'value'];
        $duration = 0.1;
        $connection = 'default';

        $this->collector->addQuery($query, $parameters, $duration, $connection);

        $data = $this->collector->collect();

        $this->assertEquals(1, $data['nb_statements']);
        $this->assertEquals($duration, $data['accumulated_duration']);
        $this->assertEquals('0.10 ms', $data['accumulated_duration_str']);

        $queryData = $data['statements'][0];
        $this->assertEquals($query, $queryData['sql']);
        $this->assertEquals($query, $queryData['cypher']);
        $this->assertEquals($parameters, (array) $queryData['params']);
        $this->assertEquals($parameters, $queryData['bindings']);
        $this->assertEquals($duration, $queryData['duration']);
        $this->assertEquals('0.10 ms', $queryData['duration_str']);
        $this->assertEquals($connection, $queryData['connection']);
        $this->assertNull($queryData['stack']); // Stack trace is disabled by default
        $this->assertTrue($queryData['is_success']);
        $this->assertSame('ok', $queryData['status']);
        $this->assertNull($queryData['error_code']);
        $this->assertSame([], $queryData['hints']);
        $this->assertEquals(0, $queryData['stmt_id']);
    }

    public function test_add_query_exposes_widget_fields_for_database_and_errors(): void
    {
        $this->collector->addQuery(
            'MATCH (n) RETURN n',
            ['id' => 1],
            12.5,
            'neo4j_primary',
            true,
            null,
            'movies'
        );
        $this->collector->addQuery(
            'BAD',
            [],
            1.0,
            'neo4j_primary',
            false,
            'boom',
            'movies'
        );

        $ok = $this->collector->collect()['statements'][0];
        $this->assertSame(['Database' => 'movies'], $ok['hints']);
        $this->assertSame('movies', $ok['database']);
        $this->assertSame('12.50 ms', $ok['duration_str']);
        $this->assertTrue($ok['is_success']);

        $fail = $this->collector->collect()['statements'][1];
        $this->assertFalse($fail['is_success']);
        $this->assertSame('error', $fail['status']);
        $this->assertSame('', $fail['error_code']);
        $this->assertSame('boom', $fail['error_message']);
        $this->assertSame(['Database' => 'movies'], $fail['hints']);
    }

    public function testStackTraceWhenEnabled(): void
    {
        $this->collector->setTimeEnabled(true);

        // Call through a helper method to ensure we have a stack frame
        $this->addQueryForStackTest();

        $data = $this->collector->collect();
        $queryData = $data['statements'][0];

        $this->assertIsArray($queryData['stack']);
        $this->assertNotEmpty($queryData['stack']);

        // Check stack trace format
        $trace = $queryData['stack'][0];
        $this->assertArrayHasKey('file', $trace);
        $this->assertArrayHasKey('line', $trace);
        $this->assertArrayHasKey('class', $trace);
        $this->assertArrayHasKey('function', $trace);
    }

    private function addQueryForStackTest(): void
    {
        $this->collector->addQuery('MATCH (n:Test) RETURN n');
    }

    public function testMultipleQueries(): void
    {
        $this->collector->addQuery('MATCH (n:Test1) RETURN n', [], 0.1);
        $this->collector->addQuery('MATCH (n:Test2) RETURN n', [], 0.2);

        $data = $this->collector->collect();

        $this->assertEquals(2, $data['nb_statements']);
        $this->assertEqualsWithDelta(0.3, $data['accumulated_duration'], 0.0001);
        $this->assertEquals('0.30 ms', $data['accumulated_duration_str']);
    }

    public function testReset(): void
    {
        $this->collector->addQuery('MATCH (n:Test) RETURN n');
        $this->collector->reset();

        $data = $this->collector->collect();

        $this->assertEquals(0, $data['nb_statements']);
        $this->assertEmpty($data['statements']);
    }

    public function test_failed_query_increments_failed_count(): void
    {
        $this->collector->addQuery('MATCH (n) RETURN n', [], 1.2, 'neo4j', true, null, 'neo4j');
        $this->collector->addQuery('INVALID', [], 0.5, 'neo4j', false, 'Syntax error', 'neo4j');

        $data = $this->collector->collect();

        $this->assertEquals(2, $data['nb_statements']);
        $this->assertEquals(1, $data['nb_failed_statements']);
        $this->assertFalse($data['statements'][1]['is_success']);
        $this->assertSame('error', $data['statements'][1]['status']);
        $this->assertEquals('Syntax error', $data['statements'][1]['error_message']);
        $this->assertEquals('INVALID', $data['statements'][1]['cypher']);
        $this->assertSame('neo4j', $data['statements'][1]['database']);
    }

    public function testWidgets(): void
    {
        $widgets = $this->collector->getWidgets();

        $this->assertArrayHasKey('neo4j', $widgets);
        $this->assertArrayHasKey('neo4j:badge', $widgets);
        $this->assertArrayHasKey('neo4j:tooltip', $widgets);

        $this->assertEquals('database', $widgets['neo4j']['icon']);
        $this->assertEquals('PhpDebugBar.Widgets.LaravelQueriesWidget', $widgets['neo4j']['widget']);
        $this->assertEquals('neo4j', $widgets['neo4j']['map']);
        $this->assertEquals('[]', $widgets['neo4j']['default']);
        $this->assertEquals('Cypher Queries', $widgets['neo4j']['title']);

        $this->assertEquals('neo4j.nb_statements', $widgets['neo4j:badge']['map']);
        $this->assertEquals(0, $widgets['neo4j:badge']['default']);
        $this->assertEquals('neo4j.tooltip', $widgets['neo4j:tooltip']['map']);
    }

    public function test_assets_provide_sql_queries_widget_resources(): void
    {
        $assets = $this->collector->getAssets();

        $this->assertSame('widgets/sqlqueries/widget.css', $assets['css']);
        $this->assertSame('widgets/sqlqueries/widget.js', $assets['js']);
    }

    public function test_collect_exposes_count_totals_and_tooltip(): void
    {
        $this->collector->setSlowThreshold(10.0);
        $this->collector->addQuery('MATCH (a) RETURN a', [], 5.0, 'neo4j', true, null, 'neo4j');
        $this->collector->addQuery('MATCH (b) RETURN b', [], 25.0, 'neo4j', true, null, 'neo4j');

        $data = $this->collector->collect();

        $this->assertSame(2, $data['count']);
        $this->assertSame(2, $data['nb_statements']);
        $this->assertSame(0, $data['nb_failed_statements']);
        $this->assertSame(1, $data['nb_slow_statements']);
        $this->assertEqualsWithDelta(30.0, $data['accumulated_duration'], 0.0001);
        $this->assertSame('30.00 ms', $data['accumulated_duration_str']);
        $this->assertTrue($data['statements'][1]['slow']);
        $this->assertSame(2, $data['tooltip']['Queries']);
        $this->assertSame('30.00 ms', $data['tooltip']['Total time']);
        $this->assertSame(2, $this->collector->getQueryCount());
        $this->assertEqualsWithDelta(30.0, $this->collector->getTotalDuration(), 0.0001);
        $this->assertCount(2, $this->collector->getQueries());
    }
}
