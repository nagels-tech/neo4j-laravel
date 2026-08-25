<?php

namespace Neo4j\Neo4jLaravel;

use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * Query builder with Neo4j-specific clauses such as vector similarity search.
 */
final class Neo4jQueryBuilder extends Builder
{
    public ?string $vectorIndex = null;

    /**
     * Restrict vector search to a named Neo4j vector index.
     */
    public function useVectorIndex(string $name): static
    {
        $this->vectorIndex = $name;

        return $this;
    }

    /**
     * Filter by cosine similarity against a stored embedding property.
     *
     * Same signature as Laravel 13's whereVectorSimilarTo(); this driver
     * requires a precomputed embedding array (the app generates embeddings).
     *
     * @param  array<int, float>  $vector
     */
    public function whereVectorSimilarTo(
        $column,
        $vector,
        $minSimilarity = 0.6,
        $order = true
    ): static {
        if (! is_array($vector) || $vector === []) {
            throw new InvalidArgumentException(
                'whereVectorSimilarTo() expects a non-empty embedding array; this driver does not generate embeddings.'
            );
        }

        foreach ($vector as $component) {
            if (! is_numeric($component)) {
                throw new InvalidArgumentException('Embedding vectors must contain only numeric components.');
            }
        }

        $this->wheres[] = [
            'type' => 'VectorSimilar',
            'column' => $column,
            'boolean' => 'and',
            'order' => (bool) $order,
        ];

        $this->addBinding(new VectorBinding(array_map(
            static fn ($value): float => (float) $value,
            array_values($vector)
        )), 'where');
        $this->addBinding((float) $minSimilarity, 'where');

        return $this;
    }
}
