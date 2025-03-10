<?php

namespace Neo4jPhp\Neo4jLaravel\Debug;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Illuminate\Support\Str;

class Neo4jQueryCollector extends DataCollector implements Renderable
{
    protected array $queries = [];
    protected bool $timeEnabled = false;
    protected bool $explainEnabled = false;

    public function addQuery(string $query, array $parameters = [], ?float $duration = null, ?string $connection = null): void
    {
        $this->queries[] = [
            'query' => $query,
            'parameters' => $parameters,
            'duration' => $duration,
            'duration_str' => $duration ? sprintf('%.2f ms', $duration) : null,
            'connection' => $connection,
            'stack' => $this->timeEnabled ? $this->getStackTrace() : null,
        ];
    }

    public function collect(): array
    {
        $totalTime = 0;
        foreach ($this->queries as $query) {
            if (isset($query['duration'])) {
                $totalTime += $query['duration'];
            }
        }

        return [
            'nb_queries' => count($this->queries),
            'nb_failed_queries' => 0,
            'accumulated_duration' => $totalTime,
            'accumulated_duration_str' => sprintf('%.2f ms', $totalTime),
            'queries' => $this->queries,
        ];
    }

    public function getName(): string
    {
        return 'neo4j';
    }

    public function getWidgets(): array
    {
        return [
            'neo4j' => [
                'icon' => 'database',
                'widget' => 'PhpDebugBar.Widgets.SQLQueriesWidget',
                'map' => 'neo4j',
                'default' => '[]',
            ],
            'neo4j:badge' => [
                'map' => 'neo4j.nb_queries',
                'default' => 0,
            ],
        ];
    }

    public function reset(): void
    {
        $this->queries = [];
    }

    protected function getStackTrace(): array
    {
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // Remove internal framework/debugbar classes
        $stack = array_filter($stack, function ($trace) {
            return !Str::startsWith($trace['class'] ?? '', [
                'DebugBar\\',
                'Neo4jPhp\\Neo4jLaravel\\Debug\\Neo4jQueryCollector',
            ]);
        });

        // Reset array keys
        $stack = array_values($stack);

        return array_map(function ($trace) {
            return [
                'file' => $trace['file'] ?? '[internal]',
                'line' => $trace['line'] ?? '?',
                'class' => $trace['class'] ?? '',
                'function' => $trace['function'] ?? '',
            ];
        }, $stack);
    }

    public function setTimeEnabled(bool $enabled = true): void
    {
        $this->timeEnabled = $enabled;
    }

    public function setExplainEnabled(bool $enabled = true): void
    {
        $this->explainEnabled = $enabled;
    }
}
