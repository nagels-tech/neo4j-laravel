<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit;

use Neo4jPhp\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class DatabaseConfigurationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [Neo4jServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Set up Laravel-style database configuration for Neo4j
        $app['config']->set('database.default', 'neo4j');
        $app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'name' => 'default',
            'url' => 'bolt://localhost:7687',
            'host' => 'localhost',
            'port' => 7687,
            'database' => 'neo4j',
            'username' => 'neo4j',
            'password' => 'password',
            'scheme' => 'basic',
            'ssl' => [
                'mode' => 'from_url',
                'verify_peer' => true,
            ],
            'pool' => [
                'max_size' => 100,
            ],
            'timeout' => [
                'connection' => 30,
                'transaction' => 30,
            ],
        ]);

        // Set up a second connection for testing multiple connections
        $app['config']->set('database.connections.neo4j_secondary', [
            'driver' => 'neo4j',
            'name' => 'secondary',
            'url' => 'bolt://localhost:7688',
            'host' => 'localhost',
            'port' => 7688,
            'database' => 'other-db',
            'username' => 'neo4j',
            'password' => 'password',
            'scheme' => 'kerberos',
            'ticket' => 'kerberos-ticket',
            'ssl' => [
                'mode' => 'enable',
                'verify_peer' => false,
            ],
            'pool' => [
                'max_size' => 200,
            ],
            'timeout' => [
                'connection' => 60,
                'transaction' => 60,
            ],
        ]);
    }

    public function test_default_connection_is_neo4j(): void
    {
        $this->assertEquals('neo4j', config('database.default'));
    }

    public function test_neo4j_connection_exists(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertIsArray($config);
        $this->assertEquals('neo4j', $config['driver']);
    }

    public function test_connection_has_required_fields(): void
    {
        $config = config('database.connections.neo4j');

        $requiredFields = [
            'driver',
            'name',
            'url',
            'host',
            'port',
            'database',
            'username',
            'password',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $config, "Configuration missing required field: {$field}");
        }
    }

    public function test_connection_url_is_constructed_correctly(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertEquals('bolt://localhost:7687', $config['url']);
    }

    public function test_authentication_configuration(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertEquals('basic', $config['scheme']);
        $this->assertEquals('neo4j', $config['username']);
        $this->assertEquals('password', $config['password']);
    }

    public function test_ssl_configuration(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertArrayHasKey('ssl', $config);
        $this->assertEquals('from_url', $config['ssl']['mode']);
        $this->assertTrue($config['ssl']['verify_peer']);
    }

    public function test_pool_configuration(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertArrayHasKey('pool', $config);
        $this->assertEquals(100, $config['pool']['max_size']);
    }

    public function test_timeout_configuration(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertArrayHasKey('timeout', $config);
        $this->assertEquals(30, $config['timeout']['connection']);
        $this->assertEquals(30, $config['timeout']['transaction']);
    }

    public function test_multiple_connections(): void
    {
        $secondary = config('database.connections.neo4j_secondary');

        $this->assertEquals('neo4j', $secondary['driver']);
        $this->assertEquals('secondary', $secondary['name']);
        $this->assertEquals('bolt://localhost:7688', $secondary['url']);
        $this->assertEquals('other-db', $secondary['database']);
        $this->assertEquals('kerberos', $secondary['scheme']);
        $this->assertEquals('kerberos-ticket', $secondary['ticket']);
        $this->assertEquals('enable', $secondary['ssl']['mode']);
        $this->assertFalse($secondary['ssl']['verify_peer']);
        $this->assertEquals(200, $secondary['pool']['max_size']);
        $this->assertEquals(60, $secondary['timeout']['connection']);
        $this->assertEquals(60, $secondary['timeout']['transaction']);
    }

    public function test_different_authentication_schemes(): void
    {
        // Test basic auth
        $basicAuth = config('database.connections.neo4j');
        $this->assertEquals('basic', $basicAuth['scheme']);
        $this->assertArrayHasKey('username', $basicAuth);
        $this->assertArrayHasKey('password', $basicAuth);

        // Test kerberos auth
        $kerberosAuth = config('database.connections.neo4j_secondary');
        $this->assertEquals('kerberos', $kerberosAuth['scheme']);
        $this->assertArrayHasKey('ticket', $kerberosAuth);
    }
}
