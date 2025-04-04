<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;

class MovieController extends Controller
{
    public function index(SessionInterface $session): JsonResponse
    {
        $result = $session->run('
            MATCH (m:Movie)
            OPTIONAL MATCH (m)<-[r:ACTED_IN]-(a:Person)
            RETURN m, collect(DISTINCT {actor: a, role: r.roles}) as actors
        ');

        return response()->json($result->toArray());
    }

    public function store(Request $request, SessionInterface $session): JsonResponse
    {
        $request->validate([
            'title' => 'required|string',
            'released' => 'required|integer',
            'tagline' => 'required|string',
        ]);

        try {
            $result = $session->run(
                'CREATE (m:Movie {
                    title: $title,
                    released: $released,
                    tagline: $tagline,
                    created_at: datetime()
                }) RETURN m',
                $request->only(['title', 'released', 'tagline'])
            );

            return response()->json($result->first());
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'type' => get_class($e)
            ], 500);
        }
    }

    public function addActor(Request $request, TransactionInterface $transaction): JsonResponse
    {
        $request->validate([
            'movie_title' => 'required|string',
            'actor_name' => 'required|string',
            'roles' => 'required|array',
            'roles.*' => 'string',
        ]);

        try {
            // First ensure both movie and actor exist, create actor if not exists
            $result = $transaction->run(
                '
                MATCH (m:Movie {title: $movieTitle})
                MERGE (a:Person {name: $actorName})
                MERGE (a)-[r:ACTED_IN]->(m)
                SET r.roles = $roles
                RETURN m, a, r',
                [
                    'movieTitle' => $request->input('movie_title'),
                    'actorName' => $request->input('actor_name'),
                    'roles' => $request->input('roles'),
                ]
            );

            if ($result->isEmpty()) {
                if ($transaction instanceof \Laudis\Neo4j\Contracts\UnmanagedTransactionInterface) {
                    $transaction->rollback();
                }
                return response()->json(['message' => 'Movie not found'], 404);
            }

            $data = $result->first();

            // Commit the transaction
            if ($transaction instanceof \Laudis\Neo4j\Contracts\UnmanagedTransactionInterface) {
                $transaction->commit();
            }

            return response()->json($data);
        } catch (\Exception $e) {
            // Rollback if possible
            if ($transaction instanceof \Laudis\Neo4j\Contracts\UnmanagedTransactionInterface) {
                $transaction->rollback();
            }

            return response()->json([
                'error' => $e->getMessage(),
                'type' => get_class($e)
            ], 500);
        }
    }

    public function show(string $title, SessionInterface $session): JsonResponse
    {
        $result = $session->run(
            '
            MATCH (m:Movie {title: $title})
            OPTIONAL MATCH (m)<-[r:ACTED_IN]-(a:Person)
            RETURN m, collect(DISTINCT {actor: a, role: r.roles}) as actors',
            ['title' => $title]
        );

        if ($result->isEmpty()) {
            return response()->json(['message' => 'Movie not found'], 404);
        }

        return response()->json($result->first());
    }

    public function findSimilar(string $title, SessionInterface $session): JsonResponse
    {
        // Find movies through common actors
        $result = $session->run(
            '
            MATCH (m:Movie {title: $title})<-[:ACTED_IN]-(a:Person)-[:ACTED_IN]->(other:Movie)
            WHERE m <> other
            WITH other, count(distinct a) as commonActors
            RETURN other, commonActors
            ORDER BY commonActors DESC
            LIMIT 5',
            ['title' => $title]
        );

        return response()->json($result->toArray());
    }

    public function destroy(string $title, SessionInterface $session): JsonResponse
    {
        $result = $session->run(
            '
            MATCH (m:Movie {title: $title})
            OPTIONAL MATCH (m)<-[r:ACTED_IN]-()
            DELETE r, m
            RETURN count(m) as deleted',
            ['title' => $title]
        );

        if ($result->first()->get('deleted') === 0) {
            return response()->json(['message' => 'Movie not found'], 404);
        }

        return response()->json(['message' => 'Movie and its relationships deleted successfully']);
    }
}
