<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Neo4jPhp\Neo4jLaravel\Neo4jConnection;
use Neo4jPhp\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class RuntimeConnectionTest extends TestCase
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
            'host' => 'neo4j',
            'port' => 7687,
            'username' => 'neo4j',
            'password' => 'testtest',
            'database' => 'neo4j',
        ]);
    }

    public function testCanAddRuntimeConnection(): void
    {
        // Add a new connection at runtime
        Config::set('database.connections.neo4j_runtime', [
            'driver' => 'neo4j',
            'host' => 'neo4j',
            'port' => 7688, // Different port
            'username' => 'neo4j',
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
            'host' => 'neo4j',
            'port' => 7687,
            'username' => 'runtime_user',
            'password' => 'runtime_pass',
            'database' => 'neo4j',
        ]);

        // Get the connection
        $connection = DB::connection('neo4j_credentials');

        // Verify the connection has the right credentials
        $this->assertEquals('runtime_user', $connection->getConfig('username'));
        $this->assertEquals('runtime_pass', $connection->getConfig('password'));
    }
}
