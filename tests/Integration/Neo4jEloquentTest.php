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

    public function testEloquentSupportsAggregatesExistsIncrementAndDateFilters(): void
    {
        User::create(['name' => 'Ada', 'score' => 10, 'status' => 'active']);
        User::create(['name' => 'Alan', 'score' => 20, 'status' => 'active']);
        User::create(['name' => 'Grace', 'score' => 5, 'status' => 'inactive']);

        self::assertSame(3, User::count());
        self::assertTrue(User::where('name', 'Ada')->exists());
        self::assertFalse(User::where('name', 'Missing')->exists());
        self::assertSame(35, (int) User::sum('score'));
        self::assertSame(20, (int) User::max('score'));
        self::assertSame(5, (int) User::min('score'));

        $ada = User::where('name', 'Ada')->firstOrFail();
        User::where('id', $ada->id)->increment('score', 3);
        self::assertSame(13, (int) User::where('id', $ada->id)->value('score'));

        self::assertSame(
            2,
            User::whereColumn('status', 'status')->where('status', 'active')->count()
        );

        self::assertGreaterThanOrEqual(
            1,
            User::whereYear('created_at', now()->year)->count()
        );

        $grouped = User::query()
            ->select('status')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        self::assertCount(2, $grouped);
        self::assertSame(['active', 'inactive'], $grouped->pluck('status')->all());
    }
}

final class User extends Neo4jModel
{
    protected $guarded = [];
}
