<?php

namespace Neo4jPhp\Neo4jLaravel\Debug;

use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Support\ServiceProvider;

class Neo4jDebugServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!$this->app->bound('debugbar')) {
            return;
        }

        // Register the collector
        $this->app->singleton(Neo4jQueryCollector::class, function () {
            $collector = new Neo4jQueryCollector();
            $collector->setTimeEnabled(config('debugbar.options.neo4j.timeline', true));
            $collector->setExplainEnabled(config('debugbar.options.neo4j.explain', true));
            return $collector;
        });

        // Add the collector to Laravel Debugbar
        /** @var \Barryvdh\Debugbar\LaravelDebugbar $debugbar */
        $debugbar = $this->app->make('debugbar');
        $debugbar->addCollector($this->app->make(Neo4jQueryCollector::class));
    }

    public function boot(): void
    {
        if (!$this->app->bound('debugbar')) {
            return;
        }

        // Add Neo4j configuration to debugbar config
        $this->mergeConfigFrom(__DIR__ . '/../../config/debugbar.php', 'debugbar');
    }
}
