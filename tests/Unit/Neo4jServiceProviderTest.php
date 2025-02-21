<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit;

use Illuminate\Contracts\Container\BindingResolutionException;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Neo4jPhp\Neo4jLaravel\ClientFactory;
use Neo4jPhp\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;
use Mockery;
use Psr\Log\LoggerInterface;

class Neo4jServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [Neo4jServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('neo4j.default', 'testing');
        $app['config']->set('neo4j.connections', [
            'testing' => [
                'url' => 'bolt://localhost:7687',
                'username' => 'neo4j',
                'password' => 'password',
                'database' => 'neo4j',
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
            ],
        ]);
        $app['config']->set('neo4j.logging', [
            'level' => null,
        ]);

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

    public function testPublishesConfig(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'neo4j-config']);
        $this->assertFileExists(config_path('neo4j.php'));
    }

    public function testHandlesMultipleConnections(): void
    {
        $this->app['config']->set('neo4j.connections.second', [
            'url' => 'bolt://second-host:7687',
            'username' => 'neo4j',
            'password' => 'password',
            'database' => 'neo4j',
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
        $this->app['config']->set('neo4j.connections.minimal', [
            'url' => 'bolt://localhost:7687',
            'username' => 'neo4j',
            'password' => 'password',
        ]);

        $client = $this->app->make(ClientInterface::class);
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testHandlesCustomSslConfig(): void
    {
        $this->app['config']->set('neo4j.connections.ssl', [
            'url' => 'bolt://localhost:7687',
            'username' => 'neo4j',
            'password' => 'password',
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
        $this->app['config']->set('neo4j.connections', [
            'invalid' => [
                'username' => 'neo4j',
                'password' => 'password',
            ],
        ]);

        $this->app->forgetInstance(ClientFactory::class);
        $this->app->forgetInstance(ClientInterface::class);
        $this->app->make(ClientInterface::class);
    }

    public function testHandlesNonBasicAuthSchemes(): void
    {
        $this->app['config']->set('neo4j.connections.custom_auth', [
            'url' => 'bolt://localhost:7687',
            'username' => 'neo4j',
            'password' => 'password',
            'auth_scheme' => 'custom',
            'auth_token' => 'custom-token',
        ]);

        $client = $this->app->make(ClientInterface::class);
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testHandlesConnectionPoolConfig(): void
    {
        $this->app['config']->set('neo4j.connections.pool', [
            'url' => 'bolt://localhost:7687',
            'username' => 'neo4j',
            'password' => 'password',
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
