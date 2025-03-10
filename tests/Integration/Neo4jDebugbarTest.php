<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Integration;

use Barryvdh\Debugbar\LaravelDebugbar;
use Barryvdh\Debugbar\ServiceProvider as DebugbarServiceProvider;
use Illuminate\Support\Facades\DB;
use Neo4jPhp\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Neo4jPhp\Neo4jLaravel\Tests\TestCase;

class Neo4jDebugbarTest extends TestCase
{
    private LaravelDebugbar $debugbar;
    private Neo4jQueryCollector $collector;

    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            DebugbarServiceProvider::class,
        ]);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('debugbar.collectors.neo4j', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->debugbar = $this->app->make(LaravelDebugbar::class);
        $this->collector = new Neo4jQueryCollector();
        $this->app->instance(Neo4jQueryCollector::class, $this->collector);
        $this->debugbar->addCollector($this->collector);

        DB::connection('neo4j')->enableQueryLog();
    }

    public function test_it_logs_write_queries(): void
    {
        DB::connection('neo4j')->write(
            'CREATE (n:TestNode {name: $name}) RETURN n',
            ['name' => 'Test Node']
        );

        $data = $this->collector->collect();

        $this->assertEquals(1, $data['nb_statements']);
        $this->assertEquals(
            'CREATE (n:TestNode {name: $name}) RETURN n',
            $data['statements'][0]['sql']
        );
        $this->assertEquals(
            ['name' => 'Test Node'],
            $data['statements'][0]['params']
        );
        $this->assertIsFloat($data['statements'][0]['duration']);
        $this->assertEquals('neo4j', $data['statements'][0]['connection']);
    }

    public function test_it_logs_read_queries(): void
    {
        DB::connection('neo4j')->write(
            'CREATE (n:TestNode {name: $name})',
            ['name' => 'Test Node']
        );

        $this->collector->reset();

        DB::connection('neo4j')->read(
            'MATCH (n:TestNode {name: $name}) RETURN n',
            ['name' => 'Test Node']
        );

        $data = $this->collector->collect();

        $this->assertEquals(1, $data['nb_statements']);
        $this->assertEquals(
            'MATCH (n:TestNode {name: $name}) RETURN n',
            $data['statements'][0]['sql']
        );
        $this->assertEquals(
            ['name' => 'Test Node'],
            $data['statements'][0]['params']
        );
        $this->assertIsFloat($data['statements'][0]['duration']);
    }

    public function test_it_logs_multiple_queries(): void
    {
        $connection = DB::connection('neo4j');

        $connection->write('CREATE (n:TestNode {name: $name})', ['name' => 'Node 1']);
        $connection->write('CREATE (n:TestNode {name: $name})', ['name' => 'Node 2']);
        $connection->read('MATCH (n:TestNode) RETURN n');

        $data = $this->collector->collect();

        $this->assertEquals(3, $data['nb_statements']);
    }

    public function test_it_logs_query_time(): void
    {
        DB::connection('neo4j')->write('
            UNWIND range(1, 1000) AS i
            CREATE (n:TestNode {value: i})
        ');

        $data = $this->collector->collect();

        $this->assertGreaterThan(0, $data['statements'][0]['duration']);
    }

    protected function tearDown(): void
    {
        DB::connection('neo4j')->write('MATCH (n:TestNode) DELETE n');

        parent::tearDown();
    }
}
