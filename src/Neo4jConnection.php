<?php

namespace Neo4jPhp\Neo4jLaravel;

use Illuminate\Database\Connection;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;

class Neo4jConnection extends Connection
{
    private ClientInterface $client;
    private ?UnmanagedTransactionInterface $transaction = null;

    public function __construct(
        ClientInterface $client,
        string $database = '',
        string $tablePrefix = '',
        array $config = []
    ) {
        $this->client = $client;
        parent::__construct(null, $database, $tablePrefix, $config);
    }

    /**
     * Begin a new database transaction.
     *
     * @throws \Throwable
     */
    public function beginTransaction(): void
    {
        $this->transaction = $this->client->beginTransaction();
    }

    /**
     * Commit the active database transaction.
     *
     * @throws \Throwable
     */
    public function commit(): void
    {
        if ($this->transaction) {
            $this->transaction->commit();
            $this->transaction = null;
        }
    }

    /**
     * Rollback the active database transaction.
     *
     * @param  int|null  $toLevel
     * @return void
     *
     * @throws \Throwable
     */
    public function rollBack($toLevel = null)
    {
        if ($this->transaction) {
            $this->transaction->rollback();
            $this->transaction = null;
        }
    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param  \Closure  $callback
     * @return array
     */
    public function pretend(\Closure $callback): array
    {
        return [];
    }

    /**
     * Run a Cypher statement and return the result.
     */
    public function runCypher(string $query, array $parameters = []): mixed
    {
        return $this->transaction
            ? $this->transaction->run($query, $parameters)
            : $this->client->run($query, $parameters);
    }

    /**
     * Run a Cypher statement in write mode.
     */
    public function write(string $query, array $parameters = []): mixed
    {
        return $this->client->writeTransaction(
            static function (TransactionInterface $tx) use ($query, $parameters) {
                return $tx->run($query, $parameters);
            }
        );
    }

    /**
     * Run a Cypher statement in read mode.
     */
    public function read(string $query, array $parameters = []): mixed
    {
        return $this->client->readTransaction(
            static function (TransactionInterface $tx) use ($query, $parameters) {
                return $tx->run($query, $parameters);
            }
        );
    }

    /**
     * Get the current PDO connection.
     * This is required by Laravel's Connection class but not used for Neo4j.
     */
    public function getPdo(): mixed
    {
        return null;
    }

    /**
     * Get the current PDO connection used for reading.
     * This is required by Laravel's Connection class but not used for Neo4j.
     */
    public function getReadPdo(): mixed
    {
        return $this->getPdo();
    }

    /**
     * Get the database connection name.
     */
    public function getName(): string
    {
        return $this->getConfig('name');
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        return $this->read($query, $bindings)->toArray();
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function insert($query, $bindings = []): bool
    {
        return (bool) $this->write($query, $bindings);
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function update($query, $bindings = []): int
    {
        $result = $this->write($query, $bindings);
        return $result->summaryCounters()->nodesCreated() + $result->summaryCounters()->nodesDeleted();
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function delete($query, $bindings = []): int
    {
        $result = $this->write($query, $bindings);
        return $result->summaryCounters()->nodesDeleted();
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function statement($query, $bindings = []): bool
    {
        return (bool) $this->write($query, $bindings);
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = []): int
    {
        $result = $this->write($query, $bindings);
        return $result->summaryCounters()->nodesCreated() +
            $result->summaryCounters()->nodesDeleted() +
            $result->summaryCounters()->propertiesSet();
    }
}
