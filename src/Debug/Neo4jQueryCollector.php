<?php

namespace Neo4jPhp\Neo4jLaravel\Debug;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Illuminate\Support\Str;

/**
 * @api
 */
class Neo4jQueryCollector extends DataCollector implements Renderable
{
    protected array $queries = [];
    protected bool $timeEnabled = false;

    protected bool $explainEnabled = false;

    public function addQuery(string $query, array $parameters = [], ?float $duration = null, ?string $connection = null): void
    {
        $this->queries[] = [
            'sql' => $query,
            'params' => $parameters,
            'duration' => $duration,
            'duration_str' => $duration !== null ? sprintf('%.2f ms', $duration) : null,
            'connection' => $connection,
            'is_success' => true,
            'stmt_id' => count($this->queries),
            'stack' => $this->timeEnabled ? $this->getStackTrace() : null,
        ];
    }

    #[\Override]
    public function collect(): array
    {
        $totalTime = 0;
        foreach ($this->queries as $query) {
            $totalTime += $query['duration'] ?? 0;
        }

        return [
            'nb_statements' => count($this->queries),
            'nb_failed_statements' => 0,
            'accumulated_duration' => $totalTime,
            'accumulated_duration_str' => $this->formatDuration($totalTime),
            'statements' => $this->queries,
        ];
    }

    #[\Override]
    public function getName(): string
    {
        return 'neo4j';
    }

    #[\Override]
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
                'map' => 'neo4j.nb_statements',
                'default' => 0,
            ],
        ];
    }

    /**
     * @api
     */
    public function reset(): void
    {
        $this->queries = [];
    }

    protected function getStackTrace(): array
    {
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // Remove internal framework/debugbar classes
        $stack = array_filter($stack, function ($trace) {
            return ! Str::startsWith($trace['class'] ?? '', [
                'DebugBar\\',
                'Neo4jPhp\\Neo4jLaravel\\Debug\\Neo4jQueryCollector',
                'Neo4jPhp\\Neo4jLaravel\\Neo4jConnection',
                'Illuminate\\',
                'Barryvdh\\',
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

    /**
     * @psalm-suppress MissingParamType Suppressed because parent class lacks type hints but adding them breaks inheritance
     */
    #[\Override]
    public function formatDuration($seconds): string
    {
        return sprintf('%.2f ms', $seconds);
    }
}
