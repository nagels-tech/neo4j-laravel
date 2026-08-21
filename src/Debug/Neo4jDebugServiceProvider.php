<?php

namespace Neo4j\Neo4jLaravel\Debug;

use Illuminate\Support\ServiceProvider;

/**
 * Registers the Neo4j Cypher Queries collector with Laravel Debugbar.
 *
 * @api
 */
class Neo4jDebugServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        if (! DebugbarAvailability::isBound($this->app)) {
            return;
        }

        $this->mergeConfigFrom(__DIR__ . '/../../config/debugbar.php', 'debugbar');

        $this->app->singleton(Neo4jQueryCollector::class, function () {
            $collector = new Neo4jQueryCollector();
            $collector->setTimeEnabled((bool) config('debugbar.options.neo4j.timeline', true));
            $collector->setExplainEnabled((bool) config('debugbar.options.neo4j.explain', true));

            $slowThreshold = config('debugbar.options.neo4j.slow_threshold');
            if ($slowThreshold !== null && $slowThreshold !== '') {
                $collector->setSlowThreshold((float) $slowThreshold);
            }

            return $collector;
        });
    }

    public function boot(): void
    {
        if (! DebugbarAvailability::isBound($this->app)) {
            return;
        }

        // Register after Debugbar has booted its own collectors so the
        // dedicated "Cypher Queries" tab is present when the bar renders.
        $this->app->booted(function (): void {
            $this->registerCypherCollector();
        });
    }

    /**
     * Attach Neo4jQueryCollector to Debugbar as the Cypher Queries section.
     */
    protected function registerCypherCollector(): void
    {
        if (! DebugbarAvailability::shouldRegister($this->app)) {
            return;
        }

        if (! DebugbarAvailability::isBound($this->app)) {
            return;
        }

        /** @var \Barryvdh\Debugbar\LaravelDebugbar $debugbar */
        $debugbar = $this->app->make('debugbar');
        $collector = $this->app->make(Neo4jQueryCollector::class);

        if ($debugbar->hasCollector($collector->getName())) {
            return;
        }

        $debugbar->addCollector($collector);
    }
}
