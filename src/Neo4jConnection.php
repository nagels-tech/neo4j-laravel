<?php

namespace Neo4jPhp\Neo4jLaravel;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar as QueryGrammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\Schema\Grammars\Grammar as SchemaGrammar;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use PDO;
use Neo4jPhp\Neo4jLaravel\Debug\Neo4jQueryCollector;
use Illuminate\Support\Facades\App;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class Neo4jConnection extends Connection
{
    private ClientInterface $client;
    private ?UnmanagedTransactionInterface $transaction = null;
    private ?PDO $pdoMock = null;
    private ?Neo4jQueryCollector $queryCollector = null;

    public function __construct(
        ClientInterface $client,
        string $database = 'neo4j',
        string $tablePrefix = '',
        array $config = []
    ) {
        $this->client = $client;
        // Use closure as PDO replacement since we can't pass null here
        parent::__construct(function () {
            return null;
        }, $database, $tablePrefix, $config);

        // Set up debugbar collector if available
        if (App::bound(Neo4jQueryCollector::class)) {
            $this->queryCollector = App::make(Neo4jQueryCollector::class);
        }
    }

    /**
     * Get the client instance.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    /**
     * Begin a new database transaction.
     */
    #[\Override]
    public function beginTransaction(): TransactionInterface
    {
        return $this->client->beginTransaction();
    }

    /**
     * Commit the active database transaction.
     *
     * @throws \Throwable
     */
    #[\Override]
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
    #[\Override]
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
    #[\Override]
    public function pretend(\Closure $callback): array
    {
        return [];
    }

    /**
     * Run a Cypher statement and return the result.
     *
     * @param string $query Cypher query string
     * @param array<string, mixed> $parameters The parameters for the Cypher query
     * @return mixed The query result
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function runCypher(string $query, array $parameters = []): mixed
    {
        /** @var array<string, mixed> $parameters */
        return $this->transaction
            ? $this->transaction->run($query, $parameters)
            : $this->client->run($query, $parameters);
    }

    /**
     * Run a Cypher statement in write mode.
     *
     * @param string $query Cypher query string
     * @param array<string, mixed> $parameters The parameters for the Cypher query
     * @return mixed The query result
     */
    public function write(string $query, array $parameters = []): mixed
    {
        /** @var array<string, mixed> $parameters */
        return $this->client->writeTransaction(
            static function (TransactionInterface $tx) use ($query, $parameters): mixed {
                return $tx->run($query, $parameters);
            }
        );
    }

    /**
     * Run a Cypher statement in read mode.
     *
     * @param string $query Cypher query string
     * @param array<string, mixed> $parameters The parameters for the Cypher query
     * @return mixed The query result
     */
    public function read(string $query, array $parameters = []): mixed
    {
        /** @var array<string, mixed> $parameters */
        return $this->client->readTransaction(
            static function (TransactionInterface $tx) use ($query, $parameters): mixed {
                return $tx->run($query, $parameters);
            }
        );
    }

    /**
     * Get the current PDO connection.
     * This is required by Laravel's Connection class but not used for Neo4j.
     *
     * @return PDO
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    #[\Override]
    public function getPdo()
    {
        // Create a mock PDO object to satisfy type checks
        if ($this->pdoMock === null) {
            // Create a mock PDO using SQLite memory
            $this->pdoMock = new PDO('sqlite::memory:');
        }

        return $this->pdoMock;
    }

    /**
     * Get the current PDO connection used for reading.
     * This is required by Laravel's Connection class but not used for Neo4j.
     *
     * @return PDO
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    #[\Override]
    public function getReadPdo()
    {
        return $this->getPdo();
    }

    /**
     * Get the database connection name.
     */
    #[\Override]
    public function getName(): string
    {
        return $this->getConfig('name') ?? 'neo4j';
    }

    /**
     * Get the database name.
     */
    public function getDatabaseName(): string
    {
        return $this->database;
    }

    /**
     * Switch to a different Neo4j database within the same connection.
     *
     * @param string $database The database name to switch to
     * @return self Returns this connection for chaining
     */
    public function useDatabase(string $database): self
    {
        $this->database = $database;

        return $this;
    }

    /**
     * Run a select statement against the database.
     */
    #[\Override]
    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        try {
            $result = $this->runQueryCallback($query, $bindings, function () use ($query, $bindings) {
                $result = $this->read($query, $bindings);
                return is_array($result) ? $result : [$result];
            });
            return $result;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    #[\Override]
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
    #[\Override]
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
    #[\Override]
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
    #[\Override]
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
    #[\Override]
    public function affectingStatement($query, $bindings = []): int
    {
        $result = $this->write($query, $bindings);

        return $result->summaryCounters()->nodesCreated() +
            $result->summaryCounters()->nodesDeleted() +
            $result->summaryCounters()->propertiesSet();
    }

    /**
     * Get the default query grammar instance.
     *
     * @return QueryGrammar
     */
    #[\Override]
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new Neo4jQueryGrammar());
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return SchemaGrammar
     */
    #[\Override]
    protected function getDefaultSchemaGrammar()
    {
        return new Neo4jSchemaGrammar();
    }

    /**
     * Get the default post processor instance.
     *
     * @return Processor
     */
    #[\Override]
    protected function getDefaultPostProcessor()
    {
        return new Neo4jProcessor();
    }

    /**
     * Get an attribute from the connection.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     *
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress UnusedParam
     */
    public function getAttribute($key, $default = null)
    {
        return $default;
    }

    /**
     * Get the table prefix for the connection.
     *
     * @return string
     */
    #[\Override]
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * Set the table prefix in use by the connection.
     *
     * @param  string  $prefix
     * @return $this
     */
    #[\Override]
    public function setTablePrefix($prefix): static
    {
        $this->tablePrefix = $prefix;

        return $this;
    }

    /**
     * Get the connection query log.
     *
     * @return array
     */
    #[\Override]
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Clear the query log.
     *
     * @return void
     */
    #[\Override]
    public function flushQueryLog(): void
    {
        $this->queryLog = [];
    }

    /**
     * Enable the query log on the connection.
     *
     * @return void
     */
    #[\Override]
    public function enableQueryLog(): void
    {
        $this->loggingQueries = true;
    }

    /**
     * Disable the query log on the connection.
     *
     * @return void
     */
    #[\Override]
    public function disableQueryLog(): void
    {
        $this->loggingQueries = false;
    }

    /**
     * Determine whether we're logging queries.
     *
     * @return bool
     */
    #[\Override]
    public function logging(): bool
    {
        return $this->loggingQueries;
    }

    /**
     * Get the connection resolver for the given driver.
     *
     * @param  string  $driver
     * @return \Closure|null
     */
    #[\Override]
    public static function getResolver($driver): ?\Closure
    {
        return static::$resolvers[$driver] ?? null;
    }

    /**
     * Get the connection configuration.
     * 
     * @param string|null $name The configuration option name or null for all configuration
     * @return mixed The configuration value or all configuration
     */
    public function getConfig($name = null)
    {
        if ($name) {
            return $this->config[$name] ?? null;
        }

        return $this->config;
    }

    public function logQuery($query, $bindings, $time = null): void
    {
        // Call parent logging
        parent::logQuery($query, $bindings, $time);

        // Add to debugbar if available
        if ($this->queryCollector !== null) {
            $this->queryCollector->addQuery(
                $query,
                $bindings,
                $time,
                $this->getName()
            );
        }
    }

    protected function runQueryCallback($query, $bindings, \Closure $callback)
    {
        $start = microtime(true);

        try {
            $result = parent::runQueryCallback($query, $bindings, $callback);

            $this->logQuery($query, $bindings, microtime(true) - $start);

            return $result;
        } catch (\Exception $e) {
            $this->logQuery($query, $bindings, microtime(true) - $start);
            throw $e;
        }
    }
}
