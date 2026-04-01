<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit;

use Laudis\Neo4j\Contracts\ClientInterface;
use Neo4j\Neo4jLaravel\ClientFactory;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;
use Psr\Log\LoggerInterface;

class ClientFactoryTest extends TestCase
{
    private array $defaultDriverConfig = [
        'connection_timeout' => 30,
        'max_pool_size' => 100,
        'ssl' => [
            'mode' => 'from_url',
            'verify_peer' => true,
        ],
    ];

    private array $defaultSessionConfig = [
        'database' => 'neo4j',
    ];

    private array $defaultTransactionConfig = [
        'timeout' => 30,
    ];

    private array $connections;

    protected function getPackageProviders($app): array
    {
        return [Neo4jServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->connections = [
            [
                'alias' => 'default',
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
            ],
        ];
    }

    public function testCreatesClientWithoutLogger(): void
    {
        $factory = new ClientFactory(
            $this->defaultDriverConfig,
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
            $this->connections,
            null,
            null
        );

        $client = $factory->create();
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testCreatesClientWithLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $factory = new ClientFactory(
            $this->defaultDriverConfig,
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
            $this->connections,
            'debug',
            $logger
        );

        $client = $factory->create();
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testCreatesClientWithMultipleConnections(): void
    {
        $connections = [
            [
                'alias' => 'default',
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
            ],
            [
                'alias' => 'secondary',
                'uri' => 'bolt://localhost:7688',
                'username' => 'neo4j',
                'password' => 'password',
                'authentication' => [
                    'scheme' => 'kerberos',
                    'ticket' => 'kerberos-ticket',
                ],
                'driver_config' => [
                    'connection_timeout' => 60,
                    'max_pool_size' => 200,
                    'ssl' => [
                        'mode' => 'enable',
                        'verify_peer' => false,
                    ],
                ],
                'session_config' => [
                    'database' => 'other-db',
                ],
                'transaction_config' => [
                    'timeout' => 60,
                ],
            ],
        ];

        $factory = new ClientFactory(
            $this->defaultDriverConfig,
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
            $connections,
            null,
            null
        );

        $client = $factory->create();
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testCreatesClientWithOidcAuth(): void
    {
        $connections = [
            [
                'alias' => 'default',
                'uri' => 'bolt://localhost:7687',
                'username' => 'neo4j',
                'password' => 'password',
                'authentication' => [
                    'scheme' => 'oidc',
                    'token' => 'oidc-token',
                ],
            ],
        ];

        $factory = new ClientFactory(
            $this->defaultDriverConfig,
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
            $connections,
            null,
            null
        );

        $client = $factory->create();
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testCreatesClientWithNoAuth(): void
    {
        $connections = [
            [
                'alias' => 'default',
                'uri' => 'bolt://localhost:7687',
                'username' => 'neo4j',
                'password' => 'password',
                'authentication' => [
                    'scheme' => 'none',
                ],
            ],
        ];

        $factory = new ClientFactory(
            $this->defaultDriverConfig,
            $this->defaultSessionConfig,
            $this->defaultTransactionConfig,
            $connections,
            null,
            null
        );

        $client = $factory->create();
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testCreatesClientWithDifferentSslModes(): void
    {
        $sslModes = ['enable', 'enable_with_self_signed', 'disable', 'from_url'];

        foreach ($sslModes as $mode) {
            $connections = [
                [
                    'alias' => 'default',
                    'uri' => 'bolt://localhost:7687',
                    'username' => 'neo4j',
                    'password' => 'password',
                    'driver_config' => [
                        'ssl' => [
                            'mode' => $mode,
                            'verify_peer' => true,
                        ],
                    ],
                ],
            ];

            $factory = new ClientFactory(
                $this->defaultDriverConfig,
                $this->defaultSessionConfig,
                $this->defaultTransactionConfig,
                $connections,
                null,
                null
            );

            $client = $factory->create();
            $this->assertInstanceOf(ClientInterface::class, $client);
        }
    }
}
