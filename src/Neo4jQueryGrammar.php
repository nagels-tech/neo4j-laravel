<?php

namespace Neo4j\Neo4jLaravel;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use InvalidArgumentException;
use RuntimeException;

/**
 * Neo4j Cypher query grammar for the Laravel Query Builder.
 *
 * Supported today (select path):
 *   - from() / table() -> MATCH (n:Label)
 *   - select(columns)  -> RETURN n.col, ...
 *   - where (Basic, Null, NotNull, In, NotIn, Between/NotBetween, Nested)
 *   - orderBy -> ORDER BY
 *   - limit   -> LIMIT
 *   - offset  -> SKIP
 *
 * The write path (insert/update/delete) intentionally throws so SQL is never
 * silently generated for Neo4j. Raw Cypher via statement() remains supported.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class Neo4jQueryGrammar extends Grammar
{
    private int $parameterIndex = 0;

    public function __construct()
    {
        // Connection may be injected via setConnection() on Laravel 11+.
    }

    /**
     * Compile a select query into Cypher.
     *
     * Example:
     *   table('User')->where('name', 'Pratiksha')
     *   => MATCH (n:User) WHERE n.name = $p0 RETURN n
     */
    #[\Override]
    public function compileSelect(Builder $query): string
    {
        $this->parameterIndex = 0;

        $label = $this->compileLabel($query->from);
        $cypher = "MATCH (n:{$label})";

        $wheres = $this->compileWheres($query);
        if ($wheres !== '') {
            $cypher .= ' '.$wheres;
        }

        $cypher .= ' '.$this->compileReturn($query);

        $orders = $this->compileOrders($query, $query->orders ?? []);
        if ($orders !== '') {
            $cypher .= ' '.$orders;
        }

        if ($query->offset !== null) {
            $cypher .= ' SKIP '.(int) $query->offset;
        }

        if ($query->limit !== null) {
            $cypher .= ' LIMIT '.(int) $query->limit;
        }

        return $cypher;
    }

    private function compileReturn(Builder $query): string
    {
        $columns = $query->columns ?? null;

        if ($columns === null || $columns === [] || $columns === ['*']) {
            return 'RETURN n';
        }

        $parts = [];
        foreach ($columns as $column) {
            if (! is_string($column) || $column === '*') {
                return 'RETURN n';
            }
            $parts[] = $this->compileColumnReference($column);
        }

        return 'RETURN '.implode(', ', $parts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $orders
     */
    #[\Override]
    public function compileOrders(Builder $query, $orders): string
    {
        if (empty($orders)) {
            return '';
        }

        $parts = [];
        foreach ($orders as $order) {
            $column = $this->compileColumnReference($order['column']);
            $direction = strtolower((string) ($order['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $parts[] = "{$column} {$direction}";
        }

        return 'ORDER BY '.implode(', ', $parts);
    }

    #[\Override]
    public function compileWheres(Builder $query): string
    {
        if (empty($query->wheres)) {
            return '';
        }

        $body = $this->compileWheresToString($query->wheres);

        return $body === '' ? '' : 'WHERE '.$body;
    }

    /**
     * @param  array<int, array<string, mixed>>  $wheres
     */
    private function compileWheresToString(array $wheres): string
    {
        $parts = [];
        foreach ($wheres as $index => $where) {
            $boolean = $index === 0 ? '' : strtoupper((string) ($where['boolean'] ?? 'and')).' ';
            $parts[] = $boolean.$this->compileWhereClause($where);
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function compileWhereClause(array $where): string
    {
        $type = $where['type'] ?? null;

        return match ($type) {
            'Basic' => $this->compileBasicWhere($where),
            'Null' => $this->compileColumnReference($where['column']).' IS NULL',
            'NotNull' => $this->compileColumnReference($where['column']).' IS NOT NULL',
            'In' => $this->compileInWhere($where, false),
            'NotIn' => $this->compileInWhere($where, true),
            'between' => $this->compileBetweenWhere($where),
            'Nested' => $this->compileNestedWhere($where),
            default => throw new RuntimeException("Unsupported where type for Neo4j Query Builder: {$type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function compileBasicWhere(array $where): string
    {
        $column = $this->compileColumnReference($where['column']);
        $operator = $this->normalizeOperator((string) $where['operator']);
        $param = $this->nextParameter();

        return "{$column} {$operator} {$param}";
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function compileInWhere(array $where, bool $negate): string
    {
        $column = $this->compileColumnReference($where['column']);
        $values = $where['values'] ?? [];

        $params = [];
        foreach ($values as $ignored) {
            $params[] = $this->nextParameter();
        }

        $list = '['.implode(', ', $params).']';
        $clause = "{$column} IN {$list}";

        return $negate ? "NOT ({$clause})" : $clause;
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function compileBetweenWhere(array $where): string
    {
        $column = $this->compileColumnReference($where['column']);
        $low = $this->nextParameter();
        $high = $this->nextParameter();

        $clause = "({$column} >= {$low} AND {$column} <= {$high})";

        return ! empty($where['not']) ? "NOT {$clause}" : $clause;
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function compileNestedWhere(array $where): string
    {
        $nested = $where['query'] ?? null;
        if (! $nested instanceof Builder) {
            throw new RuntimeException('Invalid nested where clause for Neo4j Query Builder.');
        }

        $body = $this->compileWheresToString($nested->wheres ?? []);

        return $body === '' ? '' : "({$body})";
    }

    private function nextParameter(): string
    {
        return '$p'.$this->parameterIndex++;
    }

    private function normalizeOperator(string $operator): string
    {
        $normalized = strtoupper(trim($operator));

        if ($normalized === '!=') {
            $normalized = '<>';
        }

        $allowed = [
            '=', '<>', '<', '<=', '>', '>=',
            'CONTAINS', 'STARTS WITH', 'ENDS WITH', '=~',
        ];

        if (! in_array($normalized, $allowed, true)) {
            throw new InvalidArgumentException("Unsupported operator for Neo4j Query Builder: {$operator}");
        }

        return $normalized;
    }

    private function compileLabel(mixed $from): string
    {
        if (! is_string($from) || $from === '') {
            throw new InvalidArgumentException('Neo4j Query Builder requires a node label via from()/table().');
        }

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?::[A-Za-z_][A-Za-z0-9_]*)*$/', $from)) {
            throw new InvalidArgumentException("Invalid Neo4j label: {$from}");
        }

        return $from;
    }

    private function compileColumnReference(mixed $column): string
    {
        if (! is_string($column) || $column === '') {
            throw new InvalidArgumentException('Invalid column reference for Neo4j Query Builder.');
        }

        if (str_contains($column, '.')) {
            [$alias, $property] = explode('.', $column, 2);
            $this->assertIdentifier($alias);
            $this->assertIdentifier($property);

            return "{$alias}.{$property}";
        }

        $this->assertIdentifier($column);

        return 'n.'.$column;
    }

    private function assertIdentifier(string $identifier): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("Invalid Neo4j identifier: {$identifier}");
        }
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function getOperators(): array
    {
        return [
            '=', '<', '>', '<=', '>=', '<>', '!=',
            'contains', 'starts with', 'ends with', '=~',
        ];
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return string
     */
    #[\Override]
    public function compileInsert(Builder $query, array $values)
    {
        throw new RuntimeException('Query Builder insert is not supported yet for Neo4j; use raw Cypher via statement().');
    }

    /**
     * @param  array<string, mixed>  $values
     * @return string
     */
    #[\Override]
    public function compileUpdate(Builder $query, array $values)
    {
        throw new RuntimeException('Query Builder update is not supported yet for Neo4j; use raw Cypher via statement().');
    }

    /**
     * @return string
     */
    #[\Override]
    public function compileDelete(Builder $query)
    {
        throw new RuntimeException('Query Builder delete is not supported yet for Neo4j; use raw Cypher via statement().');
    }
}
