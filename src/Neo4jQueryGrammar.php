<?php

namespace Neo4j\Neo4jLaravel;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use InvalidArgumentException;
use RuntimeException;
use WikibaseSolutions\CypherDSL\Expressions\Operators\In;
use WikibaseSolutions\CypherDSL\Expressions\Parameter;
use WikibaseSolutions\CypherDSL\Expressions\Property;
use WikibaseSolutions\CypherDSL\Patterns\Node;
use WikibaseSolutions\CypherDSL\Query;
use WikibaseSolutions\CypherDSL\Types\PropertyTypes\BooleanType;

/**
 * Neo4j Cypher query grammar backed by php-cypher-dsl.
 *
 * Supported today (select path):
 *   - from() / table() -> MATCH (n:Label)
 *   - select(columns)  -> RETURN n.col, ...
 *   - where (Basic, Null, NotNull, In, NotIn, Between/NotBetween, Nested)
 *   - orderBy -> ORDER BY
 *   - limit   -> LIMIT
 *   - offset  -> SKIP
 *   - insert   -> CREATE
 *   - update   -> MATCH / SET
 *   - delete   -> MATCH / DELETE
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

        $node = $this->compileNode($query->from);
        $cypher = Query::new()->match($node);
        $where = $this->compileWhereExpression($query->wheres ?? [], $node);

        if ($where !== null) {
            $cypher->where($where);
        }

        $cypher->returning($this->compileReturn($query, $node), (bool) $query->distinct);

        $orders = $this->compileOrders($query, $query->orders ?? []);
        if ($orders !== '') {
            $cypher->raw('ORDER BY', substr($orders, strlen('ORDER BY ')));
        }

        if ($query->offset !== null) {
            $cypher->skip((int) $query->offset);
        }

        if ($query->limit !== null) {
            $cypher->limit((int) $query->limit);
        }

        return $cypher->build();
    }

    /**
     * @return Node|list<Property>
     */
    private function compileReturn(Builder $query, Node $node): Node|array
    {
        $columns = $query->columns ?? null;

        if ($columns === null || $columns === [] || $columns === ['*']) {
            return $node;
        }

        $parts = [];
        foreach ($columns as $column) {
            if (! is_string($column) || $column === '*') {
                return $node;
            }
            $parts[] = $this->compileColumn($column, $node);
        }

        return $parts;
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
            $column = $this->compileColumn((string) $order['column'])->toQuery();
            $direction = strtolower((string) ($order['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $parts[] = "{$column} {$direction}";
        }

        return 'ORDER BY '.implode(', ', $parts);
    }

    #[\Override]
    public function compileWheres(Builder $query): string
    {
        $this->parameterIndex = 0;

        if (empty($query->wheres)) {
            return '';
        }

        $expression = $this->compileWhereExpression($query->wheres, $this->compileNode($query->from));

        return $expression === null ? '' : Query::new()->where($expression)->build();
    }

    /**
     * @param  array<int, array<string, mixed>>  $wheres
     */
    private function compileWhereExpression(array $wheres, Node $node): ?BooleanType
    {
        $expression = null;

        foreach ($wheres as $where) {
            $clause = $this->compileWhereClause($where, $node);

            if ($expression === null) {
                $expression = $clause;

                continue;
            }

            $expression = strtolower((string) ($where['boolean'] ?? 'and')) === 'or'
                ? $expression->or($clause)
                : $expression->and($clause);
        }

        return $expression;
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function compileWhereClause(array $where, Node $node): BooleanType
    {
        $type = $where['type'] ?? null;

        return match ($type) {
            'Basic' => $this->compileBasicWhere($where, $node),
            'Null' => $this->compileColumn((string) $where['column'], $node)->isNull(),
            'NotNull' => $this->compileColumn((string) $where['column'], $node)->isNotNull(),
            'In' => $this->compileInWhere($where, $node, false),
            'NotIn' => $this->compileInWhere($where, $node, true),
            'between' => $this->compileBetweenWhere($where, $node),
            'Nested' => $this->compileNestedWhere($where, $node),
            default => throw new RuntimeException("Unsupported where type for Neo4j Query Builder: {$type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function compileBasicWhere(array $where, Node $node): BooleanType
    {
        $column = $this->compileColumn((string) $where['column'], $node);
        $operator = $this->normalizeOperator((string) $where['operator']);
        $param = $this->nextParameter();

        return match ($operator) {
            '=' => $column->equals($param),
            '<>' => $column->notEquals($param),
            '<' => $column->lt($param),
            '<=' => $column->lte($param),
            '>' => $column->gt($param),
            '>=' => $column->gte($param),
            'CONTAINS' => $column->contains($param),
            'STARTS WITH' => $column->startsWith($param),
            'ENDS WITH' => $column->endsWith($param),
            '=~' => $column->regex($param),
        };
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function compileInWhere(array $where, Node $node, bool $negate): BooleanType
    {
        $column = $this->compileColumn((string) $where['column'], $node);
        $values = $where['values'] ?? [];

        $params = [];
        foreach ($values as $ignored) {
            $params[] = $this->nextParameter();
        }

        if ($params === []) {
            return Query::rawExpression($negate ? 'true' : 'false');
        }

        $clause = new In($column, Query::list($params));

        return $negate ? $clause->not() : $clause;
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function compileBetweenWhere(array $where, Node $node): BooleanType
    {
        $column = $this->compileColumn((string) $where['column'], $node);
        $low = $this->nextParameter();
        $high = $this->nextParameter();

        $clause = $column->gte($low)->and($column->lte($high));

        return ! empty($where['not']) ? $clause->not() : $clause;
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function compileNestedWhere(array $where, Node $node): BooleanType
    {
        $nested = $where['query'] ?? null;
        if (! $nested instanceof Builder) {
            throw new RuntimeException('Invalid nested where clause for Neo4j Query Builder.');
        }

        $expression = $this->compileWhereExpression($nested->wheres ?? [], $node);

        if ($expression === null) {
            throw new RuntimeException('Nested where clause for Neo4j Query Builder cannot be empty.');
        }

        return $expression;
    }

    private function nextParameter(): Parameter
    {
        return Query::parameter($this->nextParameterName());
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

    private function compileNode(mixed $from): Node
    {
        if (! is_string($from) || $from === '') {
            throw new InvalidArgumentException('Neo4j Query Builder requires a node label via from()/table().');
        }

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?::[A-Za-z_][A-Za-z0-9_]*)*$/', $from)) {
            throw new InvalidArgumentException("Invalid Neo4j label: {$from}");
        }

        return Query::node()
            ->withLabels(explode(':', $from))
            ->withVariable('n');
    }

    private function compileColumn(mixed $column, ?Node $node = null): Property
    {
        if (! is_string($column) || $column === '') {
            throw new InvalidArgumentException('Invalid column reference for Neo4j Query Builder.');
        }

        if (str_contains($column, '.')) {
            [$alias, $property] = explode('.', $column, 2);
            $this->assertIdentifier($alias);
            $this->assertIdentifier($property);

            // Eloquent qualifies keys with the model table/label (for example,
            // User.id), while this single-node grammar always binds that label
            // to the Cypher variable "n".
            return Query::variable('n')->property($property);
        }

        $this->assertIdentifier($column);

        return ($node ?? Query::node()->withVariable('n'))->property($column);
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
        $this->parameterIndex = 0;
        $label = $this->compileLabel($query->from);
        $nodes = [];

        foreach (array_values($values) as $index => $record) {
            $nodes[] = sprintf(
                '(n%d:%s %s)',
                $index,
                $label,
                $this->compilePropertyMap(array_keys($record))
            );
        }

        return 'CREATE '.implode(', ', $nodes);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return string
     */
    #[\Override]
    public function compileUpdate(Builder $query, array $values)
    {
        $this->parameterIndex = 0;
        $assignments = [];

        foreach (array_keys($values) as $column) {
            $this->assertIdentifier((string) $column);
            $assignments[] = 'n.'.$column.' = $'.$this->nextParameterName();
        }

        $node = $this->compileNode($query->from);
        $cypher = Query::new()->match($node);
        $where = $this->compileWhereExpression($query->wheres ?? [], $node);
        $prefix = $cypher->build();

        if ($where !== null) {
            $prefix .= ' '.Query::new()->where($where)->build();
        }

        return $prefix.' SET '.implode(', ', $assignments);
    }

    /**
     * @return string
     */
    #[\Override]
    public function compileDelete(Builder $query)
    {
        $this->parameterIndex = 0;
        $node = $this->compileNode($query->from);
        $cypher = Query::new()->match($node);
        $where = $this->compileWhereExpression($query->wheres ?? [], $node);
        $prefix = $cypher->build();

        if ($where !== null) {
            $prefix .= ' '.Query::new()->where($where)->build();
        }

        return $prefix.' DELETE n';
    }

    /**
     * @param  list<string>  $columns
     */
    private function compilePropertyMap(array $columns): string
    {
        $properties = [];

        foreach ($columns as $column) {
            $this->assertIdentifier($column);
            $properties[] = $column.': $'.$this->nextParameterName();
        }

        return '{'.implode(', ', $properties).'}';
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

    private function nextParameterName(): string
    {
        return 'p'.$this->parameterIndex++;
    }
}
