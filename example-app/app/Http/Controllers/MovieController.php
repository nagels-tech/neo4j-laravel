<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MovieController extends Controller
{
    public function index(): JsonResponse
    {
        $result = DB::connection('neo4j')->select('
            MATCH (m:Movie)
            OPTIONAL MATCH (m)<-[r:ACTED_IN]-(a:Person)
            RETURN m, collect(DISTINCT {actor: a, role: r.roles}) as actors
        ');

        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string',
            'released' => 'required|integer',
            'tagline' => 'required|string',
        ]);

        try {
            $result = DB::connection('neo4j')->statement(
                'CREATE (m:Movie {
                    title: $title,
                    released: $released,
                    tagline: $tagline,
                    created_at: datetime()
                }) RETURN m',
                $request->only(['title', 'released', 'tagline'])
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'type' => get_class($e)
            ], 500);
        }
    }

    public function addActor(Request $request): JsonResponse
    {
        $request->validate([
            'movie_title' => 'required|string',
            'actor_name' => 'required|string',
            'roles' => 'required|array',
            'roles.*' => 'string',
        ]);

        try {
            DB::connection('neo4j')->beginTransaction();

            $result = DB::connection('neo4j')->select(
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

            if (empty($result)) {
                DB::connection('neo4j')->rollBack();
                return response()->json(['message' => 'Movie not found'], 404);
            }

            DB::connection('neo4j')->commit();
            return response()->json($result[0]);
        } catch (\Exception $e) {
            DB::connection('neo4j')->rollBack();
            return response()->json([
                'error' => $e->getMessage(),
                'type' => get_class($e)
            ], 500);
        }
    }

    public function show(string $title): JsonResponse
    {
        $result = DB::connection('neo4j')->select(
            '
            MATCH (m:Movie {title: $title})
            OPTIONAL MATCH (m)<-[r:ACTED_IN]-(a:Person)
            RETURN m, collect(DISTINCT {actor: a, role: r.roles}) as actors',
            ['title' => $title]
        );

        if (empty($result)) {
            return response()->json(['message' => 'Movie not found'], 404);
        }

        return response()->json($result[0]);
    }

    public function findSimilar(string $title): JsonResponse
    {
        $result = DB::connection('neo4j')->select(
            '
            MATCH (m:Movie {title: $title})<-[:ACTED_IN]-(a:Person)-[:ACTED_IN]->(other:Movie)
            WHERE m <> other
            WITH other, count(distinct a) as commonActors
            RETURN other, commonActors
            ORDER BY commonActors DESC
            LIMIT 5',
            ['title' => $title]
        );

        return response()->json($result);
    }

    public function destroy(string $title): JsonResponse
    {
        $result = DB::connection('neo4j')->select(
            '
            MATCH (m:Movie {title: $title})
            OPTIONAL MATCH (m)<-[r:ACTED_IN]-()
            DELETE r, m
            RETURN count(m) as deleted',
            ['title' => $title]
        );

        if ($result[0]->deleted === 0) {
            return response()->json(['message' => 'Movie not found'], 404);
        }

        return response()->json(['message' => 'Movie and its relationships deleted successfully']);
    }
}
