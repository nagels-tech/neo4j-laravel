<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Neo4jPhp\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class ConnectionManagementTest extends TestCase
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
            'url' => 'bolt://neo4j:7687',
            'username' => 'neo4j',
            'password' => 'testtest',
            'database' => 'neo4j',
            'connection' => [
                'max_pool_size' => 5, // Small pool size for testing
                'timeout' => 2,       // Short timeout for testing
            ],
        ]);
    }

    public function testLaravelDatabaseInterface(): void
    {
        // Test that we can use Laravel's DB facade
        $result = DB::connection('neo4j')->statement('
            CREATE (n:TestDBFacade {name: "db_facade_test"})
            RETURN n.name as name
        ');

        $this->assertTrue($result);

        // Fetch using the DB facade
        $results = DB::connection('neo4j')->select('
            MATCH (n:TestDBFacade)
            RETURN n.name as name
        ');

        $this->assertCount(1, $results);
        $this->assertEquals('db_facade_test', $results[0][0]->get('name'));
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $client = $this->app->make(ClientInterface::class);
        $client->writeTransaction(function (TransactionInterface $tx) {
            $tx->run('
                MATCH (n)
                WHERE n:TestDBFacade
                DETACH DELETE n
            ');
        });

        parent::tearDown();
    }
}
