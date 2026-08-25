<?php

namespace Neo4j\Neo4jLaravel\Tests\Integration;

use Neo4j\Neo4jLaravel\Neo4jConnection;
use Neo4j\Neo4jLaravel\Neo4jModel;
use Neo4j\Neo4jLaravel\Tests\TestCase;

final class Neo4jVectorSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var Neo4jConnection $connection */
        $connection = $this->app->make('db')->connection('neo4j');
        $connection->statement('MATCH (n:Document) DETACH DELETE n');
        $connection->dropVectorIndex('document_embedding');
        $connection->createVectorIndex('document_embedding', 'Document', 'embedding', 3);
        $connection->statement("CALL db.awaitIndex('document_embedding')");
    }

    protected function tearDown(): void
    {
        /** @var Neo4jConnection $connection */
        $connection = $this->app->make('db')->connection('neo4j');
        $connection->statement('MATCH (n:Document) DETACH DELETE n');
        $connection->dropVectorIndex('document_embedding');

        parent::tearDown();
    }

    public function testFindsSimilarDocumentsByEmbedding(): void
    {
        Document::create([
            'title' => 'Graphs',
            'embedding' => [1.0, 0.0, 0.0],
        ]);
        Document::create([
            'title' => 'Cooking',
            'embedding' => [0.0, 1.0, 0.0],
        ]);

        $matches = Document::query()
            ->whereVectorSimilarTo('embedding', [1.0, 0.0, 0.0], minSimilarity: 0.9)
            ->limit(10)
            ->get();

        self::assertCount(1, $matches);
        self::assertSame('Graphs', $matches->first()->title);
        self::assertGreaterThan(0.9, $matches->first()->score);
    }
}

final class Document extends Neo4jModel
{
    protected $guarded = [];
}
