<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit\Debug;

use Barryvdh\Debugbar\ServiceProvider as DebugbarServiceProvider;
use Neo4jPhp\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Neo4jPhp\Neo4jLaravel\Neo4jServiceProvider;
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
        $this->assertEquals($parameters, $queryData['params']);
        $this->assertEquals($duration, $queryData['duration']);
        $this->assertEquals('0.10 ms', $queryData['duration_str']);
        $this->assertEquals($connection, $queryData['connection']);
        $this->assertNull($queryData['stack']); // Stack trace is disabled by default
        $this->assertTrue($queryData['is_success']);
        $this->assertEquals(0, $queryData['stmt_id']);
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

    public function testWidgets(): void
    {
        $widgets = $this->collector->getWidgets();

        $this->assertArrayHasKey('neo4j', $widgets);
        $this->assertArrayHasKey('neo4j:badge', $widgets);

        $this->assertEquals('database', $widgets['neo4j']['icon']);
        $this->assertEquals('PhpDebugBar.Widgets.SQLQueriesWidget', $widgets['neo4j']['widget']);
        $this->assertEquals('neo4j', $widgets['neo4j']['map']);
        $this->assertEquals('[]', $widgets['neo4j']['default']);

        $this->assertEquals('neo4j.nb_statements', $widgets['neo4j:badge']['map']);
        $this->assertEquals(0, $widgets['neo4j:badge']['default']);
    }
}
