<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
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
            'host' => 'localhost',
            'port' => 7687,
            'username' => 'neo4j',
            'password' => 'password',
            'database' => 'neo4j',
        ]);
    }

    public function testCanAddConnectionAtRuntime(): void
    {
        DB::purge('neo4j_runtime');

        config(['database.connections.neo4j_runtime' => [
            'driver' => 'neo4j',
            'host' => 'localhost',
            'port' => 7688,
            'username' => 'neo4j',
            'password' => 'password',
            'database' => 'neo4j',
        ]]);

        $this->assertNotNull(DB::connection('neo4j_runtime'));
    }
}
