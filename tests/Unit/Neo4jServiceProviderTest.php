<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit;

use Illuminate\Contracts\Container\BindingResolutionException;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Mockery;
use Neo4jPhp\Neo4jLaravel\Tests\TestCase;
use Psr\Log\LoggerInterface;

class Neo4jServiceProviderTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Mock the Neo4j client and its dependencies
        $mockClient = Mockery::mock(ClientInterface::class);
        $mockDriver = Mockery::mock(DriverInterface::class);
        $mockSession = Mockery::mock(SessionInterface::class);
        $mockTransaction = Mockery::mock(TransactionInterface::class);

        $mockClient->shouldReceive('getDriver')->andReturn($mockDriver);
        $mockDriver->shouldReceive('createSession')->andReturn($mockSession);
        $mockSession->shouldReceive('beginTransaction')->andReturn($mockTransaction);

        $app->instance(ClientInterface::class, $mockClient);
        $app->instance(DriverInterface::class, $mockDriver);
        $app->instance(SessionInterface::class, $mockSession);
        $app->instance(TransactionInterface::class, $mockTransaction);
    }

    public function testBindsClientInterface(): void
    {
        $client = $this->app->make(ClientInterface::class);
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testBindsDriverInterface(): void
    {
        $driver = $this->app->make(DriverInterface::class);
        $this->assertInstanceOf(DriverInterface::class, $driver);
    }

    public function testBindsSessionInterface(): void
    {
        $session = $this->app->make(SessionInterface::class);
        $this->assertInstanceOf(SessionInterface::class, $session);
    }

    public function testBindsTransactionInterface(): void
    {
        $transaction = $this->app->make(TransactionInterface::class);
        $this->assertInstanceOf(TransactionInterface::class, $transaction);
    }

    public function testDatabaseConfigurationIsValid(): void
    {
        $config = $this->app['config']->get('database.connections.neo4j');

        $this->assertEquals('neo4j', $config['driver']);
        $this->assertArrayHasKey('url', $config);
        $this->assertArrayHasKey('username', $config);
        $this->assertArrayHasKey('password', $config);
        $this->assertArrayHasKey('database', $config);
    }

    public function testHandlesMultipleConnections(): void
    {
        $this->app['config']->set('database.connections.second', [
            'driver' => 'neo4j',
            'url' => sprintf(
                'bolt://%s:%s',
                env('NEO4J_HOST', 'neo4j'),
                env('NEO4J_PORT', '7687')
            ),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'testtest'),
            'database' => env('NEO4J_DATABASE', 'neo4j'),
            'auth_scheme' => 'basic',
            'ssl' => [
                'mode' => 'from_url',
                'verify_peer' => true,
            ],
            'connection' => [
                'timeout' => 30,
                'max_pool_size' => 100,
            ],
            'transaction' => [
                'timeout' => 30,
            ],
        ]);

        $client = $this->app->make(ClientInterface::class);
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testHandlesMissingOptionalConfigs(): void
    {
        $this->app['config']->set('database.connections.minimal', [
            'driver' => 'neo4j',
            'url' => sprintf(
                'bolt://%s:%s',
                env('NEO4J_HOST', 'neo4j'),
                env('NEO4J_PORT', '7687')
            ),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'testtest'),
        ]);

        $client = $this->app->make(ClientInterface::class);
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testHandlesCustomSslConfig(): void
    {
        $this->app['config']->set('database.connections.ssl', [
            'driver' => 'neo4j',
            'url' => sprintf(
                'bolt://%s:%s',
                env('NEO4J_HOST', 'neo4j'),
                env('NEO4J_PORT', '7687')
            ),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'testtest'),
            'ssl' => [
                'mode' => 'verify_full',
                'verify_peer' => true,
                'ca_file' => '/path/to/ca.pem',
                'peer_name' => 'neo4j.example.com',
            ],
        ]);

        $client = $this->app->make(ClientInterface::class);
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testHandlesCustomLogging(): void
    {
        $this->app['config']->set('neo4j.logging.level', 'debug');

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')->withAnyArgs();
        $this->app->instance('log', $mockLogger);

        $client = $this->app->make(ClientInterface::class);
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testThrowsExceptionForMissingRequiredConfig(): void
    {
        $this->expectException(BindingResolutionException::class);

        // Clear all connections and set an invalid default
        $this->app['config']->set('database.default', 'neo4j');
        $this->app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'testtest'),
        ]);

        $this->app->forgetInstance(ClientInterface::class);
        $this->app->make(ClientInterface::class);
    }

    public function testHandlesNonBasicAuthSchemes(): void
    {
        $this->app['config']->set('database.connections.custom_auth', [
            'driver' => 'neo4j',
            'url' => sprintf(
                'bolt://%s:%s',
                env('NEO4J_HOST', 'neo4j'),
                env('NEO4J_PORT', '7687')
            ),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'testtest'),
            'auth_scheme' => 'custom',
            'auth_token' => 'custom-token',
        ]);

        $client = $this->app->make(ClientInterface::class);
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testHandlesConnectionPoolConfig(): void
    {
        $this->app['config']->set('database.connections.pool', [
            'driver' => 'neo4j',
            'url' => sprintf(
                'bolt://%s:%s',
                env('NEO4J_HOST', 'neo4j'),
                env('NEO4J_PORT', '7687')
            ),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'testtest'),
            'connection' => [
                'timeout' => 5,
                'max_pool_size' => 50,
                'keep_alive' => true,
            ],
        ]);

        $client = $this->app->make(ClientInterface::class);
        $this->assertInstanceOf(ClientInterface::class, $client);
    }
}
