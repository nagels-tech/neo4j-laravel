<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Unit;

use Illuminate\Contracts\Container\BindingResolutionException;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Exception\Neo4jException;
use Neo4jPhp\Neo4jLaravel\Tests\TestCase;

class AuthenticationEdgeCasesTest extends TestCase
{
    public function testEmptyCredentialsHandling(): void
    {
        $this->app['config']->set('database.connections.empty_creds', [
            'driver' => 'neo4j',
            'url' => sprintf(
                'bolt://%s:%s',
                env('NEO4J_HOST', 'neo4j'),
                env('NEO4J_PORT', '7687')
            ),
            'username' => '',
            'password' => '',
            'database' => 'neo4j',
        ]);

        $this->expectException(Neo4jException::class);
        $this->expectExceptionMessage('Neo.ClientError.Security.Unauthorized');

        $this->app->make(ClientInterface::class)->getDriver('empty_creds');
    }

    public function testInvalidAuthenticationScheme(): void
    {
        $this->app['config']->set('database.connections.invalid_scheme', [
            'driver' => 'neo4j',
            'url' => sprintf(
                'bolt://%s:%s',
                env('NEO4J_HOST', 'neo4j'),
                env('NEO4J_PORT', '7687')
            ),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'testtest'),
            'database' => 'neo4j',
            'auth_scheme' => 'invalid_scheme',
        ]);

        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Unsupported authentication scheme: invalid_scheme');

        $this->app->make(ClientInterface::class)->getDriver('invalid_scheme');
    }
}
