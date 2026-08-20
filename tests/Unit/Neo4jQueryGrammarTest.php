<?php

namespace Neo4j\Neo4jLaravel\Tests\Unit;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use InvalidArgumentException;
use Laudis\Neo4j\Contracts\ClientInterface;
use Neo4j\Neo4jLaravel\Neo4jConnection;
use Neo4j\Neo4jLaravel\Neo4jQueryGrammar;
use PHPUnit\Framework\TestCase;

final class Neo4jQueryGrammarTest extends TestCase
{
    public function testCompilesSelectWithDslBackedClauses(): void
    {
        $builder = $this->builder()
            ->from('Person')
            ->where('name', 'Tom Hanks')
            ->whereIn('born', [1956, 1957])
            ->orderBy('name')
            ->offset(10)
            ->limit(5);

        self::assertSame(
            'MATCH (n:Person) WHERE ((n.name = $p0) AND (n.born IN [$p1, $p2])) '
                .'RETURN n ORDER BY n.name ASC SKIP 10 LIMIT 5',
            $builder->toSql()
        );
        self::assertSame(['Tom Hanks', 1956, 1957], $builder->getBindings());
    }

    public function testCompilesNestedWheresNullChecksAndStringOperators(): void
    {
        $builder = $this->builder()
            ->from('Person:Actor')
            ->where(function (Builder $query): void {
                $query->where('name', 'starts with', 'Tom')
                    ->orWhereNull('retired_at');
            })
            ->whereNotBetween('born', [1900, 2000]);

        self::assertSame(
            'MATCH (n:Person:Actor) WHERE (((n.name STARTS WITH $p0) OR (n.retired_at IS NULL)) '
                .'AND (NOT ((n.born >= $p1) AND (n.born <= $p2)))) RETURN n',
            $builder->toSql()
        );
    }

    public function testCompilesSelectedPropertiesDistinctAndMixedOrdering(): void
    {
        $builder = $this->builder()
            ->from('Person')
            ->distinct()
            ->select(['name', 'n.born'])
            ->orderBy('name')
            ->orderByDesc('born');

        self::assertSame(
            'MATCH (n:Person) RETURN DISTINCT n.name, n.born ORDER BY n.name ASC, n.born DESC',
            $builder->toSql()
        );
    }

    public function testCompilesEmptyInClausesWithoutBindings(): void
    {
        $builder = $this->builder()
            ->from('Person')
            ->whereIn('name', []);

        self::assertSame('MATCH (n:Person) WHERE false RETURN n', $builder->toSql());
        self::assertSame([], $builder->getBindings());
    }

    public function testRejectsInvalidLabels(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder()->from('Person`) MATCH (x')->toSql();
    }

    public function testMapsEloquentQualifiedColumnsToTheNodeVariable(): void
    {
        $builder = $this->builder()
            ->from('User')
            ->where('User.id', 'user-1');

        self::assertSame(
            'MATCH (n:User) WHERE (n.id = $p0) RETURN n',
            $builder->toSql()
        );
    }

    public function testConnectionMapsPositionalBindingsToCypherParameterNames(): void
    {
        $connection = new Neo4jConnection($this->createMock(ClientInterface::class));

        self::assertSame(
            ['p0' => 'Tom Hanks', 'p1' => 1956],
            $connection->prepareBindings(['Tom Hanks', 1956])
        );
        self::assertSame(
            ['name' => 'Tom Hanks'],
            $connection->prepareBindings(['name' => 'Tom Hanks'])
        );
    }

    public function testCompilesSingleAndBatchInserts(): void
    {
        $grammar = new Neo4jQueryGrammar();
        $builder = $this->builder()->from('User');

        self::assertSame(
            'CREATE (n0:User {id: $p0, name: $p1})',
            $grammar->compileInsert($builder, [['id' => 'user-1', 'name' => 'Pratiksha']])
        );
        self::assertSame(
            'CREATE (n0:User {id: $p0, name: $p1}), (n1:User {id: $p2, name: $p3})',
            $grammar->compileInsert($builder, [
                ['id' => 'user-1', 'name' => 'Pratiksha'],
                ['id' => 'user-2', 'name' => 'Ghlen'],
            ])
        );
    }

    public function testCompilesUpdateWithValueBindingsBeforeWhereBindings(): void
    {
        $grammar = new Neo4jQueryGrammar();
        $builder = $this->builder()
            ->from('User')
            ->where('id', 'user-1');

        self::assertSame(
            'MATCH (n:User) WHERE (n.id = $p1) SET n.name = $p0',
            $grammar->compileUpdate($builder, ['name' => 'Pratiksha Zalte'])
        );
        self::assertSame(
            ['Pratiksha Zalte', 'user-1'],
            $grammar->prepareBindingsForUpdate($builder->getRawBindings(), ['Pratiksha Zalte'])
        );
    }

    public function testCompilesDelete(): void
    {
        $grammar = new Neo4jQueryGrammar();
        $builder = $this->builder()
            ->from('User')
            ->where('id', 'user-1');

        self::assertSame(
            'MATCH (n:User) WHERE (n.id = $p0) DELETE n',
            $grammar->compileDelete($builder)
        );
    }

    private function builder(): Builder
    {
        return new Builder(
            $this->createMock(ConnectionInterface::class),
            new Neo4jQueryGrammar(),
            new Processor()
        );
    }
}
