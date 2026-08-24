<?php

namespace Neo4j\Neo4jLaravel\Debug;

use Illuminate\Contracts\Foundation\Application;

/**
 * Detects whether Laravel Debugbar is installed and whether Neo4j should
 * register its Debugbar integration for the current application.
 *
 * Supports barryvdh/laravel-debugbar and fruitcake/laravel-debugbar (v4+),
 * which replaces Barryvdh and binds the same `debugbar` container alias.
 *
 * @api
 */
final class DebugbarAvailability
{
    /** @var list<string> */
    private const PACKAGE_CLASSES = [
        'Barryvdh\\Debugbar\\LaravelDebugbar',
        'Barryvdh\\Debugbar\\ServiceProvider',
        'Fruitcake\\LaravelDebugbar\\LaravelDebugbar',
        'Fruitcake\\LaravelDebugbar\\ServiceProvider',
    ];

    /**
     * Whether a Laravel Debugbar package appears to be installed.
     */
    public static function isPackagePresent(): bool
    {
        foreach (self::PACKAGE_CLASSES as $class) {
            if (class_exists($class)) {
                return true;
            }
        }

        return false;
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
     * Package presence and the debugbar binding are checked by callers.
     * Disabled when Debugbar itself is disabled, or when either
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

        // Host app disabled Laravel Debugbar entirely.
        if ($config->get('debugbar.enabled') === false) {
            return false;
        }

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
