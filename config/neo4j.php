<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for Neo4j sessions. Of course
    | you may use many connections at once using the Neo4j library.
    |
    */
    'default' => env('NEO4J_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | You can configure multiple connections to use different Neo4j databases
    | or even connect to different Neo4j servers.
    |
    */
    'connections' => [
        'default' => [
            'driver' => 'neo4j',
            'name' => env('NEO4J_CONNECTION', 'default'),
            'url' => env('NEO4J_URL'),
            'host' => env('NEO4J_HOST', 'localhost'),
            'port' => env('NEO4J_PORT', 7687),
            'database' => env('NEO4J_DATABASE', 'neo4j'),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', ''),
            'scheme' => env('NEO4J_AUTH_SCHEME', 'basic'),
            'ssl' => [
                'mode' => env('NEO4J_SSL_MODE', 'from_url'),
                'verify_peer' => env('NEO4J_SSL_VERIFY_PEER', true),
            ],
            'pool' => [
                'max_size' => env('NEO4J_MAX_POOL_SIZE', 100),
            ],
            'timeout' => [
                'connection' => env('NEO4J_CONNECTION_TIMEOUT', 30),
                'transaction' => env('NEO4J_TRANSACTION_TIMEOUT', 30),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log settings for your Neo4j connection.
    | If you want to use Laravel's logging system with Neo4j, you can
    | specify the log level here.
    |
    */
    'logging' => [
        'level' => env('NEO4J_LOG_LEVEL'),
    ],
];
