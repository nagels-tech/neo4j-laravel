<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit;

use Neo4jPhp\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class ConfigurationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [Neo4jServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('neo4j.default_driver', 'testing');
        $app['config']->set('neo4j.drivers.testing', [
            'alias' => 'testing',
            'uri' => 'bolt://localhost:7687',
            'username' => 'neo4j',
            'password' => 'password',
            'authentication' => [
                'scheme' => 'basic',
                'username' => 'neo4j',
                'password' => 'password',
            ],
            'driver_config' => [
                'connection_timeout' => 30,
                'max_pool_size' => 100,
                'ssl' => [
                    'mode' => 'from_url',
                    'verify_peer' => true,
                ],
            ],
            'session_config' => [
                'database' => 'neo4j',
            ],
            'transaction_config' => [
                'timeout' => 30,
            ],
        ]);
    }

    public function test_config_is_loaded(): void
    {
        $this->assertEquals('testing', config('neo4j.default_driver'));
        $this->assertIsArray(config('neo4j.drivers'));
        $this->assertArrayHasKey('testing', config('neo4j.drivers'));
    }

    public function test_default_driver_config_exists(): void
    {
        $config = config('neo4j.default_driver_config');
        $this->assertIsArray($config);
        $this->assertEquals(30, $config['connection_timeout']);
        $this->assertEquals(100, $config['max_pool_size']);
        $this->assertArrayHasKey('ssl', $config);
        $this->assertEquals('from_url', $config['ssl']['mode']);
        $this->assertTrue($config['ssl']['verify_peer']);
    }

    public function test_default_session_config_exists(): void
    {
        $config = config('neo4j.default_session_config');
        $this->assertIsArray($config);
        $this->assertEquals('neo4j', $config['database']);
    }

    public function test_default_transaction_config_exists(): void
    {
        $config = config('neo4j.default_transaction_config');
        $this->assertIsArray($config);
        $this->assertEquals(30, $config['timeout']);
    }

    public function test_driver_configuration(): void
    {
        $driver = config('neo4j.drivers.testing');
        $this->assertEquals('testing', $driver['alias']);
        $this->assertEquals('bolt://localhost:7687', $driver['uri']);
        $this->assertEquals('neo4j', $driver['username']);
        $this->assertEquals('password', $driver['password']);

        // Authentication config
        $this->assertArrayHasKey('authentication', $driver);
        $this->assertEquals('basic', $driver['authentication']['scheme']);
        $this->assertEquals('neo4j', $driver['authentication']['username']);
        $this->assertEquals('password', $driver['authentication']['password']);

        // Driver config
        $this->assertArrayHasKey('driver_config', $driver);
        $this->assertEquals(30, $driver['driver_config']['connection_timeout']);
        $this->assertEquals(100, $driver['driver_config']['max_pool_size']);
        $this->assertArrayHasKey('ssl', $driver['driver_config']);
        $this->assertEquals('from_url', $driver['driver_config']['ssl']['mode']);
        $this->assertTrue($driver['driver_config']['ssl']['verify_peer']);

        // Session config
        $this->assertArrayHasKey('session_config', $driver);
        $this->assertEquals('neo4j', $driver['session_config']['database']);

        // Transaction config
        $this->assertArrayHasKey('transaction_config', $driver);
        $this->assertEquals(30, $driver['transaction_config']['timeout']);
    }
}
