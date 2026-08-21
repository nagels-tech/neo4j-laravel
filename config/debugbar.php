<?php

return [
    'options' => [
        'neo4j' => [
            /*
            | Enable Neo4j Cypher query collection in Laravel Debugbar.
            | When null, integration is enabled whenever Debugbar is installed.
            | Set to false to keep Debugbar but skip Neo4j query capture.
            */
            'enabled' => env('DEBUGBAR_NEO4J_ENABLED', null),
            'timeline' => env('DEBUGBAR_NEO4J_TIMELINE', true),
            'explain' => env('DEBUGBAR_NEO4J_EXPLAIN', true),
        ],
    ],
];
