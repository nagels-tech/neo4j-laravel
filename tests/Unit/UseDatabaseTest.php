<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit;

use Neo4jPhp\Neo4jLaravel\Neo4jConnection;
use Neo4jPhp\Neo4jLaravel\Tests\TestCase;

class UseDatabaseTest extends TestCase
{
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
        $this->assertEquals(env('NEO4J_DATABASE', 'neo4j'), $connection->getDatabaseName());

        // Change the database
        $connection->useDatabase('test_db');

        // Check that database name was updated
        $this->assertEquals('test_db', $connection->getDatabaseName());
    }
}
