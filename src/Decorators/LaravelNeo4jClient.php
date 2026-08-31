<?php

namespace Neo4jPhp\Neo4jLaravel\Decorators;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Types\CypherList;
use Neo4j\Neo4jLaravel\Neo4jConnection;

final class LaravelNeo4jClient implements ClientInterface
{
    public function __construct(ClientInterface $client, Neo4jConnection $connection)
    {
    }

    public function run(string $statement, iterable $parameters = [], ?string $alias = null): SummarizedResult
    {
        // TODO: Implement run() method.
    }

    public function runStatement(Statement $statement, ?string $alias = null): SummarizedResult
    {
        // TODO: Implement runStatement() method.
    }

    public function runStatements(iterable $statements, ?string $alias = null): CypherList
    {
        // TODO: Implement runStatements() method.
    }

    public function beginTransaction(?iterable $statements = null, ?string $alias = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        // TODO: Implement beginTransaction() method.
    }

    public function getDriver(?string $alias): DriverInterface
    {
        // TODO: Implement getDriver() method.
    }

    public function hasDriver(string $alias): bool
    {
        // TODO: Implement hasDriver() method.
    }

    public function writeTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        // TODO: Implement writeTransaction() method.
    }

    public function readTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        // TODO: Implement readTransaction() method.
    }

    public function transaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        // TODO: Implement transaction() method.
    }

    public function verifyConnectivity(?string $driver = null): bool
    {
        // TODO: Implement verifyConnectivity() method.
    }

    public function bindTransaction(?string $alias = null, ?TransactionConfiguration $config = null): void
    {
        // TODO: Implement bindTransaction() method.
    }

    public function commitBoundTransaction(?string $alias = null, int $depth = 1): void
    {
        // TODO: Implement commitBoundTransaction() method.
    }

    public function rollbackBoundTransaction(?string $alias = null, int $depth = 1): void
    {
        // TODO: Implement rollbackBoundTransaction() method.
    }
}