<?php

namespace Neo4j\Neo4jLaravel\Tests\Integration;

use Neo4j\Neo4jLaravel\Neo4jModel;
use Neo4j\Neo4jLaravel\Tests\TestCase;

final class Neo4jEloquentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make('db')->connection('neo4j')
            ->statement('MATCH (n:User) DETACH DELETE n');
    }

    public function testEloquentModelSupportsBasicCrud(): void
    {
        $created = User::create(['name' => 'Pratiksha']);

        self::assertNotNull($created->id);
        self::assertSame('Pratiksha', $created->name);

        $found = User::where('name', 'Pratiksha')->first();

        self::assertInstanceOf(User::class, $found);
        self::assertSame($created->id, $found->id);
        self::assertSame($created->id, User::find($created->id)?->id);

        self::assertNotNull($created->created_at);
        self::assertNotNull($created->updated_at);

        $found->update(['name' => 'Pratiksha Zalte']);

        $updated = User::where('id', $created->id)->firstOrFail();

        self::assertSame('Pratiksha Zalte', $updated->name);
        self::assertNotNull($updated->updated_at);

        self::assertTrue($found->delete());
        self::assertNull(User::where('id', $created->id)->first());
    }
}

final class User extends Neo4jModel
{
    protected $guarded = [];
}
