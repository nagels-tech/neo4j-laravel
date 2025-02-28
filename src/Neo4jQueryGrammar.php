<?php

namespace Neo4jPhp\Neo4jLaravel;

use Illuminate\Database\Query\Grammars\Grammar;

/**
 * Minimal implementation of a Neo4j Query Grammar
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class Neo4jQueryGrammar extends Grammar
{
    // Override constructor to avoid parameter requirements
    public function __construct()
    {
        // No-op constructor
    }

    // Override methods as needed
}
