<?php

namespace Neo4j\Neo4jLaravel\Logging;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Neo4j\Neo4jLaravel\Neo4jConnection;

/**
 * Flushes each Neo4j connection's in-memory query log (see {@see Neo4jConnection::logQuery})
 * to Laravel's logging system once per request / console lifecycle, then clears the buffer.
 *
 * @api
 */
final class Neo4jQueryLogAfterRequest
{
    public static function flush(Application $app): void
    {
        if (! $app->bound('db') || ! $app->bound('config')) {
            return;
        }

        $config = $app->make('config');
        $channel = trim((string) $config->get('neo4j-laravel.query_log_channel', ''));
        $allowProductionChannel = (bool) $config->get('neo4j-laravel.query_log_allow_production', false);

        $channels = $config->get('logging.channels', []);
        $channelConfigured = is_array($channels) && $channel !== '' && array_key_exists($channel, $channels);

        $writeToLaravel = true;
        if ($channel !== '' && $app->environment('production') && ! $allowProductionChannel) {
            $writeToLaravel = false;
        }

        $db = $app->make('db');

        foreach ($config->get('database.connections', []) as $name => $connectionConfig) {
            if (($connectionConfig['driver'] ?? '') !== 'neo4j') {
                continue;
            }

            try {
                $connection = $db->connection($name);
            } catch (\Throwable) {
                continue;
            }

            if (! $connection instanceof Neo4jConnection) {
                continue;
            }

            $entries = $connection->getQueryLog();
            if ($entries === []) {
                continue;
            }

            if ($writeToLaravel) {
                if ($channel !== '' && $channelConfigured) {
                    $log = Log::channel($channel);
                    foreach ($entries as $entry) {
                        $log->debug('Neo4j query', $entry);
                    }
                } else {
                    foreach ($entries as $entry) {
                        Log::debug('Neo4j query', $entry);
                    }
                }
            }

            $connection->flushQueryLog();
        }
    }
}
