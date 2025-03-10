<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Neo4jPhp\Neo4jLaravel\Neo4jConnection;
use Neo4jPhp\Neo4jLaravel\Tests\TestCase;

class RuntimeConnectionTest extends TestCase
{
    public function testCanAddRuntimeConnection(): void
    {
        // Add a new connection at runtime
        Config::set('database.connections.neo4j_runtime', [
            'driver' => 'neo4j',
            'host' => env('NEO4J_HOST', 'neo4j'),
            'port' => (int)env('NEO4J_PORT', '7687'),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => 'runtime_password',
            'database' => 'runtime_db',
        ]);

        // Get the connection
        $connection = DB::connection('neo4j_runtime');

        // Verify the connection was created with correct config
        $this->assertInstanceOf(Neo4jConnection::class, $connection);

        // Check the configuration
        $this->assertEquals('neo4j_runtime', $connection->getName());
        $this->assertEquals('runtime_db', $connection->getDatabaseName());
    }

    public function testRuntimeConnectionUsesCorrectCredentials(): void
    {
        // Add a new connection at runtime
        Config::set('database.connections.neo4j_credentials', [
            'driver' => 'neo4j',
            'host' => env('NEO4J_HOST', 'neo4j'),
            'port' => (int)env('NEO4J_PORT', '7687'),
            'username' => 'runtime_user',
            'password' => 'runtime_pass',
            'database' => env('NEO4J_DATABASE', 'neo4j'),
        ]);

        // Get the connection
        $connection = DB::connection('neo4j_credentials');

        // Verify the connection has the right credentials
        $this->assertEquals('runtime_user', $connection->getConfig('username'));
        $this->assertEquals('runtime_pass', $connection->getConfig('password'));
    }
}
