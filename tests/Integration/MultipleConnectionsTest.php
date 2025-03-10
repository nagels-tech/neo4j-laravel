<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Integration;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Neo4jPhp\Neo4jLaravel\Tests\TestCase;

class MultipleConnectionsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Define a second connection
        $app['config']->set('database.connections.neo4j_secondary', [
            'driver' => 'neo4j',
            'url' => sprintf(
                'bolt://%s:%s',
                env('NEO4J_HOST', 'neo4j'),
                env('NEO4J_PORT', '7687')
            ),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'testtest'),
            'database' => 'neo4j',
        ]);
    }

    public function testCanConnectToPrimaryDatabase(): void
    {
        $client = $this->app->make(ClientInterface::class);
        $result = $client->verifyConnectivity();
        $this->assertTrue($result);

        // Verify we're using the primary connection
        $result = $client->writeTransaction(function (TransactionInterface $tx) {
            return $tx->run('
                CREATE (n:TestPrimaryNode {name: "primary_test"})
                RETURN n.name as name
            ');
        });

        $this->assertEquals('primary_test', $result->first()->get('name'));
    }

    public function testCanSwitchBetweenConnections(): void
    {
        // Get the default client (primary)
        $primaryClient = $this->app->make(ClientInterface::class);

        // Create a node in the primary connection
        $primaryClient->writeTransaction(function (TransactionInterface $tx) {
            $tx->run('
                CREATE (n:TestConnectionNode {connection: "primary"})
            ');
        });

        // Get the secondary client
        $secondaryClient = $primaryClient->getDriver('neo4j_secondary')->createSession()->beginTransaction();

        // Create a node in the secondary connection
        $secondaryClient->run('
            CREATE (n:TestConnectionNode {connection: "secondary"})
        ');
        $secondaryClient->commit();

        // Verify both nodes exist in their respective connections
        $primaryResult = $primaryClient->readTransaction(function (TransactionInterface $tx) {
            return $tx->run('
                MATCH (n:TestConnectionNode {connection: "primary"})
                RETURN count(n) as count
            ');
        });

        $secondaryResult = $primaryClient->getDriver('neo4j_secondary')->createSession()->run('
            MATCH (n:TestConnectionNode {connection: "secondary"})
            RETURN count(n) as count
        ');

        $this->assertEquals(1, $primaryResult->first()->get('count'));
        $this->assertEquals(1, $secondaryResult->first()->get('count'));
    }

    protected function tearDown(): void
    {
        // Clean up test data in both connections
        $client = $this->app->make(ClientInterface::class);

        // Clean primary
        $client->writeTransaction(function (TransactionInterface $tx) {
            $tx->run('
                MATCH (n:TestPrimaryNode) DETACH DELETE n
            ');
            $tx->run('
                MATCH (n:TestConnectionNode) DETACH DELETE n
            ');
        });

        // Clean secondary
        $client->getDriver('neo4j_secondary')->createSession()->run('
            MATCH (n:TestConnectionNode) DETACH DELETE n
        ');

        parent::tearDown();
    }
}
