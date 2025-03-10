<?php

namespace Neo4jPhp\Neo4jLaravel\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Types\Node;
use Neo4jPhp\Neo4jLaravel\Neo4jServiceProvider;
use Neo4jPhp\Neo4jLaravel\Tests\TestCase;

class Neo4jConnectionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Add test-specific connection settings
        $app['config']->set('database.connections.neo4j.connection', [
            'timeout' => 5,
        ]);
    }

    public function testCanConnectToNeo4j(): void
    {
        $client = $this->app->make(ClientInterface::class);
        $result = $client->verifyConnectivity();
        $this->assertTrue($result);
    }

    public function testCanCreateAndQueryNode(): void
    {
        $client = $this->app->make(ClientInterface::class);

        $result = $client->writeTransaction(function (TransactionInterface $tx) {
            return $tx->run('
                CREATE (n:TestNode {name: $name, created: datetime()})
                RETURN n
            ', ['name' => 'test_node']);
        });

        $node = $result->first()->get('n');
        $this->assertInstanceOf(Node::class, $node);
        $this->assertEquals('test_node', $node->getProperty('name'));

        $result = $client->readTransaction(function (TransactionInterface $tx) {
            return $tx->run('
                MATCH (n:TestNode {name: $name})
                RETURN n
            ', ['name' => 'test_node']);
        });

        $node = $result->first()->get('n');
        $this->assertInstanceOf(Node::class, $node);
        $this->assertEquals('test_node', $node->getProperty('name'));
    }

    public function testCanUseMultipleTransactions(): void
    {
        $client = $this->app->make(ClientInterface::class);

        $client->writeTransaction(function (TransactionInterface $tx) {
            $tx->run('
                CREATE (n:TestNode {name: "node1"})
                CREATE (m:TestNode {name: "node2"})
            ');
        });

        $client->writeTransaction(function (TransactionInterface $tx) {
            $tx->run('
                MATCH (n:TestNode {name: "node1"})
                MATCH (m:TestNode {name: "node2"})
                CREATE (n)-[r:RELATES_TO]->(m)
                RETURN r
            ');
        });

        $result = $client->readTransaction(function (TransactionInterface $tx) {
            return $tx->run('
                MATCH (n:TestNode {name: "node1"})-[r:RELATES_TO]->(m:TestNode {name: "node2"})
                RETURN n, r, m
            ');
        });

        $record = $result->first();
        $this->assertInstanceOf(Node::class, $record->get('n'));
        $this->assertInstanceOf(Node::class, $record->get('m'));
        $this->assertEquals('node1', $record->get('n')->getProperty('name'));
        $this->assertEquals('node2', $record->get('m')->getProperty('name'));
    }

    public function testHandlesLargeDatasets(): void
    {
        $client = $this->app->make(ClientInterface::class);

        $client->writeTransaction(function (TransactionInterface $tx) {
            $tx->run('
                UNWIND range(1, 100) as i
                CREATE (n:BatchNode {id: i, name: "node_" + i})
            ');
        });

        $result = $client->readTransaction(function (TransactionInterface $tx) {
            return $tx->run('
                MATCH (n:BatchNode)
                RETURN n
                ORDER BY n.id
                SKIP 50 LIMIT 10
            ');
        });

        $nodes = $result->toArray();
        $this->assertCount(10, $nodes);
        $this->assertEquals(51, $nodes[0]->get('n')->getProperty('id'));
        $this->assertEquals(60, $nodes[9]->get('n')->getProperty('id'));
    }

    public function testHandlesConcurrentTransactions(): void
    {
        $client = $this->app->make(ClientInterface::class);

        $client->writeTransaction(function (TransactionInterface $tx) {
            $tx->run('
                CREATE (n:ConcurrentNode {counter: 0})
            ');
        });

        $promises = [];
        for ($i = 0; $i < 5; $i++) {
            $promises[] = $client->writeTransaction(function (TransactionInterface $tx) {
                return $tx->run('
                    MATCH (n:ConcurrentNode)
                    SET n.counter = n.counter + 1
                    RETURN n.counter as counter
                ');
            });
        }

        $result = $client->readTransaction(function (TransactionInterface $tx) {
            return $tx->run('
                MATCH (n:ConcurrentNode)
                RETURN n.counter as counter
            ');
        });

        $counter = $result->first()->get('counter');
        $this->assertEquals(5, $counter);
    }

    protected function tearDown(): void
    {
        $client = $this->app->make(ClientInterface::class);
        $client->writeTransaction(function (TransactionInterface $tx) {
            $tx->run('
                MATCH (n)
                WHERE n:TestNode OR n:BatchNode OR n:ConcurrentNode
                DETACH DELETE n
            ');
        });

        parent::tearDown();
    }
}
