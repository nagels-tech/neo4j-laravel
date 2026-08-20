<?php

namespace Neo4j\Neo4jLaravel\Concerns;

use Illuminate\Support\Str;

/**
 * Configure a standard Eloquent model for Neo4j node persistence.
 */
trait HasNeo4jConnection
{
    public static function bootHasNeo4jConnection(): void
    {
        static::creating(function ($model): void {
            $key = $model->getKeyName();

            if ($key !== null && $model->getAttribute($key) === null) {
                $model->setAttribute($key, (string) Str::uuid());
            }
        });
    }

    public function initializeHasNeo4jConnection(): void
    {
        $this->connection ??= 'neo4j';
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    /**
     * Use a node label instead of Eloquent's plural snake-case table name.
     */
    public function getTable(): string
    {
        return $this->table ?? Str::studly(class_basename($this));
    }

    public function getLabel(): string
    {
        return $this->getTable();
    }

    public function setLabel(string $label): static
    {
        return $this->setTable($label);
    }
}
