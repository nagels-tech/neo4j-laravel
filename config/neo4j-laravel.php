<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Neo4j query log channel (optional)
    |--------------------------------------------------------------------------
    |
    | When set to a non-empty string that matches a key in config/logging.php
    | "channels", each in-memory query log entry is written with
    | Log::channel(<name>)->debug(...) after the HTTP request or console
    | command finishes. When empty, entries use the application default logger
    | (Log::debug without a named channel).
    |
    */
    'query_log_channel' => env('NEO4J_QUERY_CHANNEL', ''),

    /*
    |--------------------------------------------------------------------------
    | Allow query log flush to a dedicated channel in production
    |--------------------------------------------------------------------------
    |
    | When NEO4J_QUERY_CHANNEL is set and APP_ENV is production, the package
    | skips writing query log lines to Laravel (but still clears the in-memory
    | log) unless this is true, to avoid heavy logging in production by
    | accident.
    |
    */
    'query_log_allow_production' => env('NEO4J_QUERY_LOG_ALLOW_PRODUCTION', false),
];
