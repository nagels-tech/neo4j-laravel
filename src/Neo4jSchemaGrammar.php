<?php

namespace Neo4jPhp\Neo4jLaravel;

use Illuminate\Database\Schema\Grammars\Grammar;

/**
 * Minimal implementation of a Neo4j Schema Grammar
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class Neo4jSchemaGrammar extends Grammar
{
    // Override constructor to avoid parameter requirements
    public function __construct()
    {
        // No-op constructor
    }
}
