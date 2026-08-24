<?php

namespace Neo4j\Neo4jLaravel\Debug;

use Illuminate\Support\ServiceProvider;

/**
 * Registers the Neo4j Cypher Queries collector with Laravel Debugbar.
 *
 * Safe to load only when Laravel Debugbar (Barryvdh or Fruitcake) is installed
 * and bound; Neo4jServiceProvider gates registration on package presence + binding.
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

        // Laravel's mergeConfigFrom uses array_merge and replaces nested
        // collectors/options wholesale. Recursively merge so neo4j keys land
        // under an already-published debugbar.php without wiping host config.
        $this->mergeNeo4jDebugbarConfig();

        if (! DebugbarAvailability::shouldRegister($this->app)) {
            return;
        }

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

        if (! DebugbarAvailability::shouldRegister($this->app)) {
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

        if (! $this->app->bound(Neo4jQueryCollector::class)) {
            return;
        }

        /** @var object{hasCollector: callable, addCollector: callable} $debugbar */
        $debugbar = $this->app->make('debugbar');
        $collector = $this->app->make(Neo4jQueryCollector::class);

        if ($debugbar->hasCollector($collector->getName())) {
            return;
        }

        $debugbar->addCollector($collector);
    }

    /**
     * Merge package debugbar defaults under existing host debugbar config.
     *
     * Host values win over package defaults (array_replace_recursive order).
     */
    protected function mergeNeo4jDebugbarConfig(): void
    {
        if ($this->app instanceof \Illuminate\Contracts\Foundation\CachesConfiguration
            && $this->app->configurationIsCached()) {
            return;
        }

        /** @var array<string, mixed> $package */
        $package = require __DIR__ . '/../../config/debugbar.php';
        /** @var array<string, mixed> $existing */
        $existing = $this->app->make('config')->get('debugbar', []);

        $this->app->make('config')->set(
            'debugbar',
            array_replace_recursive($package, $existing)
        );
    }
}
