<?php

namespace Neo4j\Neo4jLaravel\Debug;

use Illuminate\Support\ServiceProvider;

/**
 * Registers the Neo4j query collector with Laravel Debugbar when available.
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

        if ($this->app->make('config')->get('debugbar.options.neo4j.enabled') === false) {
            return;
        }

        $this->app->singleton(Neo4jQueryCollector::class, function () {
            $collector = new Neo4jQueryCollector();
            $collector->setTimeEnabled((bool) config('debugbar.options.neo4j.timeline', true));
            $collector->setExplainEnabled((bool) config('debugbar.options.neo4j.explain', true));

            return $collector;
        });

        /** @var \Barryvdh\Debugbar\LaravelDebugbar $debugbar */
        $debugbar = $this->app->make('debugbar');
        $debugbar->addCollector($this->app->make(Neo4jQueryCollector::class));
    }
}
