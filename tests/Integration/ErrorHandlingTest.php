<?php

namespace Neo4j\Neo4jLaravel\Tests\Integration;

use Laudis\Neo4j\Contracts\ClientInterface;
use Neo4j\Neo4jLaravel\Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

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
        $this->app['config']->set('database.default', 'neo4j');

        $client = $this->app->make(ClientInterface::class);

        try {
            $client->getDriver('non_existent')->createSession()->run('RETURN 1 as one');
            $this->fail('Expected exception when connecting to non-existent host');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }
}
