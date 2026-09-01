<?php

namespace Neo4j\Neo4jLaravel\Decorators;

/**
 * @internal
 */
final class ParameterBag
{
    /**
     * @param iterable<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public static function toArray(iterable $parameters): array
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
