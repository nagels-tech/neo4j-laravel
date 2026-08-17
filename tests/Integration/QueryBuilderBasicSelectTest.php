<?php

namespace Neo4j\Neo4jLaravel\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Neo4j\Neo4jLaravel\Tests\TestCase;

class QueryBuilderBasicSelectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanup();
        $client = $this->app->make(ClientInterface::class);
        $client->writeTransaction(function (TransactionInterface $tx): void {
            $tx->run('CREATE (u:User {name: $name, age: $age})', [
                'name' => 'Pratiksha',
                'age' => 25,
            ]);
            $tx->run('CREATE (u:User {name: $name, age: $age})', [
                'name' => 'Ghlen',
                'age' => 30,
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public function testTableWhereGetReturnsMatchingRows(): void
    {
        $results = DB::connection('neo4j')
            ->table('User')
            ->where('name', 'Pratiksha')
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Pratiksha', $results[0]['name']);
        $this->assertSame(25, $results[0]['age']);
    }

    public function testToSqlCompilesCypherNotSql(): void
    {
        $sql = DB::connection('neo4j')
            ->table('User')
            ->where('name', 'Pratiksha')
            ->toSql();

        $this->assertStringContainsString('MATCH (n:User)', $sql);
        $this->assertStringContainsString('WHERE n.name = $p0', $sql);
        $this->assertStringContainsString('RETURN n', $sql);
        $this->assertStringNotContainsString('select *', strtolower($sql));
    }

    public function testWhereOrderLimit(): void
    {
        $results = DB::connection('neo4j')
            ->table('User')
            ->where('age', '>', 20)
            ->orderBy('name')
            ->limit(10)
            ->get();

        $this->assertCount(2, $results);
        $this->assertSame('Ghlen', $results[0]['name']);
        $this->assertSame('Pratiksha', $results[1]['name']);
    }

    public function testWhereIn(): void
    {
        $results = DB::connection('neo4j')
            ->table('User')
            ->whereIn('name', ['Pratiksha', 'Ghlen'])
            ->orderBy('name')
            ->get();

        $this->assertCount(2, $results);
        $this->assertSame('Ghlen', $results[0]['name']);
        $this->assertSame('Pratiksha', $results[1]['name']);
    }

    public function testWhereBetween(): void
    {
        $results = DB::connection('neo4j')
            ->table('User')
            ->whereBetween('age', [24, 26])
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Pratiksha', $results[0]['name']);
    }

    public function testNestedWhereWithOr(): void
    {
        $results = DB::connection('neo4j')
            ->table('User')
            ->where(function ($query): void {
                $query->where('name', 'Pratiksha')
                    ->orWhere('name', 'Ghlen');
            })
            ->orderBy('name')
            ->get();

        $this->assertCount(2, $results);
    }

    public function testWhereContainsOperator(): void
    {
        $results = DB::connection('neo4j')
            ->table('User')
            ->where('name', 'CONTAINS', 'rati')
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Pratiksha', $results[0]['name']);
    }

    public function testInequalityOperatorCompilesToCypher(): void
    {
        $sql = DB::connection('neo4j')
            ->table('User')
            ->where('age', '!=', 25)
            ->toSql();

        $this->assertStringContainsString('n.age <> $p0', $sql);
    }

    public function testSelectSpecificColumns(): void
    {
        $sql = DB::connection('neo4j')
            ->table('User')
            ->select('name', 'age')
            ->where('name', 'Pratiksha')
            ->toSql();

        $this->assertStringContainsString('RETURN n.name, n.age', $sql);
    }

    private function cleanup(): void
    {
        $client = $this->app->make(ClientInterface::class);
        $client->writeTransaction(function (TransactionInterface $tx): void {
            $tx->run('MATCH (u:User) WHERE u.name IN $names DETACH DELETE u', [
                'names' => ['Pratiksha', 'Ghlen'],
            ]);
        });
    }
}
