<?php

namespace Neo4j\Neo4jLaravel;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use Laudis\Neo4j\Contracts\HasPropertiesInterface;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\DateTimeZoneId;

/**
 * Converts Neo4j records into Laravel Query Builder row arrays.
 */
final class Neo4jProcessor extends Processor
{
    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    #[\Override]
    public function processSelect(Builder $query, $results): array
    {
        $rows = [];

        foreach ($results as $row) {
            $processed = [];

            foreach ($row as $key => $value) {
                if ($value instanceof HasPropertiesInterface) {
                    foreach ($value->getProperties() as $property => $propertyValue) {
                        $processed[$property] = $this->normalizeValue($propertyValue);
                    }
                } else {
                    $processed[$key] = $this->normalizeValue($value);
                }
            }

            $rows[] = $processed;
        }

        return $rows;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeZoneId || $value instanceof DateTime) {
            return $value->toDateTime();
        }

        return $value;
    }
}
