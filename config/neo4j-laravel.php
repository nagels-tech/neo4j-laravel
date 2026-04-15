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
];
