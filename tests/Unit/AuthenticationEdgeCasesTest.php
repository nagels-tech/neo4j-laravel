<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit;

use Illuminate\Contracts\Container\BindingResolutionException;
use Laudis\Neo4j\Contracts\ClientInterface;
use Neo4j\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AuthenticationEdgeCasesTest extends TestCase
{
    private ClientInterface|MockObject $clientMock;

    protected function getPackageProviders($app): array
    {
        return [Neo4jServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientMock = $this->createMock(ClientInterface::class);
        $this->app->instance(ClientInterface::class, $this->clientMock);
    }

    public function testInvalidAuthenticationScheme(): void
    {
        $this->app['config']->set('database.connections.invalid_scheme', [
            'driver' => 'neo4j',
            'url' => 'bolt://localhost:7687',
            'username' => 'neo4j',
            'password' => 'password',
            'database' => 'neo4j',
            'auth_scheme' => 'invalid_scheme',
        ]);

        $this->clientMock
            ->expects($this->once())
            ->method('getDriver')
            ->with('invalid_scheme')
            ->willThrowException(
                new BindingResolutionException('Unsupported authentication scheme: invalid_scheme')
            );

        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Unsupported authentication scheme: invalid_scheme');

        $this->clientMock->getDriver('invalid_scheme');
    }
}
