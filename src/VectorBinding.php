<?php

namespace Neo4j\Neo4jLaravel;

/**
 * Keeps an embedding list as one query binding.
 *
 * Laravel's addBinding()/getBindings() flatten nested arrays, which would
 * turn a vector into individual floats.
 */
final class VectorBinding
{
    /**
     * @param  list<float>  $values
     */
    public function __construct(public readonly array $values)
    {
    }
}
