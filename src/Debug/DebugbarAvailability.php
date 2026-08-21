<?php

namespace Neo4j\Neo4jLaravel\Debug;

use Illuminate\Contracts\Foundation\Application;

/**
 * Detects whether Laravel Debugbar is installed and whether Neo4j should
 * register its Debugbar integration for the current application.
 *
 * @api
 */
final class DebugbarAvailability
{
    private const SERVICE_PROVIDER = 'Barryvdh\\Debugbar\\ServiceProvider';

    private const LARAVEL_DEBUGBAR = 'Barryvdh\\Debugbar\\LaravelDebugbar';

    /**
     * Whether the barryvdh/laravel-debugbar package appears to be installed.
     */
    public static function isPackagePresent(): bool
    {
        return class_exists(self::LARAVEL_DEBUGBAR)
            || class_exists(self::SERVICE_PROVIDER);
    }

    /**
     * Whether the Debugbar service has been registered on the container.
     */
    public static function isBound(Application $app): bool
    {
        return $app->bound('debugbar');
    }

    /**
     * Whether Neo4j should register its Cypher Queries Debugbar integration.
     *
     * Requires the Debugbar package. Disabled when either
     * debugbar.collectors.neo4j or debugbar.options.neo4j.enabled is false.
     */
    public static function shouldRegister(Application $app): bool
    {
        if (! self::isPackagePresent()) {
            return false;
        }

        if (! $app->bound('config')) {
            return true;
        }

        $config = $app->make('config');

        if ($config->get('debugbar.collectors.neo4j', true) === false) {
            return false;
        }

        return $config->get('debugbar.options.neo4j.enabled') !== false;
    }

    /**
     * Whether Neo4j should capture queries into Debugbar for this request.
     *
     * Requires the package, a bound debugbar instance, and the Neo4j collector.
     */
    public static function shouldCapture(Application $app): bool
    {
        return self::isPackagePresent()
            && self::isBound($app)
            && $app->bound(Neo4jQueryCollector::class)
            && self::shouldRegister($app);
    }
}
