<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laudis\Neo4j\Contracts\SessionInterface;

class UserController extends Controller
{
    public function index(SessionInterface $session): JsonResponse
    {
        $result = $session->run('MATCH (u:User) RETURN u');
        return response()->json($result->toArray());
    }

    public function store(Request $request, SessionInterface $session): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
        ]);

        $result = $session->run(
            'CREATE (u:User {name: $name, email: $email, created_at: datetime()}) RETURN u',
            [
                'name' => $request->input('name'),
                'email' => $request->input('email'),
            ]
        );

        return response()->json($result->first());
    }

    public function show(string $email, SessionInterface $session): JsonResponse
    {
        $result = $session->run(
            'MATCH (u:User {email: $email}) RETURN u',
            ['email' => $email]
        );

        if ($result->isEmpty()) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($result->first());
    }

    public function update(Request $request, string $email, SessionInterface $session): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $result = $session->run(
            'MATCH (u:User {email: $email})
             SET u.name = $name, u.updated_at = datetime()
             RETURN u',
            [
                'email' => $email,
                'name' => $request->input('name'),
            ]
        );

        if ($result->isEmpty()) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($result->first());
    }

    public function destroy(string $email, SessionInterface $session): JsonResponse
    {
        $result = $session->run(
            'MATCH (u:User {email: $email}) DELETE u RETURN count(u) as deleted',
            ['email' => $email]
        );

        if ($result->first()->get('deleted') === 0) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json(['message' => 'User deleted successfully']);
    }
}
