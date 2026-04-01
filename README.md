# Neo4j Laravel

A Laravel package that provides integration with Neo4j graph database.

> [!WARNING]
> Database Configuration Recommendations:
>
> Laravel uses the default database connection for authentication, sessions, and other core features. While you can use Neo4j as your default database, we recommend:
>
> 1. Use a traditional database (SQLite, MySQL, PostgreSQL, etc.) as your default database connection
> 2. Use Neo4j as a secondary connection for your graph data
>
> If you choose to use Neo4j as your default database:
>
> - Set `SESSION_DRIVER=file` in your .env file
> - Laravel Authentication will not work as it relies on Eloquent ORM
> - Other features that depend on the default database connection may be affected
>
> These limitations will be addressed in future releases.

## Installation

```bash
composer require neo4j-php/neo4j-laravel
```

## Configuration

### Environment Variables

Add the following to your `.env` file:

```env
DB_CONNECTION=neo4j
NEO4J_URL=bolt://localhost:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=your_password
NEO4J_DATABASE=neo4j
```

### Database Configuration

Add the Neo4j connection configuration to your `config/database.php`:

```php
'neo4j' => [
    'driver' => 'neo4j',
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
```

## Usage

### Using Laravel's DB Facade

```php
use Illuminate\Support\Facades\DB;

// Basic query
$result = DB::connection('neo4j')->select('MATCH (n) RETURN n');

// With parameters
$result = DB::connection('neo4j')->select(<<<'CYPHER'
    MATCH (m:Movie {title: $title})
    RETURN m
CYPHER, ['title' => 'The Matrix']);

// Transactions
DB::connection('neo4j')->beginTransaction();
try {
    // Your queries here
    DB::connection('neo4j')->commit();
} catch (\Exception $e) {
    DB::connection('neo4j')->rollBack();
    throw $e;
}
```

### Using Neo4j Client Interface

```php
use Laudis\Neo4j\Contracts\SessionInterface;

class YourController extends Controller
{
    public function index(SessionInterface $session)
    {
        $result = $session->run(<<<'CYPHER'
            MATCH (n)
            RETURN n
        CYPHER);
        return response()->json($result->toArray());
    }
}
```

### Example: Movie Management

```php
// Create a movie
$result = DB::connection('neo4j')->statement(<<<'CYPHER'
    CREATE (m:Movie {
        title: $title,
        released: $released,
        tagline: $tagline,
        created_at: datetime()
    })
    RETURN m
CYPHER, [
    'title' => 'The Matrix',
    'released' => 1999,
    'tagline' => 'Welcome to the Real World'
]);

// Add an actor to a movie
$result = DB::connection('neo4j')->select(<<<'CYPHER'
    MATCH (m:Movie {title: $movieTitle})
    MERGE (a:Person {name: $actorName})
    MERGE (a)-[r:ACTED_IN]->(m)
    SET r.roles = $roles
    RETURN m, a, r
CYPHER, [
    'movieTitle' => 'The Matrix',
    'actorName' => 'Keanu Reeves',
    'roles' => ['Neo']
]);

// Find similar movies
$result = DB::connection('neo4j')->select(<<<'CYPHER'
    MATCH (m:Movie {title: $title})<-[:ACTED_IN]-(a:Person)-[:ACTED_IN]->(other:Movie)
    WHERE m <> other
    WITH other, count(distinct a) as commonActors
    RETURN other, commonActors
    ORDER BY commonActors DESC
    LIMIT 5
CYPHER, ['title' => 'The Matrix']);
```

### Example: User Management

```php
// Create a user
$result = $session->run(<<<'CYPHER'
    CREATE (u:User {
        name: $name,
        email: $email,
        created_at: datetime()
    })
    RETURN u
CYPHER, [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Update a user
$result = $session->run(<<<'CYPHER'
    MATCH (u:User {email: $email})
    SET u.name = $name, u.updated_at = datetime()
    RETURN u
CYPHER, [
    'email' => 'john@example.com',
    'name' => 'John Smith'
]);
```

## Features

- Seamless integration with Laravel's database layer
- Support for both DB Facade and Neo4j Client Interface
- Transaction support
- Parameterized queries
- SSL configuration options
- Connection pooling
- Timeout settings for connections and transactions

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
