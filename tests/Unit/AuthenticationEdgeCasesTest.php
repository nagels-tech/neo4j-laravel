<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit;

use Illuminate\Contracts\Container\BindingResolutionException;
use Neo4jPhp\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class AuthenticationEdgeCasesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [Neo4jServiceProvider::class];
    }

    public function testEmptyCredentialsHandling(): void
    {
        $this->app['config']->set('database.default', 'neo4j');
        $this->app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'url' => 'bolt://neo4j:7687',
            'username' => '', // Empty username
            'password' => '', // Empty password
            'database' => 'neo4j',
        ]);

        // This should construct without errors even with empty credentials
        // The actual connection will fail when trying to connect, but the construction should work
        $client = $this->app->make(\Laudis\Neo4j\Contracts\ClientInterface::class);
        $this->assertNotNull($client);
    }

    public function testInvalidAuthenticationScheme(): void
    {
        $this->app['config']->set('database.default', 'neo4j');
        $this->app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'url' => 'bolt://neo4j:7687',
            'username' => 'neo4j',
            'password' => 'testtest',
            'database' => 'neo4j',
            'auth_scheme' => 'invalid_scheme', // Invalid scheme
        ]);

        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Unsupported authentication scheme: invalid_scheme');

        $this->app->make(\Laudis\Neo4j\Contracts\ClientInterface::class);
    }
}
