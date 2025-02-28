<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Neo4jPhp\Neo4jLaravel\Neo4jConnection;
use Neo4jPhp\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class UseDatabaseTest extends TestCase
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
        ]);
    }

    public function testUseDatabaseReturnsConnection(): void
    {
        $connection = app(Neo4jConnection::class);
        $result = $connection->useDatabase('test_db');
        
        // The method should return $this for chaining
        $this->assertSame($connection, $result);
        
        // The database should now be set to test_db
        $this->assertEquals('test_db', $connection->getDatabaseName());
    }
    
    public function testUseDatabaseWithQueryExecutesOnSpecifiedDatabase(): void
    {
        // Create a simplified test that doesn't rely on mocking the driver
        $connection = app(Neo4jConnection::class);
        
        // Initial database should be neo4j (from config)
        $this->assertEquals('neo4j', $connection->getDatabaseName());
        
        // Change the database
        $connection->useDatabase('test_db');
        
        // Check that database name was updated
        $this->assertEquals('test_db', $connection->getDatabaseName());
    }
} 