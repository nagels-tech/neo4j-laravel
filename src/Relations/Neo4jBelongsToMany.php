<?php

namespace Neo4j\Neo4jLaravel\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Many-to-many without SQL JOINs.
 *
 * Resolves related models via the pivot label (node) with where / whereIn,
 * then loads related nodes by primary key. Compatible with stock
 * attach / detach / sync against a pivot label such as RoleUser.
 *
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends BelongsToMany<TRelatedModel, TDeclaringModel>
 */
class Neo4jBelongsToMany extends BelongsToMany
{
    /**
     * Parent keys when eager loading.
     *
     * @var list<mixed>
     */
    protected array $eagerParentKeys = [];

    protected bool $isEager = false;

    /**
     * Neo4j has no SQL JOIN; pivot matching is done in {@see get()}.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>|null  $query
     */
    #[\Override]
    protected function performJoin($query = null)
    {
        return $this;
    }

    /**
     * Constraints are applied when resolving through the pivot label.
     */
    #[\Override]
    protected function addWhereConstraints()
    {
        return $this;
    }

    /** @inheritDoc */
    #[\Override]
    public function addEagerConstraints(array $models)
    {
        $this->isEager = true;
        $this->eagerParentKeys = $this->getKeys($models, $this->parentKey);
    }

    /** @inheritDoc */
    #[\Override]
    public function get($columns = ['*'])
    {
        $parentKeys = $this->isEager
            ? $this->eagerParentKeys
            : array_values(array_filter([$this->parent->{$this->parentKey}], static fn ($key) => $key !== null));

        if ($parentKeys === []) {
            return $this->related->newCollection();
        }

        // Do not use newPivotQuery(): it always constrains to $this->parent->id,
        // which is empty on the relation instance used for eager loading.
        $pivots = $this->newPivotStatement()
            ->whereIn($this->foreignPivotKey, $parentKeys)
            ->get();

        if ($pivots->isEmpty()) {
            return $this->related->newCollection();
        }

        $relatedIds = $pivots->map(function ($pivot) {
            return $this->pivotValue($pivot, $this->relatedPivotKey);
        })->unique()->values()->all();

        /** @var EloquentCollection<int, TRelatedModel> $related */
        $related = $this->related->newQueryWithoutRelationships()
            ->whereIn($this->relatedKey, $relatedIds)
            ->get($columns === ['*'] ? ['*'] : $columns);

        $relatedById = $related->keyBy(fn ($model) => $this->getDictionaryKey($model->getAttribute($this->relatedKey)));

        $results = $this->related->newCollection();

        foreach ($pivots as $pivot) {
            $relatedKey = $this->getDictionaryKey($this->pivotValue($pivot, $this->relatedPivotKey));
            $model = $relatedById->get($relatedKey);

            if ($model === null) {
                continue;
            }

            $instance = clone $model;
            $instance->setRelation(
                $this->accessor,
                $this->newExistingPivot($this->pivotAttributes($pivot))
            );
            $results->push($instance);
        }

        if ($results->isNotEmpty()) {
            $results = $this->query->eagerLoadRelations($results->all());
            $results = $this->related->newCollection($results);
        }

        return $this->query->applyAfterQueryCallbacks($results);
    }

    /**
     * @return array<string, mixed>
     */
    private function pivotAttributes(mixed $pivot): array
    {
        $attributes = $pivot instanceof BaseCollection
            ? $pivot->all()
            : (array) $pivot;

        unset($attributes['elementId']);

        return $attributes;
    }

    private function pivotValue(mixed $pivot, string $key): mixed
    {
        if (is_array($pivot)) {
            return $pivot[$key] ?? null;
        }

        return $pivot->{$key} ?? null;
    }
}
