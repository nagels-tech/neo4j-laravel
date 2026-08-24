<?php

namespace Neo4j\Neo4jLaravel\Debug;

use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherList;
use Neo4j\Neo4jLaravel\Neo4jConnection;

/**
 * Decorates an unmanaged Neo4j transaction so Cypher run through the
 * transaction object still goes through Neo4jConnection capture (once).
 *
 * The connection keeps the inner transaction for runCypher(); this wrapper
 * is only what beginTransaction() returns to application code.
 *
 * @internal
 */
final class CapturingUnmanagedTransaction implements UnmanagedTransactionInterface
{
    public function __construct(
        private readonly UnmanagedTransactionInterface $inner,
        private readonly Neo4jConnection $connection
    ) {
    }

    /**
     * @param iterable<string, mixed> $parameters
     */
    public function run(string $statement, iterable $parameters = []): SummarizedResult
    {
        $bindings = self::iterableToArray($parameters);

        /** @var SummarizedResult */
        return $this->connection->executeCaptured(
            $statement,
            $bindings,
            fn (): SummarizedResult => $this->inner->run($statement, $parameters)
        );
    }

    public function runStatement(Statement $statement): SummarizedResult
    {
        $bindings = self::iterableToArray($statement->getParameters());

        /** @var SummarizedResult */
        return $this->connection->executeCaptured(
            $statement->getText(),
            $bindings,
            fn (): SummarizedResult => $this->inner->runStatement($statement)
        );
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @return CypherList<SummarizedResult>
     */
    public function runStatements(iterable $statements): CypherList
    {
        $results = [];
        foreach ($statements as $statement) {
            $results[] = $this->runStatement($statement);
        }

        return CypherList::fromIterable($results);
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @return CypherList<SummarizedResult>
     */
    public function commit(iterable $statements = []): CypherList
    {
        $pending = [];
        foreach ($statements as $statement) {
            $pending[] = $statement;
        }

        if ($pending === []) {
            return $this->inner->commit();
        }

        // Capture each final statement, then commit the open transaction.
        $results = [];
        foreach ($pending as $statement) {
            $results[] = $this->runStatement($statement);
        }
        $this->inner->commit();

        return CypherList::fromIterable($results);
    }

    public function rollback(): void
    {
        $this->inner->rollback();
    }

    public function isRolledBack(): bool
    {
        return $this->inner->isRolledBack();
    }

    public function isCommitted(): bool
    {
        return $this->inner->isCommitted();
    }

    public function isFinished(): bool
    {
        return $this->inner->isFinished();
    }

    /**
     * @param iterable<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private static function iterableToArray(iterable $parameters): array
    {
        if (is_array($parameters)) {
            /** @var array<string, mixed> $parameters */
            return $parameters;
        }

        $bindings = [];
        foreach ($parameters as $key => $value) {
            $bindings[$key] = $value;
        }

        return $bindings;
    }
}
