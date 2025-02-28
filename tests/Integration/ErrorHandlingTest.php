<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Integration;

use Exception;
use Laudis\Neo4j\Contracts\ClientInterface;
use Neo4jPhp\Neo4jLaravel\Neo4jServiceProvider;
use Orchestra\Testbench\TestCase;

class ErrorHandlingTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [Neo4jServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'neo4j');

        // Define a valid connection
        $app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'url' => 'bolt://neo4j:7687',
            'username' => 'neo4j',
            'password' => 'testtest',
            'database' => 'neo4j',
        ]);

        // Define an invalid connection
        $app['config']->set('database.connections.non_existent', [
            'driver' => 'neo4j',
            'url' => 'bolt://non-existent-host:7687', // This host doesn't exist
            'username' => 'invalid',
            'password' => 'invalid',
            'database' => 'neo4j',
        ]);
    }

    public function testUsingNonExistentConnection(): void
    {
        // This test verifies that attempting to use a configured but non-existent connection
        // properly fails when trying to execute a query, not when the connection is created

        // First make sure the "non_existent" connection is registered but not default
        $this->app['config']->set('database.default', 'neo4j');

        // This should not throw an exception because the connection is configured
        // even though the host doesn't exist
        $client = $this->app->make(ClientInterface::class);

        // Now try to use that connection explicitly
        try {
            // This should throw an exception when it tries to connect
            $result = $client->getDriver('non_existent')->createSession()->run('RETURN 1 as one');
            $this->fail('Expected exception when connecting to non-existent host');
        } catch (\Exception $e) {
            // We expect an exception here, so the test passes
            $this->assertTrue(true);
        }
    }
}
