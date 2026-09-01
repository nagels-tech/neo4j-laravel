<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Types\CypherList;

/**
 * Real-looking Neo4j examples for Debugbar screenshots:
 * Client / Driver / Session / Transaction each run meaningful Cypher.
 */
class DemoCaptureController extends Controller
{
    public function client(ClientInterface $client): View
    {
        $result = $client->run(<<<'CYPHER'
            MATCH (m:Movie)
            OPTIONAL MATCH (m)<-[:ACTED_IN]-(a:Person)
            RETURN m.title AS title,
                   m.released AS released,
                   count(DISTINCT a) AS actorCount
            ORDER BY m.released DESC
            LIMIT 10
        CYPHER);

        $rows = collect($result)->map(fn ($row) => [
            'title' => $row->get('title'),
            'released' => $row->get('released'),
            'actorCount' => $row->get('actorCount'),
        ])->all();

        return view('demo.capture', [
            'api' => 'Client',
            'description' => 'Injected ClientInterface — lists recent movies with actor counts.',
            'cypherHint' => 'MATCH (m:Movie) … RETURN title, released, actorCount',
            'rows' => $rows,
        ]);
    }

    public function driver(DriverInterface $driver): View
    {
        $session = $driver->createSession();

        $result = $session->run(<<<'CYPHER'
            MATCH (a:Person)-[r:ACTED_IN]->(m:Movie)
            RETURN a.name AS actor,
                   m.title AS movie,
                   coalesce(r.roles, []) AS roles
            ORDER BY a.name, m.title
            LIMIT 15
        CYPHER);

        $rows = collect($result)->map(fn ($row) => [
            'actor' => $row->get('actor'),
            'movie' => $row->get('movie'),
            'roles' => $this->listToString($row->get('roles')),
        ])->all();

        return view('demo.capture', [
            'api' => 'Driver',
            'description' => 'Injected DriverInterface → createSession() — lists actors and films.',
            'cypherHint' => 'MATCH (a:Person)-[r:ACTED_IN]->(m:Movie) …',
            'rows' => $rows,
        ]);
    }

    public function session(SessionInterface $session): View
    {
        $result = $session->run(<<<'CYPHER'
            MATCH (m:Movie)
            OPTIONAL MATCH (m)<-[:ACTED_IN]-(a:Person)
            WITH m, collect(DISTINCT a.name) AS actors
            RETURN m.title AS title,
                   m.tagline AS tagline,
                   actors
            ORDER BY m.title
            LIMIT 10
        CYPHER);

        $rows = collect($result)->map(fn ($row) => [
            'title' => $row->get('title'),
            'tagline' => $row->get('tagline'),
            'actors' => $this->listToString($row->get('actors')),
        ])->all();

        return view('demo.capture', [
            'api' => 'Session',
            'description' => 'Injected SessionInterface — movies with cast names (same style as /movies).',
            'cypherHint' => 'MATCH (m:Movie) OPTIONAL MATCH … RETURN title, tagline, actors',
            'rows' => $rows,
        ]);
    }

    public function transaction(ClientInterface $client): View
    {
        $suffix = now()->format('His');
        $title = "Debugbar Demo {$suffix}";
        $actor = "Demo Actor {$suffix}";

        $tx = $client->beginTransaction();

        $tx->run(<<<'CYPHER'
            MERGE (m:Movie {title: $title})
            ON CREATE SET m.released = $released,
                          m.tagline = $tagline,
                          m.created_at = datetime(),
                          m.demo = true
            RETURN m.title AS title
        CYPHER, [
            'title' => $title,
            'released' => (int) now()->year,
            'tagline' => 'Created via Client beginTransaction()',
        ]);

        $tx->run(<<<'CYPHER'
            MATCH (m:Movie {title: $title})
            MERGE (a:Person {name: $actorName})
            ON CREATE SET a.demo = true
            MERGE (a)-[r:ACTED_IN]->(m)
            SET r.roles = $roles
            RETURN a.name AS actor, m.title AS movie, r.roles AS roles
        CYPHER, [
            'title' => $title,
            'actorName' => $actor,
            'roles' => ['Lead'],
        ]);

        $summary = $tx->run(<<<'CYPHER'
            MATCH (a:Person {name: $actorName})-[r:ACTED_IN]->(m:Movie {title: $title})
            RETURN m.title AS title,
                   a.name AS actor,
                   r.roles AS roles,
                   m.tagline AS tagline
        CYPHER, [
            'title' => $title,
            'actorName' => $actor,
        ]);

        $tx->commit();

        $rows = collect($summary)->map(fn ($row) => [
            'title' => $row->get('title'),
            'actor' => $row->get('actor'),
            'roles' => $this->listToString($row->get('roles')),
            'tagline' => $row->get('tagline'),
        ])->all();

        return view('demo.capture', [
            'api' => 'Transaction',
            'description' => 'Client beginTransaction() — creates a demo movie + actor + ACTED_IN, then reads them back (3 Cypher statements).',
            'cypherHint' => 'MERGE Movie → MERGE Person/ACTED_IN → MATCH result (all inside one tx)',
            'rows' => $rows,
        ]);
    }

    private function listToString(mixed $value): string
    {
        if ($value instanceof CypherList) {
            $value = $value->toArray();
        }

        if (! is_array($value)) {
            return $value === null ? '' : (string) $value;
        }

        return implode(', ', array_filter(array_map(
            static fn ($item) => $item === null ? null : (string) $item,
            $value
        )));
    }
}
