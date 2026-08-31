<?php

namespace Neo4j\Neo4jLaravel;

use Illuminate\Database\Eloquent\Model;
use Neo4j\Neo4jLaravel\Concerns\HasNeo4jConnection;

/**
 * Convenience base class for an Eloquent model backed by a Neo4j node.
 */
abstract class Neo4jModel extends Model
{
    use HasNeo4jConnection;
}
