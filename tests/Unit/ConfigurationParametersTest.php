<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit;

use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class ConfigurationParametersTest extends TestCase
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
            'url' => 'bolt://localhost:7687',
            'username' => 'neo4j',
            'password' => 'password',
            'database' => 'neo4j',
            'ssl' => [
                'mode' => 'from_url',
                'verify_peer' => true,
            ],
            'connection' => [
                'max_pool_size' => 100,
                'timeout' => 30,
            ],
            'transaction' => [
                'timeout' => 30,
            ],
        ]);
    }

    public function testSslConfiguration(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertArrayHasKey('ssl', $config);
        $this->assertEquals('from_url', $config['ssl']['mode']);
        $this->assertTrue($config['ssl']['verify_peer']);
    }

    public function testConnectionConfiguration(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertArrayHasKey('connection', $config);
        $this->assertEquals(100, $config['connection']['max_pool_size']);
        $this->assertEquals(30, $config['connection']['timeout']);
    }

    public function testTransactionConfiguration(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertArrayHasKey('transaction', $config);
        $this->assertEquals(30, $config['transaction']['timeout']);
    }
}
