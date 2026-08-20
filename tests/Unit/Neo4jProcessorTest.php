<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit;

use Illuminate\Database\Query\Builder;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Neo4j\Neo4jLaravel\Neo4jProcessor;
use PHPUnit\Framework\TestCase;

final class Neo4jProcessorTest extends TestCase
{
    public function testFlattensMatchedNodePropertiesForEloquentHydration(): void
    {
        $node = new Node(
            1,
            new CypherList(['User']),
            new CypherMap(['id' => 'user-1', 'name' => 'Pratiksha']),
            '4:abc:1'
        );

        $results = (new Neo4jProcessor())->processSelect(
            $this->createMock(Builder::class),
            [new CypherMap(['n' => $node])]
        );

        self::assertSame([
            ['id' => 'user-1', 'name' => 'Pratiksha'],
        ], $results);
    }

    public function testRemovesNodeAliasFromSelectedPropertyNames(): void
    {
        $results = (new Neo4jProcessor())->processSelect(
            $this->createMock(Builder::class),
            [new CypherMap(['n.name' => 'Pratiksha'])]
        );

        self::assertSame([['name' => 'Pratiksha']], $results);
    }
}
