<?php

return [
    'collectors' => [
        /*
        | Enable the dedicated Neo4j "Cypher Queries" Debugbar tab.
        | Set false to hide/disable Neo4j collection while keeping Debugbar.
        */
        'neo4j' => env('DEBUGBAR_COLLECTORS_NEO4J', true),
    ],

    'options' => [
        'neo4j' => [
            /*
            | Enable Neo4j Cypher query collection in Laravel Debugbar.
            | When null, integration follows debugbar.collectors.neo4j
            | (enabled whenever Debugbar is installed unless collectors.neo4j is false).
            | Set to false to keep Debugbar but skip Neo4j query capture.
            */
            'enabled' => env('DEBUGBAR_NEO4J_ENABLED', null),
            'timeline' => env('DEBUGBAR_NEO4J_TIMELINE', true),
            'explain' => env('DEBUGBAR_NEO4J_EXPLAIN', true),
            /*
            | Highlight queries at or above this duration (milliseconds).
            | Null disables slow-query highlighting.
            */
            'slow_threshold' => env('DEBUGBAR_NEO4J_SLOW_THRESHOLD', null),
        ],
    ],
];
