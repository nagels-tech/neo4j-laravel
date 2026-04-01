<?php

return [
    'options' => [
        'neo4j' => [
            'enabled' => env('DEBUGBAR_NEO4J_ENABLED', null),
            'timeline' => env('DEBUGBAR_NEO4J_TIMELINE', true),
            'explain' => env('DEBUGBAR_NEO4J_EXPLAIN', true),
        ],
    ],
];
