<?php

namespace Neo4j\Neo4jLaravel\Debug;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Illuminate\Support\Str;

/**
 * Laravel Debugbar DataCollector for Neo4j Cypher queries.
 *
 * Stores captured executions and exposes them as a "Cypher Queries" panel
 * (reusing Laravel Debugbar's LaravelQueriesWidget for list/badge rendering).
 *
 * @api
 */
class Neo4jQueryCollector extends DataCollector implements Renderable, AssetProvider
{
    /** @var list<array<string, mixed>> */
    protected array $queries = [];

    protected bool $timeEnabled = false;

    protected bool $explainEnabled = false;

    /** Slow-query threshold in milliseconds; null disables highlighting. */
    protected ?float $slowThresholdMs = null;

    /**
     * Record a Cypher execution for Debugbar.
     *
     * @param array<string, mixed> $parameters
     */
    public function addQuery(
        string $query,
        array $parameters = [],
        ?float $duration = null,
        ?string $connection = null,
        bool $isSuccess = true,
        ?string $errorMessage = null,
        ?string $database = null
    ): void {
        $isSlow = $this->slowThresholdMs !== null
            && $duration !== null
            && $duration >= $this->slowThresholdMs;

        // Duplicate-detection in LaravelQueriesWidget only counts type=query.
        $connectionLabel = $connection ?? 'neo4j';
        $hints = [];
        if ($database !== null && $database !== '') {
            // Widget has no dedicated database field; Hints is rendered by LaravelQueriesWidget.
            $hints['Database'] = $database;
        }

        $this->queries[] = [
            // sql keeps compatibility with PhpDebugBar / Laravel query widgets
            'sql' => $query,
            'cypher' => $query,
            'type' => 'query',
            'params' => (object) $parameters,
            'bindings' => $parameters,
            'duration' => $duration,
            'duration_str' => $duration !== null ? $this->formatDuration($duration) : null,
            'connection' => $connectionLabel,
            'database' => $database,
            'status' => $isSuccess ? 'ok' : 'error',
            'is_success' => $isSuccess,
            'error_code' => $isSuccess ? null : '',
            'error_message' => $errorMessage,
            'hints' => $hints,
            'slow' => $isSlow,
            'stmt_id' => count($this->queries),
            'stack' => $this->timeEnabled ? $this->getStackTrace() : null,
        ];
    }

    /**
     * @return array{
     *     count: int,
     *     nb_statements: int,
     *     nb_failed_statements: int,
     *     nb_slow_statements: int,
     *     accumulated_duration: float|int,
     *     accumulated_duration_str: string,
     *     statements: list<array<string, mixed>>,
     *     tooltip: array<string, string|int>
     * }
     */
    #[\Override]
    public function collect(): array
    {
        $totalTime = 0.0;
        $failed = 0;
        $slow = 0;

        foreach ($this->queries as $query) {
            $totalTime += (float) ($query['duration'] ?? 0);
            if (($query['is_success'] ?? true) === false) {
                ++$failed;
            }
            if (($query['slow'] ?? false) === true) {
                ++$slow;
            }
        }

        $count = count($this->queries);

        return [
            'count' => $count,
            'nb_statements' => $count,
            'nb_failed_statements' => $failed,
            'nb_slow_statements' => $slow,
            'accumulated_duration' => $totalTime,
            'accumulated_duration_str' => $this->formatDuration($totalTime),
            'statements' => $this->queries,
            'tooltip' => [
                'Queries' => $count,
                'Failed' => $failed,
                'Slow' => $slow,
                'Total time' => $this->formatDuration($totalTime),
            ],
        ];
    }

    #[\Override]
    public function getName(): string
    {
        return 'neo4j';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    #[\Override]
    public function getWidgets(): array
    {
        return [
            'neo4j' => [
                'icon' => 'database',
                // Prefer Laravel Debugbar's widget (always in its asset bundle) so the
                // Cypher tab renders even when collector-provided sqlqueries JS is late.
                'widget' => 'PhpDebugBar.Widgets.LaravelQueriesWidget',
                'map' => 'neo4j',
                'default' => '[]',
                'title' => 'Cypher Queries',
                'order' => 55,
            ],
            'neo4j:badge' => [
                'map' => 'neo4j.nb_statements',
                'default' => 0,
            ],
            'neo4j:tooltip' => [
                'map' => 'neo4j.tooltip',
                'default' => '{}',
            ],
        ];
    }

    /**
     * SQLQueries CSS still styles LaravelQueriesWidget (shared class names).
     * JS is optional backup; Laravel Debugbar already ships LaravelQueriesWidget.
     *
     * @return array{css: string, js: string}
     */
    #[\Override]
    public function getAssets(): array
    {
        return [
            'css' => 'widgets/sqlqueries/widget.css',
            'js' => 'widgets/sqlqueries/widget.js',
        ];
    }

    /**
     * @api
     */
    public function reset(): void
    {
        $this->queries = [];
    }

    /**
     * @api
     */
    public function getQueryCount(): int
    {
        return count($this->queries);
    }

    /**
     * Total execution time in milliseconds.
     *
     * @api
     */
    public function getTotalDuration(): float
    {
        $total = 0.0;
        foreach ($this->queries as $query) {
            $total += (float) ($query['duration'] ?? 0);
        }

        return $total;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @api
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * @return list<array{file: string, line: int|string, class: string, function: string}>
     */
    protected function getStackTrace(): array
    {
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $stack = array_filter($stack, function ($trace) {
            return ! Str::startsWith($trace['class'] ?? '', [
                'DebugBar\\',
                'Neo4j\\Neo4jLaravel\\Debug\\',
                'Neo4j\\Neo4jLaravel\\Neo4jConnection',
                'Illuminate\\',
                'Barryvdh\\',
                'Fruitcake\\',
            ]);
        });

        $stack = array_values($stack);

        return array_map(static function ($trace) {
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
     * @api
     */
    public function setSlowThreshold(?float $milliseconds): void
    {
        $this->slowThresholdMs = $milliseconds;
    }

    /**
     * Format duration for the Queries widget (values are milliseconds).
     *
     * @psalm-suppress MissingParamType Suppressed because parent class lacks type hints but adding them breaks inheritance
     */
    public function formatDuration($seconds): string
    {
        return sprintf('%.2f ms', $seconds);
    }
}
