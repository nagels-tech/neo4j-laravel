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
        $app['config']->set('database.default', 'neo4j');

        // Set up default connection
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

        // Set up a second connection for testing multiple connections
        $app['config']->set('database.connections.neo4j_secondary', [
            'driver' => 'neo4j',
            'name' => 'secondary',
            'url' => 'bolt://localhost:7688',
            'database' => 'other-db',
            'username' => 'neo4j',
            'password' => 'password',
            'auth_scheme' => 'kerberos',
            'ticket' => 'kerberos-ticket',
            'ssl' => [
                'mode' => 'enable',
                'verify_peer' => false,
            ],
            'connection' => [
                'max_pool_size' => 200,
                'timeout' => 60,
            ],
            'transaction' => [
                'timeout' => 60,
            ],
        ]);
    }

    public function testDefaultConnectionIsNeo4j(): void
    {
        $this->assertEquals('neo4j', config('database.default'));
    }

    public function testNeo4jConnectionExists(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertIsArray($config);
        $this->assertEquals('neo4j', $config['driver']);
    }

    public function testConnectionHasRequiredFields(): void
    {
        $config = config('database.connections.neo4j');

        $requiredFields = [
            'driver',
            'url',
            'username',
            'password',
            'database',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $config, "Configuration missing required field: {$field}");
        }
    }

    public function testConnectionUrlIsConstructedCorrectly(): void
    {
        $config = config('database.connections.neo4j');
        $expectedUrl = 'bolt://localhost:7687';
        $this->assertEquals($expectedUrl, $config['url']);
    }

    public function testAuthenticationConfiguration(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertEquals('neo4j', $config['username']);
        $this->assertEquals('password', $config['password']);
    }

    public function testSslConfiguration(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertArrayHasKey('ssl', $config);
        $this->assertEquals('from_url', $config['ssl']['mode']);
        $this->assertTrue($config['ssl']['verify_peer']);
    }

    public function testPoolConfiguration(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertArrayHasKey('connection', $config);
        $this->assertEquals(100, $config['connection']['max_pool_size']);
    }

    public function testTimeoutConfiguration(): void
    {
        $config = config('database.connections.neo4j');
        $this->assertArrayHasKey('connection', $config);
        $this->assertEquals(30, $config['connection']['timeout']);
    }

    public function testMultipleConnections(): void
    {
        $secondary = config('database.connections.neo4j_secondary');

        $this->assertEquals('neo4j', $secondary['driver']);
        $this->assertEquals('secondary', $secondary['name']);
        $this->assertEquals('bolt://localhost:7688', $secondary['url']);
        $this->assertEquals('other-db', $secondary['database']);
        $this->assertEquals('kerberos', $secondary['auth_scheme']);
        $this->assertEquals('kerberos-ticket', $secondary['ticket']);
        $this->assertEquals('enable', $secondary['ssl']['mode']);
        $this->assertFalse($secondary['ssl']['verify_peer']);
        $this->assertEquals(200, $secondary['connection']['max_pool_size']);
        $this->assertEquals(60, $secondary['connection']['timeout']);
        $this->assertEquals(60, $secondary['transaction']['timeout']);
    }

    public function testDifferentAuthenticationSchemes(): void
    {
        // Test basic auth (default connection)
        $basicAuth = config('database.connections.neo4j');
        $this->assertEquals('neo4j', $basicAuth['username']);
        $this->assertEquals('password', $basicAuth['password']);

        // Test kerberos auth
        $kerberosAuth = config('database.connections.neo4j_secondary');
        $this->assertEquals('kerberos', $kerberosAuth['auth_scheme']);
        $this->assertEquals('kerberos-ticket', $kerberosAuth['ticket']);
    }
}
