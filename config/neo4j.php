<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Neo4j Connection Name
    |--------------------------------------------------------------------------
    */
    'default' => env('NEO4J_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Neo4j Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [
        'default' => [
            'url' => env('NEO4J_URL', 'bolt://localhost:7687'),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', ''),
            'database' => env('NEO4J_DATABASE', 'neo4j'),
            'auth_scheme' => env('NEO4J_AUTH_SCHEME', 'basic'),
            'ssl' => [
                'mode' => env('NEO4J_SSL_MODE', 'from_url'),
                'verify_peer' => env('NEO4J_SSL_VERIFY_PEER', true),
            ],
            'connection' => [
                'timeout' => env('NEO4J_CONNECTION_TIMEOUT', 30),
                'max_pool_size' => env('NEO4J_MAX_POOL_SIZE', 100),
            ],
            'transaction' => [
                'timeout' => env('NEO4J_TRANSACTION_TIMEOUT', 30),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'level' => env('NEO4J_LOG_LEVEL', null),
    ],
];
